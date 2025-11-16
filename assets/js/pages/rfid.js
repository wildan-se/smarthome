/**
 * RFID Management Page JavaScript
 * Handle MQTT, card management, and access logs
 */

$(function () {
  "use strict";

  // Fallback error handler if utils.js not loaded
  if (typeof handleAjaxError === "undefined") {
    window.handleAjaxError = function (xhr, status, error) {
      console.error("❌ AJAX Error:", { xhr, status, error });
      console.error("Response:", xhr.responseText);
    };
  }

  // Helper function to clear blacklist
  function clearBlacklist() {
    localStorage.removeItem("lastAddedUID");
    localStorage.removeItem("lastAddTime");
    localStorage.removeItem("firstTapAfterAdd");
    console.log("🧹 Blacklist data cleared");
  }

  // Clear stale blacklist data on page load
  console.log("🧹 Clearing stale blacklist data on page load...");
  clearBlacklist();

  // MQTT Configuration
  const broker = window.mqttConfig.broker;
  const mqttUser = window.mqttConfig.username;
  const mqttPass = window.mqttConfig.password;
  const serial = window.mqttConfig.serial;
  const topicRoot = `smarthome/${serial}`;
  const mqttProtocol = window.mqttConfig.protocol;

  let lastScannedUID = "";
  let pendingRemoveUID = null; // Store UID being removed

  // Initialize MQTT Client
  const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
    username: mqttUser,
    password: mqttPass,
    clientId: "rfid-" + Math.random().toString(16).substr(2, 8),
  });

  // === MQTT CONNECTION HANDLERS ===
  client.on("connect", function () {
    client.subscribe(`${topicRoot}/rfid/info`);
    client.subscribe(`${topicRoot}/rfid/access`);
    console.log("✅ MQTT Connected - Subscribed to RFID topics");

    // Reload data saat reconnect
    console.log("🔄 Reloading RFID data after MQTT connect...");
    loadLog();
  });

  client.on("offline", function () {
    console.log("⚠️ MQTT Offline - Clearing blacklist data");
    clearBlacklist();
  });

  client.on("close", function () {
    console.log("🔌 MQTT Connection Closed - Clearing blacklist data");
    clearBlacklist();
  });

  client.on("reconnect", function () {
    console.log("🔄 MQTT Reconnecting...");
  });

  client.on("error", function (error) {
    console.error("❌ MQTT Error:", error);
  });

  // === MQTT MESSAGE HANDLER ===
  client.on("message", function (topic, message) {
    const messageStr = message.toString();
    console.log("📨 MQTT Message received:", {
      topic: topic,
      message: messageStr,
    });

    // Handle /rfid/info
    if (topic.endsWith("/rfid/info")) {
      handleRFIDInfo(messageStr);
    }

    // Handle /rfid/access
    if (topic.endsWith("/rfid/access")) {
      handleRFIDAccess(messageStr);
    }
  });

  // === RFID INFO HANDLER ===
  function handleRFIDInfo(messageStr) {
    let data = {};
    try {
      data = JSON.parse(messageStr);
    } catch (e) {
      console.error("Parse error:", e);
      return;
    }

    if (data.uid) {
      lastScannedUID = data.uid;
    }

    // Handle ADD action
    if (data.action === "add" && data.result === "ok" && data.uid) {
      const name = localStorage.getItem("pendingCard_" + data.uid) || "";
      localStorage.removeItem("pendingCard_" + data.uid);

      // Set blacklist untuk mencegah duplikasi tap pertama setelah add
      localStorage.setItem("lastAddedUID", data.uid);
      localStorage.setItem("lastAddTime", Date.now().toString());
      localStorage.removeItem("firstTapAfterAdd"); // Reset flag
      console.log(
        "✅ Blacklist set for:",
        data.uid,
        "- Will block first tap only to prevent duplicate"
      );

      // Clear blacklist after 3 seconds
      setTimeout(function () {
        const currentUID = localStorage.getItem("lastAddedUID");
        if (currentUID === data.uid) {
          clearBlacklist();
          console.log("🧹 Blacklist cleared for:", data.uid);
        }
      }, 3000);

      // Add card to database
      $.post(
        "api/rfid_crud.php?action=add",
        {
          uid: data.uid,
          name: name,
        },
        function (res) {
          if (res.success) {
            Alert.success(
              "#addResult",
              `Kartu <strong>${data.uid}</strong> (${name}) berhasil ditambahkan!`
            );
            $("#formAddRFID")[0].reset();
            loadRFID();
          } else {
            Alert.error("#addResult", res.error || "Gagal tambah kartu");
          }
        },
        "json"
      ).fail(handleAjaxError);
    }

    // Handle REMOVE action - ESP32 confirmed deletion
    // ESP32 may not send UID, so use pendingRemoveUID as fallback
    const uidToRemove = data.uid || pendingRemoveUID;

    if (data.action === "remove" && data.result === "ok" && uidToRemove) {
      console.log(`✅ ESP32 confirmed removal of UID: ${uidToRemove}`);

      // Clear pending UID
      pendingRemoveUID = null;

      // Remove from database
      $.post(
        "api/rfid_crud.php?action=remove",
        { uid: uidToRemove },
        function (res) {
          if (res.success) {
            console.log(
              `✅ Database removal successful for UID: ${uidToRemove}`
            );
            console.log(
              `📊 Deleted ${res.deleted.cards} card(s) and ${res.deleted.logs} log(s)`
            );

            // 1. Remove from card list UI with animation
            const targetUID = uidToRemove;
            let cardRowRemoved = false;

            console.log(`🔍 Looking for card row with UID: ${targetUID}`);
            console.log(
              `📋 Total rows in table: ${$("#tableRFID tbody tr").length}`
            );

            $("#tableRFID tbody tr").each(function (index) {
              const $row = $(this);
              const $codeElement = $row.find("code.code-badge");
              const rowUID = $codeElement.text().trim();

              console.log(`  Row ${index + 1}: UID = "${rowUID}"`);

              if (rowUID === targetUID) {
                console.log(
                  `  ✅ MATCH FOUND at row ${index + 1}! Removing...`
                );
                cardRowRemoved = true;

                $row.addClass("table-danger");
                $row.fadeOut(400, function () {
                  $row.remove();

                  // Update card count after removal
                  const remainingCount = $("#tableRFID tbody tr").length;
                  $("#cardCount").html(
                    `<i class="fas fa-id-card"></i> ${remainingCount} Kartu`
                  );

                  // Renumber remaining rows
                  $("#tableRFID tbody tr").each(function (idx) {
                    $(this)
                      .find("td:first strong")
                      .text(idx + 1);
                  });

                  console.log(
                    `✅ Card row removed from UI, ${remainingCount} cards remaining`
                  );
                });
              }
            });

            if (!cardRowRemoved) {
              console.warn(
                `⚠️ Card row with UID "${targetUID}" NOT FOUND in UI!`
              );
              console.log(`🔄 Forcing table refresh...`);
              loadRFID();
            }

            // 2. Remove from access log UI (all entries for this UID)
            let logsRemoved = 0;
            $("#tableLog tbody tr").each(function () {
              const logUID = $(this).find("code.code-badge").text().trim();
              if (logUID === targetUID) {
                logsRemoved++;
                const $logRow = $(this);
                $logRow.addClass("table-warning");
                $logRow.fadeOut(400, function () {
                  $logRow.remove();

                  // Renumber remaining log rows
                  $("#tableLog tbody tr").each(function (idx) {
                    $(this)
                      .find("td:first strong")
                      .text(idx + 1);
                  });
                });
              }
            });

            if (logsRemoved > 0) {
              console.log(`✅ Removed ${logsRemoved} log entries from UI`);
            }

            // 3. Check if tables are now empty and update UI
            setTimeout(function () {
              const cardCount = $("#tableRFID tbody tr").length;
              const logCount = $("#tableLog tbody tr").length;

              if (cardCount === 0) {
                $("#tableRFID tbody").html(
                  '<tr><td colspan="6" class="text-center text-muted"><i class="fas fa-inbox"></i><br>Belum ada kartu terdaftar</td></tr>'
                );
                $("#cardCount").html(`<i class="fas fa-id-card"></i> 0 Kartu`);
              }

              if (logCount === 0) {
                $("#tableLog tbody").html(
                  '<tr><td colspan="5" class="text-center text-muted"><i class="fas fa-inbox"></i><br>Belum ada riwayat akses</td></tr>'
                );
              }
            }, 450);

            Alert.success(
              "#addResult",
              `Kartu <code><strong>${targetUID}</strong></code> dan ${res.deleted.logs} riwayat akses berhasil dihapus!`
            );
          } else {
            console.error(
              `❌ Database removal failed for UID: ${uidToRemove}`,
              res.error
            );
            Alert.error(
              "#addResult",
              res.error || "Gagal menghapus kartu dari database"
            );
            // Reload to show current state
            loadRFID();
            loadLog();
          }
        },
        "json"
      ).fail(function (xhr) {
        console.error("❌ AJAX error during database removal:", xhr);
        handleAjaxError(xhr);
        loadRFID();
        loadLog();
      });
    }

    // Handle REMOVE not found - card not in ESP32
    if (data.action === "remove" && data.result === "not_found" && data.uid) {
      console.warn(`⚠️ ESP32 reports card not found: ${data.uid}`);

      // Still remove from database since ESP32 doesn't have it
      $.post(
        "api/rfid_crud.php?action=remove",
        { uid: data.uid },
        function (res) {
          if (res.success) {
            console.log(`✅ Database removal successful (ESP32 not found)`);

            // Remove from both tables in UI
            $("#tableRFID tbody tr").each(function () {
              if ($(this).find("code.code-badge").text().trim() === data.uid) {
                $(this)
                  .addClass("table-danger")
                  .fadeOut(400, function () {
                    $(this).remove();
                    const count = $("#tableRFID tbody tr").length;
                    $("#cardCount").html(
                      `<i class="fas fa-id-card"></i> ${count} Kartu`
                    );
                  });
              }
            });

            $("#tableLog tbody tr").each(function () {
              if ($(this).find("code.code-badge").text().trim() === data.uid) {
                $(this)
                  .addClass("table-warning")
                  .fadeOut(400, function () {
                    $(this).remove();
                  });
              }
            });

            Alert.success(
              "#addResult",
              `Kartu <code><strong>${data.uid}</strong></code> tidak ada di ESP32, tapi berhasil dihapus dari Database!`
            );
          } else {
            Alert.error(
              "#addResult",
              `Kartu tidak ditemukan di ESP32. Gagal menghapus dari database: ${
                res.error || "Unknown error"
              }`
            );
            loadRFID();
            loadLog();
          }
        },
        "json"
      ).fail(function (xhr) {
        console.error("❌ Failed to remove from database:", xhr);
        Alert.error(
          "#addResult",
          "Kartu tidak ada di ESP32 dan gagal dihapus dari database"
        );
        handleAjaxError(xhr);
        loadRFID();
        loadLog();
      });
    }
  }

  // === RFID ACCESS HANDLER ===
  function handleRFIDAccess(messageStr) {
    let data = {};
    try {
      data = JSON.parse(messageStr);
      console.log("🔔 Parsed /rfid/access:", data);
    } catch (e) {
      console.error("❌ Parse error /rfid/access:", e);
      return;
    }

    const uid = data.uid || lastScannedUID || "unknown";

    // Skip manual control
    if (uid === "MANUAL_CONTROL") {
      console.log("⚠️ Skipping manual control from RFID access log");
      return;
    }

    // Check blacklist - hanya untuk mencegah duplikasi saat PERTAMA kali setelah add
    const pendingUID = localStorage.getItem("lastAddedUID");
    const addTime = localStorage.getItem("lastAddTime");
    const isFirstTapAfterAdd = localStorage.getItem("firstTapAfterAdd");

    if (pendingUID && pendingUID === uid && addTime) {
      const timeDiff = Date.now() - parseInt(addTime);

      // Hanya block jika ini tap PERTAMA setelah add (dalam 3 detik)
      if (timeDiff > 0 && timeDiff < 3000 && isFirstTapAfterAdd !== "done") {
        console.log(
          "⚠️ BLOCKED: First tap after adding card (preventing duplicate):",
          uid,
          "Time diff:",
          timeDiff,
          "ms"
        );
        // Tandai bahwa tap pertama sudah di-block, tap selanjutnya diizinkan
        localStorage.setItem("firstTapAfterAdd", "done");
        return;
      } else if (timeDiff >= 3000) {
        console.log(
          "⏰ Time expired for blacklist:",
          uid,
          "Time diff:",
          timeDiff,
          "ms - Clearing stale data"
        );
        clearBlacklist();
      } else {
        console.warn(
          "⚠️ Invalid timestamp detected - Clearing corrupted blacklist data"
        );
        clearBlacklist();
      }
    }

    // Log access
    if (data.status) {
      console.log(`📝 Logging access for UID: ${uid}, Status: ${data.status}`);

      $.post(
        "api/rfid_crud.php?action=log",
        {
          uid: uid,
          status: data.status,
        },
        function (res) {
          if (res.success) {
            console.log(`✅ Access logged successfully for UID: ${uid}`);

            // Refresh log table setelah sukses
            setTimeout(function () {
              console.log("🔄 Refreshing RFID access log...");
              loadLog();
            }, 300);

            if (!data.uid) lastScannedUID = "";
          } else {
            console.warn(
              `⚠️ Access log skipped or failed for UID: ${uid}`,
              res
            );
          }
        },
        "json"
      ).fail(function (xhr) {
        console.error("❌ Failed to log RFID access:", xhr.responseText);
      });
    }
  }

  // === FORM SUBMIT HANDLER ===
  $("#formAddRFID").submit(function (e) {
    e.preventDefault();
    const uid = $(this).find("[name=uid]").val().trim();
    const name = $(this).find("[name=name]").val().trim();

    if (!uid || !name) {
      Alert.warning("#addResult", "UID dan Nama harus diisi!");
      return;
    }

    // Save to localStorage for ESP32 confirmation
    localStorage.setItem("pendingCard_" + uid, name);

    // Send register command to ESP32
    client.publish(`${topicRoot}/rfid/register`, uid);

    Swal.fire({
      title: "Menambah Kartu",
      html: `
        <div style="text-align: center; padding: 15px 5px;">
          <div style="margin-bottom: 25px;">
            <i class="fas fa-paper-plane" style="color: #17a2b8; font-size: 64px;"></i>
          </div>
          <p style="margin: 0 0 12px 0; color: #495057; font-size: 15px;">
            UID Kartu
          </p>
          <div style="background: #f8f9fa; padding: 12px 20px; border-radius: 8px; margin-bottom: 15px;">
            <code style="font-size: 20px; font-weight: 700; color: #17a2b8; letter-spacing: 1px;">${uid}</code>
          </div>
          <p style="margin: 0 0 8px 0; color: #495057; font-size: 15px; font-weight: 600;">
            ${name}
          </p>
          <p style="margin: 0; color: #6c757d; font-size: 14px;">
            sedang dikirim ke ESP32...
          </p>
        </div>
      `,
      confirmButtonText: "OK",
      confirmButtonColor: "#17a2b8",
      customClass: {
        popup: "swal2-square-popup",
        confirmButton: "btn btn-info btn-square",
      },
      buttonsStyling: false,
      width: "450px",
      timer: 3000,
    });

    $("#formAddRFID")[0].reset();
  });

  // === LOAD RFID CARDS ===
  window.loadRFID = function () {
    console.log("🔄 Loading RFID cards list...");
    $.get(
      "api/rfid_crud.php?action=list",
      function (res) {
        console.log("📊 RFID Cards Response:", res);
        let rows = "";
        if (res.success && res.data && res.data.length > 0) {
          res.data.forEach((card, index) => {
            rows += `
                        <tr class="fadeIn">
                            <td class="text-center"><strong>${
                              index + 1
                            }</strong></td>
                            <td><code class="code-badge">${card.uid}</code></td>
                            <td><i class="fas fa-user text-muted"></i> ${
                              card.name ||
                              '<em class="text-muted">Tidak ada nama</em>'
                            }</td>
                            <td><i class="far fa-calendar-alt text-muted"></i> ${
                              card.added_at
                            }</td>
                            <td><i class="fas fa-user-shield text-muted"></i> ${
                              card.added_by_name || "System"
                            }</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-danger" onclick="removeRFID('${
                                  card.uid
                                }')" title="Hapus kartu dari ESP32 dan Database">
                                    <i class="fas fa-trash-alt"></i> Hapus
                                </button>
                            </td>
                        </tr>
                    `;
          });
          $("#cardCount").html(
            `<i class="fas fa-id-card"></i> ${res.data.length} Kartu`
          );
          console.log(`✅ Rendered ${res.data.length} cards`);
        } else {
          rows =
            '<tr><td colspan="6" class="text-center text-muted"><i class="fas fa-inbox"></i><br>Belum ada kartu terdaftar</td></tr>';
          $("#cardCount").html(`<i class="fas fa-id-card"></i> 0 Kartu`);
          console.log("⚠️ No cards found");
        }
        $("#tableRFID tbody").html(rows);
      },
      "json"
    ).fail(function (xhr, status, error) {
      console.error("❌ Failed to load RFID cards:", error);
      console.error("XHR:", xhr);
      handleAjaxError(xhr, status, error);
      $("#tableRFID tbody").html(
        '<tr><td colspan="6" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i><br>Gagal memuat daftar kartu</td></tr>'
      );
    });
  };

  // === LOAD ACCESS LOGS ===
  window.loadLog = function () {
    console.log("🔄 Loading RFID access logs...");
    $.get(
      "api/rfid_crud.php?action=getlogs&limit=20",
      function (res) {
        console.log("📊 RFID Logs Response:", res);
        let rows = "";
        if (res.success && res.data && res.data.length > 0) {
          // Data sudah dibatasi 20 dari API, tidak perlu slice lagi
          res.data.forEach((l, index) => {
            const name =
              l.name || '<em class="text-muted">Tidak terdaftar</em>';
            const statusClass = l.status === "granted" ? "success" : "danger";
            const statusIcon =
              l.status === "granted" ? "check-circle" : "times-circle";
            const statusText = l.status === "granted" ? "Diterima" : "Ditolak";

            rows += `
                        <tr class="fadeIn">
                            <td class="text-center"><strong>${
                              index + 1
                            }</strong></td>
                            <td><code class="code-badge">${l.uid}</code></td>
                            <td><i class="fas fa-user text-muted"></i> ${name}</td>
                            <td class="text-center">
                                <span class="badge badge-${statusClass} pulse">
                                    <i class="fas fa-${statusIcon}"></i> ${statusText}
                                </span>
                            </td>
                            <td><i class="far fa-clock text-muted"></i> ${
                              l.access_time
                            }</td>
                        </tr>
                    `;
          });
          console.log(`✅ Rendered ${res.data.length} log entries`);
        } else {
          console.warn("⚠️ No RFID logs found");
          rows =
            '<tr><td colspan="5" class="text-center text-muted"><i class="fas fa-inbox"></i><br>Belum ada riwayat akses</td></tr>';
        }
        $("#tableLog tbody").html(rows);
      },
      "json"
    ).fail(function (xhr, status, error) {
      console.error("❌ Failed to load RFID logs:", error);
      console.error("XHR:", xhr);
      $("#tableLog tbody").html(
        '<tr><td colspan="5" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i><br>Gagal memuat riwayat akses</td></tr>'
      );
    });
  };

  // === REMOVE RFID CARD ===
  window.removeRFID = function (uid) {
    Swal.fire({
      title: "Hapus Kartu",
      html: `
        <div style="text-align: center; padding: 15px 5px;">
          <div style="margin-bottom: 25px;">
            <i class="fas fa-exclamation-circle" style="color: #dc3545; font-size: 64px;"></i>
          </div>
          <p style="margin: 0 0 12px 0; color: #495057; font-size: 15px;">
            UID Kartu
          </p>
          <div style="background: #f8f9fa; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">
            <code style="font-size: 20px; font-weight: 700; color: #dc3545; letter-spacing: 1px;">${uid}</code>
          </div>
          <p style="margin: 0; color: #6c757d; font-size: 14px;">
            akan dihapus permanen
          </p>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: "Hapus",
      cancelButtonText: "Batal",
      confirmButtonColor: "#dc3545",
      cancelButtonColor: "#6c757d",
      reverseButtons: true,
      customClass: {
        popup: "swal2-square-popup",
        confirmButton: "btn btn-danger btn-square",
        cancelButton: "btn btn-secondary btn-square",
      },
      buttonsStyling: false,
      width: "450px",
    }).then((result) => {
      if (result.isConfirmed) {
        // Show loading state
        Alert.loading("#addResult", "Mengirim perintah hapus ke ESP32...");

        // Store UID being removed (ESP32 might not send it back)
        pendingRemoveUID = uid;

        // Send remove command to ESP32 via MQTT
        console.log(`📤 Sending remove command to ESP32 for UID: ${uid}`);
        client.publish(`${topicRoot}/rfid/remove`, uid);

        // ESP32 will respond via MQTT /rfid/info with result
        // The handleRFIDInfo function will:
        // 1. Remove from database
        // 2. Remove from UI with animation
        // 3. Refresh loadRFID() to update count and numbering
        // 4. Refresh loadLog() to update access history

        console.log("⏳ Waiting for ESP32 confirmation...");
      }
    });
  };

  // === INITIALIZATION ===
  console.log("🚀 Initializing RFID page...");
  loadRFID();
  loadLog();

  // Auto-refresh every 15 seconds for both tables
  setInterval(function () {
    console.log("⏰ Auto-refreshing RFID data...");
    loadRFID();
    loadLog();
  }, 15000);

  console.log("✅ RFID page initialization complete");
});
