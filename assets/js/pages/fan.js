/**
 * Fan Control Page JavaScript
 * Handle MQTT, fan control, mode switching, and threshold settings
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
  let currentMode = "auto";
  let currentStatus = "off";
  let currentTemp = 0;
  let currentHum = 0;
  let thresholdOn = 30;
  let thresholdOff = 25;

  // Initialize MQTT Client
  const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
    username: mqttUser,
    password: mqttPass,
    clientId: "fan-" + Math.random().toString(16).substr(2, 8),
  });

  // === MQTT CONNECTION HANDLERS ===
  client.on("connect", function () {
    console.log("✅ MQTT Fan Control Connected");

    // Subscribe to topics
    client.subscribe(`${topicRoot}/kipas/status`);
    client.subscribe(`${topicRoot}/kipas/mode`);
    client.subscribe(`${topicRoot}/dht/temperature`);
    client.subscribe(`${topicRoot}/dht/humidity`);
    client.subscribe(`${topicRoot}/relay/status`);

    // Request current status
    client.publish(`${topicRoot}/system/ping`, "request_status");

    // Load settings from server
    loadSettings();
  });

  client.on("message", function (topic, message) {
    const msg = message.toString();
    console.log("📩 MQTT:", topic, "=>", msg);

    // Fan Status
    if (topic.endsWith("/kipas/status")) {
      handleFanStatus(msg);
    }

    // Fan Mode
    if (topic.endsWith("/kipas/mode")) {
      handleFanMode(msg);
    }

    // Temperature
    if (topic.endsWith("/dht/temperature")) {
      handleTemperature(msg);
    }

    // Humidity
    if (topic.endsWith("/dht/humidity")) {
      handleHumidity(msg);
    }

    // Relay Status
    if (topic.endsWith("/relay/status")) {
      handleRelayStatus(msg);
    }
  });

  // === MESSAGE HANDLERS ===

  function handleFanStatus(msg) {
    const status = msg.toLowerCase();
    currentStatus = status;

    updateFanUI(status);
  }

  function handleFanMode(msg) {
    const mode = msg.toLowerCase();
    currentMode = mode;

    updateMode(mode);
  }

  function handleTemperature(msg) {
    const temp = parseFloat(msg);
    if (!isNaN(temp) && temp > 0 && temp < 100) {
      currentTemp = temp;
      $("#currentTemp").text(temp.toFixed(1));

      // Update temperature indicator
      if (temp > thresholdOn) {
        $("#tempIndicator")
          .removeClass("bg-success bg-warning")
          .addClass("bg-danger");
      } else if (temp > thresholdOff) {
        $("#tempIndicator")
          .removeClass("bg-success bg-danger")
          .addClass("bg-warning");
      } else {
        $("#tempIndicator")
          .removeClass("bg-danger bg-warning")
          .addClass("bg-success");
      }
    }
  }

  function handleHumidity(msg) {
    const hum = parseFloat(msg);
    if (!isNaN(hum) && hum > 0 && hum <= 100) {
      currentHum = hum;
      $("#currentHum").text(hum.toFixed(1));
    }
  }

  function handleRelayStatus(msg) {
    const isOn = msg.toLowerCase().includes("on");
    const isAuto = msg.toLowerCase().includes("auto");

    updateFanUI(isOn ? "on" : "off");
    updateMode(isAuto ? "auto" : "manual");
  }

  // === UI UPDATE FUNCTIONS ===

  function updateFanUI(status) {
    const isOn = status === "on";

    // Update fan icon
    const fanIcon = $("#fanIcon");
    if (isOn) {
      fanIcon.addClass("fan-spinning");
      $("#fanStatusIcon").removeClass("fa-power-off").addClass("fa-fan");
      $("#fanCard").removeClass("bg-danger bg-warning").addClass("bg-success");
    } else {
      fanIcon.removeClass("fan-spinning");
      $("#fanStatusIcon").removeClass("fa-fan").addClass("fa-power-off");
      $("#fanCard").removeClass("bg-success bg-warning").addClass("bg-danger");
    }

    // Update status text
    $("#fanStatusText").text(isOn ? "ON" : "OFF");

    console.log("🔄 Fan UI updated:", status);
  }

  function updateMode(mode) {
    currentMode = mode;
    const isAuto = mode === "auto";

    // Update mode display
    $("#modeDisplay").text(isAuto ? "AUTO" : "MANUAL");

    // Update buttons
    $("#btnAuto").toggleClass("active", isAuto);
    $("#btnManual").toggleClass("active", !isAuto);

    // Show/hide manual controls
    if (isAuto) {
      $("#manualControls").slideUp();
      $("#autoInfo").slideDown();
    } else {
      $("#manualControls").slideDown();
      $("#autoInfo").slideUp();
    }

    console.log("🎛️ Mode updated:", mode);
  }

  // === BUTTON HANDLERS ===

  // Mode Switching
  $("#btnAuto").click(function () {
    if (currentMode === "auto") return;

    console.log("🔵 Switching to AUTO mode");

    // Update UI immediately
    updateMode("auto");

    // Publish to MQTT
    client.publish(`${topicRoot}/kipas/mode`, "auto");

    // Save to database
    $.post(
      "api/kipas_crud.php",
      {
        action: "update_mode",
        mode: "auto",
      },
      function (res) {
        if (res.success) {
          showSuccessToast("Mode AUTO diaktifkan");
        }
      },
      "json"
    ).fail(handleAjaxError);
  });

  $("#btnManual").click(function () {
    if (currentMode === "manual") return;

    console.log("🔴 Switching to MANUAL mode");

    // Update UI immediately
    updateMode("manual");

    // Publish to MQTT
    client.publish(`${topicRoot}/kipas/mode`, "manual");

    // Save to database
    $.post(
      "api/kipas_crud.php",
      {
        action: "update_mode",
        mode: "manual",
      },
      function (res) {
        if (res.success) {
          showSuccessToast("Mode MANUAL diaktifkan");
        }
      },
      "json"
    ).fail(handleAjaxError);
  });

  // Manual ON/OFF Controls
  $("#btnFanOn").click(function () {
    if (currentMode !== "manual") {
      showErrorToast("Mode harus MANUAL untuk kontrol manual");
      return;
    }

    console.log("🔵 Turning fan ON (manual)");

    // Disable button temporarily
    $(this).prop("disabled", true);

    // Publish to MQTT
    client.publish(`${topicRoot}/kipas/control`, "on");

    // Re-enable after 2 seconds
    setTimeout(() => {
      $(this).prop("disabled", false);
    }, 2000);
  });

  $("#btnFanOff").click(function () {
    if (currentMode !== "manual") {
      showErrorToast("Mode harus MANUAL untuk kontrol manual");
      return;
    }

    console.log("🔴 Turning fan OFF (manual)");

    // Disable button temporarily
    $(this).prop("disabled", true);

    // Publish to MQTT
    client.publish(`${topicRoot}/kipas/control`, "off");

    // Re-enable after 2 seconds
    setTimeout(() => {
      $(this).prop("disabled", false);
    }, 2000);
  });

  // Save Threshold Settings
  $("#btnSaveThreshold").click(function () {
    const newThresholdOn = parseFloat($("#thresholdOn").val());
    const newThresholdOff = parseFloat($("#thresholdOff").val());

    // Comprehensive validation
    if (isNaN(newThresholdOn) || isNaN(newThresholdOff)) {
      Alert.error(
        "#thresholdResult",
        "❌ Nilai suhu tidak valid. Harus berupa angka!"
      );
      return;
    }

    if (newThresholdOn <= newThresholdOff) {
      Alert.error(
        "#thresholdResult",
        "❌ Suhu ON harus lebih tinggi dari suhu OFF"
      );
      return;
    }

    if (newThresholdOn < 20 || newThresholdOn > 60) {
      Alert.error("#thresholdResult", "❌ Suhu ON harus antara 20°C - 60°C");
      return;
    }

    if (newThresholdOff < 15 || newThresholdOff > 50) {
      Alert.error("#thresholdResult", "❌ Suhu OFF harus antara 15°C - 50°C");
      return;
    }

    console.log(
      "💾 Saving threshold - ON:",
      newThresholdOn,
      "OFF:",
      newThresholdOff
    );

    // Disable button
    const btn = $(this);
    btn.prop("disabled", true);
    Alert.loading("#thresholdResult", "Menyimpan pengaturan...");

    // Save to database
    $.post(
      "api/kipas_crud.php",
      {
        action: "update_settings",
        threshold_on: newThresholdOn,
        threshold_off: newThresholdOff,
        mode: currentMode,
      },
      function (res) {
        if (res.success) {
          thresholdOn = newThresholdOn;
          thresholdOff = newThresholdOff;

          // Publish to ESP32
          const thresholdData = JSON.stringify({
            on: newThresholdOn,
            off: newThresholdOff,
          });
          console.log("📤 Publishing threshold to ESP32:", thresholdData);
          client.publish(`${topicRoot}/kipas/threshold`, thresholdData);

          Alert.success(
            "#thresholdResult",
            `✅ Pengaturan threshold berhasil disimpan! ON: ${newThresholdOn}°C, OFF: ${newThresholdOff}°C`
          );

          // Re-enable button after 2 seconds
          setTimeout(() => btn.prop("disabled", false), 2000);
        } else {
          Alert.error(
            "#thresholdResult",
            "❌ Gagal menyimpan: " + (res.error || "Unknown error")
          );
          btn.prop("disabled", false);
        }
      },
      "json"
    ).fail(function (xhr) {
      console.error("❌ Threshold save failed:", xhr.responseText);
      Alert.error(
        "#thresholdResult",
        "❌ Gagal menghubungi server. Silakan coba lagi."
      );
      btn.prop("disabled", false);
    });
  });

  // === LOAD SETTINGS FROM SERVER ===
  function loadSettings() {
    $.get(
      "api/kipas_crud.php?action=get_settings",
      function (res) {
        if (res.success && res.data) {
          const data = res.data;

          // Update thresholds
          thresholdOn = parseFloat(data.threshold_on) || 30;
          thresholdOff = parseFloat(data.threshold_off) || 25;

          $("#thresholdOn").val(thresholdOn);
          $("#thresholdOff").val(thresholdOff);

          // Update mode
          currentMode = data.mode || "auto";
          updateMode(currentMode);

          console.log("✅ Settings loaded:", {
            thresholdOn,
            thresholdOff,
            mode: currentMode,
          });
        }
      },
      "json"
    ).fail(function () {
      console.warn("⚠️ Failed to load settings, using defaults");
    });
  }

  // Initial load
  console.log("🎯 Fan Control Page Initialized");
});
