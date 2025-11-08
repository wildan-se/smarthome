/**
 * Door Control Page JavaScript
 * Handle MQTT and door servo control
 */

$(function () {
  "use strict";

  // MQTT Configuration
  const broker = window.mqttConfig.broker;
  const mqttUser = window.mqttConfig.username;
  const mqttPass = window.mqttConfig.password;
  const serial = window.mqttConfig.serial;
  const topicRoot = `smarthome/${serial}`;
  const mqttProtocol = window.mqttConfig.protocol;

  // State variables
  let currentDoorStatus = "tertutup";
  let isProcessing = false;

  // Initialize MQTT Client
  const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
    username: mqttUser,
    password: mqttPass,
    clientId: "door-" + Math.random().toString(16).substr(2, 8),
  });

  // === MQTT CONNECTION HANDLERS ===
  client.on("connect", function () {
    console.log("✅ MQTT Door Control Connected");

    // Subscribe to door status
    client.subscribe(`${topicRoot}/pintu/status`);

    // Request current status
    client.publish(`${topicRoot}/system/ping`, "request_status");
  });

  client.on("message", function (topic, message) {
    const msg = message.toString();
    console.log("📩 MQTT:", topic, "=>", msg);

    // Door Status
    if (topic.endsWith("/pintu/status")) {
      handleDoorStatus(msg);
    }
  });

  // === DOOR STATUS HANDLER ===
  function handleDoorStatus(msg) {
    const status = msg.toLowerCase();
    currentDoorStatus = status;

    updateDoorUI(status);

    // Reset processing flag
    isProcessing = false;
  }

  // === UI UPDATE FUNCTION ===
  function updateDoorUI(status) {
    const isOpen = status === "terbuka";

    // Update door icon and card
    const doorIcon = $("#doorIcon");
    const doorCard = $("#doorCard");
    const statusText = $("#doorStatusText");

    if (isOpen) {
      doorIcon.removeClass("fa-door-closed").addClass("fa-door-open");
      doorCard.removeClass("bg-success").addClass("bg-warning");
      statusText.text("TERBUKA");
    } else {
      doorIcon.removeClass("fa-door-open").addClass("fa-door-closed");
      doorCard.removeClass("bg-warning").addClass("bg-success");
      statusText.text("TERTUTUP");
    }

    // Update last update time
    const now = new Date().toLocaleString("id-ID", {
      timeZone: "Asia/Jakarta",
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });
    $("#lastUpdate").text("Update: " + now);

    console.log("🚪 Door UI updated:", status);
  }

  // === BUTTON HANDLERS ===

  // Open Door
  $("#btnOpen").click(function () {
    if (isProcessing) {
      showErrorToast("Sedang memproses perintah sebelumnya...");
      return;
    }

    if (currentDoorStatus === "terbuka") {
      showErrorToast("Pintu sudah terbuka");
      return;
    }

    console.log("🔓 Opening door...");
    isProcessing = true;

    // Disable button temporarily
    $(this).prop("disabled", true);

    // Show loading
    Alert.loading("#doorResult", "Membuka pintu...");

    // Publish to MQTT
    client.publish(`${topicRoot}/pintu/control`, "buka");

    // Log to database
    $.post(
      "api/door_log.php",
      {
        action: "log",
        status: "terbuka",
        source: "manual",
      },
      function (res) {
        if (res.success) {
          Alert.success("#doorResult", "✅ Perintah buka pintu dikirim!");
        }
      },
      "json"
    ).fail(handleAjaxError);

    // Re-enable button after 3 seconds
    setTimeout(() => {
      $(this).prop("disabled", false);
      isProcessing = false;
    }, 3000);
  });

  // Close Door
  $("#btnClose").click(function () {
    if (isProcessing) {
      showErrorToast("Sedang memproses perintah sebelumnya...");
      return;
    }

    if (currentDoorStatus === "tertutup") {
      showErrorToast("Pintu sudah tertutup");
      return;
    }

    console.log("🔒 Closing door...");
    isProcessing = true;

    // Disable button temporarily
    $(this).prop("disabled", true);

    // Show loading
    Alert.loading("#doorResult", "Menutup pintu...");

    // Publish to MQTT
    client.publish(`${topicRoot}/pintu/control`, "tutup");

    // Log to database
    $.post(
      "api/door_log.php",
      {
        action: "log",
        status: "tertutup",
        source: "manual",
      },
      function (res) {
        if (res.success) {
          Alert.success("#doorResult", "✅ Perintah tutup pintu dikirim!");
        }
      },
      "json"
    ).fail(handleAjaxError);

    // Re-enable button after 3 seconds
    setTimeout(() => {
      $(this).prop("disabled", false);
      isProcessing = false;
    }, 3000);
  });

  // === LOAD DOOR LOGS ===
  function loadDoorLogs() {
    $.get(
      "api/door_log.php?action=getlogs&limit=20",
      function (res) {
        let rows = "";
        if (res.success && res.data && res.data.length > 0) {
          res.data.forEach((log, index) => {
            const statusClass =
              log.status === "terbuka" ? "warning" : "success";
            const statusIcon =
              log.status === "terbuka" ? "door-open" : "door-closed";
            const statusText =
              log.status === "terbuka" ? "Terbuka" : "Tertutup";
            const sourceIcon =
              log.source === "manual" ? "hand-pointer" : "id-card";
            const sourceText = log.source === "manual" ? "Manual" : "RFID";

            rows += `
                        <tr class="fadeIn">
                            <td class="text-center"><strong>${
                              index + 1
                            }</strong></td>
                            <td>
                                <span class="badge badge-${statusClass}">
                                    <i class="fas fa-${statusIcon}"></i> ${statusText}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <i class="fas fa-${sourceIcon}"></i> ${sourceText}
                                </span>
                            </td>
                            <td><i class="far fa-clock"></i> ${
                              log.timestamp
                            }</td>
                        </tr>
                    `;
          });
        } else {
          rows =
            '<tr><td colspan="4" class="text-center text-muted"><i class="fas fa-inbox"></i><br>Belum ada riwayat</td></tr>';
        }
        $("#tableDoorLogs tbody").html(rows);
      },
      "json"
    ).fail(function () {
      $("#tableDoorLogs tbody").html(
        '<tr><td colspan="4" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i><br>Gagal memuat riwayat</td></tr>'
      );
    });
  }

  // Make loadDoorLogs available globally
  window.loadDoorLogs = loadDoorLogs;

  // Initial load
  loadDoorLogs();

  // Auto-refresh every 10 seconds
  setInterval(function () {
    console.log("⏰ Auto-refreshing door logs...");
    loadDoorLogs();
  }, 10000);

  console.log("🎯 Door Control Page Initialized");
});
