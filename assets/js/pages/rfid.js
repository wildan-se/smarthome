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

    // Handle REMOVE action
    if (data.action === "remove" && data.result === "ok" && data.uid) {
      $.post(
        "api/rfid_crud.php?action=remove",
        { uid: data.uid },
        function (res) {
          if (res.success) {
            Alert.success(
              "#addResult",
              `Kartu <strong>${data.uid}</strong> berhasil dihapus dari ESP32 dan database!`
            );
            loadRFID();
            loadLog();
          } else {
            Alert.error("#addResult", res.error || "Gagal menghapus kartu");
          }
        },
        "json"
      ).fail(handleAjaxError);
    }

    // Handle REMOVE not found
    if (data.action === "remove" && data.result === "not_found" && data.uid) {
      Swal.fire({
        title: "Kartu Tidak Ditemukan di ESP32",
        html: `Kartu <code><strong>${data.uid}</strong></code> tidak ditemukan di ESP32.<br>Hapus dari database saja?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Ya, hapus dari DB",
        cancelButtonText: "Batal",
        confirmButtonColor: "#ffc107",
      }).then((result) => {
        if (result.isConfirmed) {
          removeFromDBOnly(data.uid);
        }
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
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-danger" onclick="removeRFID('${
                                      card.uid
                                    }')" title="Hapus di ESP32 & DB">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="removeFromDBOnly('${
                                      card.uid
                                    }')" title="Hapus hanya di Database">
                                        <i class="fas fa-database"></i> DB
                                    </button>
                                </div>
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
      title: "Hapus Kartu",
      html: `Hapus kartu dengan UID: <code><strong>${uid}</strong></code>?`,
      icon: "warning",
      showDenyButton: true,
      showCancelButton: true,
      confirmButtonText:
        '<i class="fas fa-microchip"></i> Hapus di ESP32 & Database',
      denyButtonText: '<i class="fas fa-database"></i> Hapus hanya di Database',
      cancelButtonText: '<i class="fas fa-times"></i> Batal',
      confirmButtonColor: "#dc3545",
      denyButtonColor: "#ffc107",
      cancelButtonColor: "#6c757d",
      focusCancel: true,
    }).then((result) => {
      if (result.isConfirmed) {
        // Hapus di ESP32 dan Database
        Alert.loading("#addResult", "Menghapus kartu dari ESP32...");
        client.publish(`${topicRoot}/rfid/remove`, uid);

        setTimeout(function () {
          Alert.success(
            "#addResult",
            `Perintah hapus dikirim ke ESP32 untuk kartu <code><strong>${uid}</strong></code>`
          );
        }, 1000);
      } else if (result.isDenied) {
        // Hapus dari Database saja
        removeFromDBOnly(uid);
      }
    });
  };

  // === REMOVE FROM DB ONLY ===
  window.removeFromDBOnly = function (uid) {
    Swal.fire({
      title: "Hapus dari Database saja?",
      html: `Kartu <code><strong>${uid}</strong></code> akan dihapus dari database tetapi tetap tersimpan di ESP32. Lanjutkan?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Ya, hapus dari DB",
      cancelButtonText: "Batal",
      confirmButtonColor: "#ffc107",
    }).then((result) => {
      if (!result.isConfirmed) return;

      Alert.loading("#addResult", "Menghapus dari database...");

      $.post(
        "api/rfid_crud.php?action=remove",
        { uid: uid },
        function (res) {
          if (res.success) {
            Alert.success(
              "#addResult",
              `Kartu <code><strong>${uid}</strong></code> berhasil dihapus dari database! (Masih ada di ESP32)`
            );
            loadRFID();
            loadLog();
          } else {
            Alert.error(
              "#addResult",
              res.error || "Gagal menghapus dari database"
            );
          }
        },
        "json"
      ).fail(handleAjaxError);
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
