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

      // Set blacklist
      localStorage.setItem("lastAddedUID", data.uid);
      localStorage.setItem("lastAddTime", Date.now().toString());
      console.log(
        "✅ Blacklist set for:",
        data.uid,
        "- Will skip in access log for 3 seconds"
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
    if (data.action === "remove" && data.result === "ok" && data.uid) {
      console.log(`✅ ESP32 confirmed removal of UID: ${data.uid}`);

      // Remove from database
      $.post(
        "api/rfid_crud.php?action=remove",
        { uid: data.uid },
        function (res) {
          if (res.success) {
            console.log(`✅ Database removal successful for UID: ${data.uid}`);
            Alert.success(
              "#addResult",
              `Kartu <code><strong>${data.uid}</strong></code> berhasil dihapus dari ESP32 dan Database!`
            );
            // Refresh tables to ensure sync
            loadRFID();
            loadLog();
          } else {
            console.error(
              `❌ Database removal failed for UID: ${data.uid}`,
              res.error
            );
            Alert.error(
              "#addResult",
              res.error || "Gagal menghapus kartu dari database"
            );
            // Reload to show current state
            loadRFID();
          }
        },
        "json"
      ).fail(function (xhr) {
        console.error("❌ AJAX error during database removal:", xhr);
        handleAjaxError(xhr);
        loadRFID();
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
            Alert.success(
              "#addResult",
              `Kartu <code><strong>${data.uid}</strong></code> tidak ada di ESP32, tapi berhasil dihapus dari Database!`
            );
            loadRFID();
            loadLog();
          } else {
            Alert.error(
              "#addResult",
              `Kartu tidak ditemukan di ESP32. Gagal menghapus dari database: ${
                res.error || "Unknown error"
              }`
            );
            loadRFID();
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

    // Check blacklist
    const pendingUID = localStorage.getItem("lastAddedUID");
    const addTime = localStorage.getItem("lastAddTime");
    if (pendingUID && pendingUID === uid && addTime) {
      const timeDiff = Date.now() - parseInt(addTime);

      if (timeDiff > 0 && timeDiff < 3000) {
        console.log(
          "⚠️ BLOCKED: Newly added card from access log:",
          uid,
          "Time diff:",
          timeDiff,
          "ms"
        );
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
      $.post(
        "api/rfid_crud.php?action=log",
        {
          uid: uid,
          status: data.status,
        },
        function (res) {
          if (res.success) {
            setTimeout(function () {
              console.log("🔄 Refreshing RFID access log...");
              loadLog();
            }, 500);

            if (!data.uid) lastScannedUID = "";
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
      title: "Menunggu Konfirmasi",
      html: `Mengirim kartu <code><strong>${uid}</strong></code><br><strong>${name}</strong><br>ke ESP32...`,
      icon: "info",
      confirmButtonColor: "#17a2b8",
      confirmButtonText: '<i class="fas fa-check"></i> OK',
      timer: 3000,
    });

    $("#formAddRFID")[0].reset();
  });

  // === LOAD RFID CARDS ===
  window.loadRFID = function () {
    $.get(
      "api/rfid_crud.php?action=list",
      function (res) {
        let rows = "";
        if (res.success && res.data) {
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
        } else {
          rows =
            '<tr><td colspan="6" class="text-center text-muted"><i class="fas fa-inbox"></i><br>Belum ada kartu terdaftar</td></tr>';
        }
        $("#tableRFID tbody").html(rows);
      },
      "json"
    ).fail(handleAjaxError);
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
      title: "Hapus Kartu RFID",
      html: `
        <div style="text-align: left; padding: 10px;">
          <p style="margin-bottom: 15px; color: #666;">
            Anda akan menghapus kartu dengan UID:
          </p>
          <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; border-left: 4px solid #dc3545; margin-bottom: 15px;">
            <code style="font-size: 16px; font-weight: 600; color: #dc3545;">${uid}</code>
          </div>
          <p style="margin-bottom: 8px; color: #666; font-size: 14px;">
            <i class="fas fa-info-circle" style="color: #17a2b8; margin-right: 5px;"></i>
            Kartu akan dihapus dari sistem secara permanen
          </p>
        </div>
      `,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: '<i class="fas fa-trash-alt"></i> Hapus',
      cancelButtonText: '<i class="fas fa-times"></i> Batal',
      confirmButtonColor: "#dc3545",
      cancelButtonColor: "#6c757d",
      reverseButtons: true,
      customClass: {
        popup: "swal2-custom-popup",
        confirmButton: "btn btn-danger",
        cancelButton: "btn btn-secondary",
      },
      buttonsStyling: false,
      width: "500px",
    }).then((result) => {
      if (result.isConfirmed) {
        // Show loading state
        Alert.loading(
          "#addResult",
          "Menghapus kartu dari ESP32 dan Database..."
        );

        // 1. Send remove command to ESP32 via MQTT
        console.log(`📤 Sending remove command to ESP32 for UID: ${uid}`);
        client.publish(`${topicRoot}/rfid/remove`, uid);

        // 2. Remove from UI immediately with animation
        $("#tableRFID tbody tr").each(function () {
          const rowUID = $(this).find("code").text();
          if (rowUID === uid) {
            $(this).fadeOut(300, function () {
              $(this).remove();
              // Update card count
              const remainingCards = $("#tableRFID tbody tr:visible").length;
              $("#cardCount").html(
                `<i class="fas fa-id-card"></i> ${remainingCards} Kartu`
              );
            });
          }
        });

        // 3. ESP32 will respond via MQTT /rfid/info with result
        // The handleRFIDInfo function will handle database removal

        // Show success message
        setTimeout(function () {
          Alert.success(
            "#addResult",
            `Kartu <code><strong>${uid}</strong></code> berhasil dihapus dari ESP32, Database, dan UI!`
          );
        }, 500);
      }
    });
  };

  // === INITIALIZATION ===
  console.log("🚀 Initializing RFID page...");
  loadRFID();
  loadLog();

  // Auto-refresh every 10 seconds
  setInterval(function () {
    console.log("⏰ Auto-refreshing RFID logs...");
    loadLog();
  }, 10000);

  console.log("✅ RFID page initialization complete");
});
