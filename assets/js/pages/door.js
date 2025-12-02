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
  const statusTopic = `smarthome/status/${serial}`;
  const mqttProtocol = window.mqttConfig.protocol;

  // State variables
  let currentDoorStatus = "tertutup";
  let isProcessing = false;
  let manualServoControl = false; // Flag untuk kontrol manual slider

  // Initialize MQTT Client
  const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
    username: mqttUser,
    password: mqttPass,
    clientId: "door-" + Math.random().toString(16).substr(2, 8),
  });

  // === MQTT CONNECTION HANDLERS ===
  client.on("connect", function () {
    console.log("✅ MQTT Door Control Connected");

    // Subscribe to topics
    client.subscribe(statusTopic, { qos: 1 });
    client.subscribe(`${topicRoot}/pintu/status`, { qos: 1 });
    client.subscribe(`${topicRoot}/rfid/access`, { qos: 1 }); // ✅ Subscribe RFID access

    // Request current status
    setTimeout(() => {
      client.publish(`${topicRoot}/request`, "status", { qos: 1 });
      console.log("📤 Requesting current status from ESP32...");
    }, 500);
  });

  client.on("error", function (err) {
    console.error("❌ MQTT Error:", err);
    updateESPStatus("offline");
  });

  client.on("message", function (topic, message) {
    const msg = message.toString();
    console.log("📩 MQTT:", topic, "=>", msg);

    // ESP32 Status
    if (topic === statusTopic) {
      updateESPStatus(msg);
    }

    // Door Status
    if (topic.endsWith("/pintu/status")) {
      handleDoorStatus(msg);
    }

    // ✅ RFID Access - Reload logs jika kartu granted
    if (topic.endsWith("/rfid/access")) {
      try {
        const data = JSON.parse(msg);
        console.log("🪪 RFID Access:", data);

        if (data.status === "granted") {
          console.log("✅ RFID Granted - Reloading door logs in 1 second...");
          // Delay lebih lama untuk memastikan HTTP POST selesai
          setTimeout(() => {
            loadDoorLogs();
          }, 1000);
        }
      } catch (e) {
        console.error("❌ Failed to parse RFID access:", e);
      }
    }
  });

  // === ESP32 STATUS HANDLER ===
  function updateESPStatus(status) {
    const espIcon = $("#espStatus").find("i");
    const espText = $("#espText");

    if (status === "online") {
      espIcon.attr("class", "fas fa-circle text-success pulse");
      espText
        .text("Online")
        .attr("class", "text-success font-weight-bold mb-2");
    } else {
      espIcon.attr("class", "fas fa-circle text-danger pulse");
      espText
        .text("Offline")
        .attr("class", "text-danger font-weight-bold mb-2");
    }
  }

  // === DOOR STATUS HANDLER ===
  function handleDoorStatus(msg) {
    const status = msg.toLowerCase();
    currentDoorStatus = status;

    updateDoorUI(status);

    // ✅ Reload door logs ketika ada perubahan status (termasuk dari RFID)
    console.log("🔄 Door status changed, reloading logs...");
    setTimeout(() => {
      loadDoorLogs();
    }, 500); // Delay 500ms - cukup karena HTTP POST sudah blocking sebelum MQTT publish

    // Update slider position based on status HANYA jika bukan dari kontrol manual slider
    if (!manualServoControl) {
      if (status === "terbuka") {
        $("#servoSlider").val(90);
        $("#sliderValue").text(90);
      } else if (status === "tertutup") {
        $("#servoSlider").val(0);
        $("#sliderValue").text(0);
      }
    }

    // Reset processing flag dan manual control flag setelah delay
    setTimeout(() => {
      isProcessing = false;
      manualServoControl = false;
    }, 2000);
  }

  // === UI UPDATE FUNCTION ===
  function updateDoorUI(status) {
    const doorIcon = $("#doorIcon");
    const doorText = $("#doorText");

    if (status === "terbuka") {
      doorIcon.attr("class", "fas fa-door-open text-success pulse");
      doorText
        .text("Terbuka")
        .attr("class", "text-success font-weight-bold mb-2");
    } else {
      doorIcon.attr("class", "fas fa-door-closed text-secondary");
      doorText
        .text("Tertutup")
        .attr("class", "text-secondary font-weight-bold mb-2");
    }

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

    // Publish servo position to MQTT
    client.publish(`${topicRoot}/servo`, "90", { qos: 1 });

    // Publish status
    client.publish(`${topicRoot}/pintu/status`, "terbuka", {
      retain: true,
      qos: 1,
    });

    // Publish source flag
    client.publish(`${topicRoot}/kontrol/source`, "manual", { qos: 1 });

    // Update slider
    $("#servoSlider").val(90);
    $("#sliderValue").text(90);

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
          Alert.success(
            "#doorResult",
            "✅ Perintah buka pintu dikirim ke ESP32!"
          );
          loadDoorLogs(); // Refresh logs
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

    // Publish servo position to MQTT
    client.publish(`${topicRoot}/servo`, "0", { qos: 1 });

    // Publish status
    client.publish(`${topicRoot}/pintu/status`, "tertutup", {
      retain: true,
      qos: 1,
    });

    // Publish source flag
    client.publish(`${topicRoot}/kontrol/source`, "manual", { qos: 1 });

    // Update slider
    $("#servoSlider").val(0);
    $("#sliderValue").text(0);

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
          Alert.success(
            "#doorResult",
            "✅ Perintah tutup pintu dikirim ke ESP32!"
          );
          loadDoorLogs(); // Refresh logs
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

  // === SERVO SLIDER HANDLERS ===

  // Update slider value display
  $("#servoSlider").on("input", function () {
    $("#sliderValue").text($(this).val());
  });

  // Apply servo position
  $("#btnApplyServo").click(function () {
    if (isProcessing) {
      showErrorToast("Sedang memproses perintah sebelumnya...");
      return;
    }

    const pos = parseInt($("#servoSlider").val());
    console.log(`🎚️ Setting servo to ${pos}°`);
    isProcessing = true;
    manualServoControl = true; // Set flag bahwa ini kontrol manual slider

    // Disable button temporarily
    $(this).prop("disabled", true);

    // Show loading
    Alert.loading("#sliderResult", `Mengatur servo ke ${pos}°...`);

    // Publish servo position to MQTT
    client.publish(`${topicRoot}/servo`, pos.toString(), { qos: 1 });

    // Determine status based on position (same logic as ESP32)
    const status = pos > 45 ? "terbuka" : "tertutup";

    // Publish status
    client.publish(`${topicRoot}/pintu/status`, status, {
      retain: true,
      qos: 1,
    });

    // Publish source flag
    client.publish(`${topicRoot}/kontrol/source`, "manual", { qos: 1 });

    // Log to database
    $.post(
      "api/door_log.php",
      {
        action: "log",
        status: status,
        source: "manual",
      },
      function (res) {
        if (res.success) {
          Alert.success(
            "#sliderResult",
            `✅ Posisi servo diatur ke ${pos}° (Status: ${status.toUpperCase()})`
          );
          loadDoorLogs(); // Refresh logs
        }
      },
      "json"
    ).fail(handleAjaxError);

    console.log(`📤 Sent: Servo ${pos}° => Status ${status} - Manual Control`);

    // Re-enable button after 3 seconds
    setTimeout(() => {
      $(this).prop("disabled", false);
      isProcessing = false;
    }, 3000);
  });

  // === LOAD DOOR LOGS ===
  function loadDoorLogs() {
    console.log("📊 Loading door logs...");
    $.get(
      "api/door_log.php?action=get_logs&per_page=20",
      function (res) {
        console.log("✅ Door logs received:", res);
        let rows = "";
        if (res.success && res.data && res.data.length > 0) {
          console.log("📝 Processing", res.data.length, "records");

          try {
            res.data.forEach((log, index) => {
              const statusClass =
                log.status === "terbuka" ? "warning" : "success";
              const statusIcon =
                log.status === "terbuka" ? "door-open" : "door-closed";
              const statusText =
                log.status === "terbuka" ? "Terbuka" : "Tertutup";

              // Handle different source types
              let sourceIcon = "hand-pointer";
              let sourceText = "Manual";
              let sourceBadge = "info";

              if (log.source === "rfid") {
                sourceIcon = "id-card";
                sourceText = "RFID";
                sourceBadge = "primary";
              } else if (log.source === "auto") {
                sourceIcon = "robot";
                sourceText = "Auto";
                sourceBadge = "secondary";
              }

              // Build row HTML - simplified to avoid template string issues
              const row =
                '<tr class="fadeIn">' +
                '<td class="text-center"><strong>' +
                (index + 1) +
                "</strong></td>" +
                "<td>" +
                '<span class="badge badge-' +
                statusClass +
                '">' +
                '<i class="fas fa-' +
                statusIcon +
                '"></i> ' +
                statusText +
                "</span>" +
                "</td>" +
                "<td>" +
                '<span class="badge badge-' +
                sourceBadge +
                '">' +
                '<i class="fas fa-' +
                sourceIcon +
                '"></i> ' +
                sourceText +
                "</span>" +
                "</td>" +
                '<td><i class="far fa-clock"></i> ' +
                log.timestamp +
                "</td>" +
                "</tr>";

              rows += row;
            });
            console.log("✅ All rows generated successfully");
          } catch (err) {
            console.error("❌ Error generating rows:", err);
            rows =
              '<tr><td colspan="4" class="text-center text-danger">Error: ' +
              err.message +
              "</td></tr>";
          }
        } else {
          console.log("⚠️ No door log data available");
          rows =
            '<tr><td colspan="4" class="text-center text-muted"><i class="fas fa-inbox"></i><br>Belum ada riwayat</td></tr>';
        }
        console.log(
          "🔄 Updating table with",
          rows.length > 200 ? "data" : "empty message"
        );

        // Debug: Log sample HTML
        console.log(
          "📄 Generated HTML (first 500 chars):",
          rows.substring(0, 500)
        );

        // Debug: Check if table exists
        const tableBody = $("#tableDoorLogs tbody");
        console.log("🎯 Table selector found:", tableBody.length, "elements");

        if (tableBody.length === 0) {
          console.error("❌ Table #tableDoorLogs tbody NOT FOUND!");
          return;
        }

        tableBody.html(rows);
        console.log("✅ Table updated successfully!");

        // Verify what's actually in the DOM after 100ms
        setTimeout(() => {
          const actualRows = $("#tableDoorLogs tbody tr").length;
          console.log("🔍 Verification: Table now has", actualRows, "rows");
          if (actualRows === 0) {
            console.error("⚠️ WARNING: Rows disappeared!");
          }
        }, 100);
      },
      "json"
    ).fail(function (xhr, status, error) {
      console.error("❌ Failed to load door logs:", status, error);
      console.error("Response:", xhr.responseText);
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
    loadDoorLogs();
  }, 10000);

  console.log("🎯 Door Control Page Initialized");
});
