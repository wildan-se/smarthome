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

  // Bug fix: Prevent circular mode updates
  let modeUpdateInProgress = false;
  let lastModeUpdate = 0;
  const MODE_UPDATE_COOLDOWN = 2000; // 2 seconds cooldown

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

    // Fan Mode - FIX: Call handleModeChange instead of handleFanMode
    if (topic.endsWith("/kipas/mode")) {
      handleModeChange(msg);
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

  function handleModeChange(msg) {
    const mode = msg.toLowerCase();

    // Bug fix: Prevent update if in progress or within cooldown period
    const now = Date.now();
    if (modeUpdateInProgress || now - lastModeUpdate < MODE_UPDATE_COOLDOWN) {
      console.log("⏳ Mode update skipped (cooldown active)");
      return;
    }

    currentMode = mode;
    updateMode(mode);
  }

  function handleTemperature(msg) {
    const temp = parseFloat(msg);
    if (!isNaN(temp) && temp > 0 && temp < 100) {
      currentTemp = temp;
      $("#currentTemp").text(temp.toFixed(1) + "°C");

      // Update temperature icon color
      const tempIcon = $("#tempIcon");
      tempIcon.removeClass("temp-cold temp-cool temp-warm temp-hot");

      if (temp < 20) {
        tempIcon.addClass("temp-cold");
      } else if (temp < 25) {
        tempIcon.addClass("temp-cool");
      } else if (temp < 30) {
        tempIcon.addClass("temp-warm");
      } else {
        tempIcon.addClass("temp-hot");
      }

      // Update temperature indicator gauge
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

      // Update gauge position (0-50°C scale)
      const gaugePercent = Math.min((temp / 50) * 100, 100);
      $("#tempIndicator").css("left", gaugePercent + "%");
    }
  }

  function handleHumidity(msg) {
    const hum = parseFloat(msg);
    if (!isNaN(hum) && hum > 0 && hum <= 100) {
      currentHum = hum;
      $("#currentHumidity").text(hum.toFixed(1) + "%");
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
    const fanStatusIcon = $("#fanStatusIcon");

    if (isOn) {
      fanIcon.addClass("fan-spinning");
      fanStatusIcon
        .removeClass("bg-secondary bg-danger")
        .addClass("bg-success");
      $("#fanStatusText").html('<strong class="text-success">ON</strong>');
    } else {
      fanIcon.removeClass("fan-spinning");
      fanStatusIcon
        .removeClass("bg-success bg-secondary")
        .addClass("bg-danger");
      $("#fanStatusText").html('<strong class="text-danger">OFF</strong>');
    }

    console.log("🔄 Fan UI updated:", status);
  }

  function updateMode(mode) {
    currentMode = mode;
    const isAuto = mode === "auto";

    // Update mode text display
    $("#modeText").text(isAuto ? "AUTO" : "MANUAL");

    // Update mode switch checkbox (without triggering change event)
    $("#modeSwitch").off("change"); // Temporarily remove handler
    $("#modeSwitch").prop("checked", isAuto);

    // Re-attach handler after a short delay
    setTimeout(() => {
      $("#modeSwitch").on("change", handleModeSwitch);
    }, 100);

    // Update buttons if they exist
    $("#btnAuto").toggleClass("active", isAuto);
    $("#btnManual").toggleClass("active", !isAuto);

    // Show/hide manual controls
    if (isAuto) {
      $("#manualControls").slideUp();
      if ($("#autoInfo").length) $("#autoInfo").slideDown();
    } else {
      $("#manualControls").slideDown();
      if ($("#autoInfo").length) $("#autoInfo").slideUp();
    }

    console.log("🎛️ Mode updated:", mode);
  }

  // === BUTTON HANDLERS ===

  // Mode Switch Handler Function (separated for better control)
  function handleModeSwitch() {
    const isChecked = $(this).is(":checked");
    const newMode = isChecked ? "auto" : "manual";

    // Prevent if same mode
    if (currentMode === newMode) {
      console.log("⏭️ Mode switch skipped (already in " + newMode + " mode)");
      return;
    }

    // Prevent spam clicks with cooldown
    const now = Date.now();
    if (modeUpdateInProgress) {
      console.log("⏳ Mode switch blocked (update in progress)");
      // Revert checkbox state
      $("#modeSwitch").prop("checked", currentMode === "auto");
      return;
    }

    if (now - lastModeUpdate < MODE_UPDATE_COOLDOWN) {
      const remaining = Math.ceil(
        (MODE_UPDATE_COOLDOWN - (now - lastModeUpdate)) / 1000
      );
      console.log(`⏳ Mode switch blocked (cooldown: ${remaining}s remaining)`);
      showWarningToast(`Tunggu ${remaining} detik sebelum mengubah mode lagi`);
      // Revert checkbox state
      $("#modeSwitch").prop("checked", currentMode === "auto");
      return;
    }

    console.log(`🔄 Mode switch toggled to: ${newMode.toUpperCase()}`);

    // Set flags
    modeUpdateInProgress = true;
    lastModeUpdate = now;

    // Update mode immediately
    currentMode = newMode;
    updateMode(newMode);

    // Publish to MQTT
    client.publish(`${topicRoot}/kipas/mode`, newMode);

    // Save to database
    $.post(
      "api/kipas_crud.php",
      {
        action: "update_mode",
        mode: newMode,
      },
      function (res) {
        if (res.success) {
          showSuccessToast(`Mode ${newMode.toUpperCase()} diaktifkan`);
        } else {
          showErrorToast(
            "Gagal mengubah mode: " + (res.error || "Unknown error")
          );
        }
        // Release flag after success/fail
        setTimeout(() => {
          modeUpdateInProgress = false;
        }, 500);
      },
      "json"
    ).fail(function (xhr) {
      handleAjaxError(xhr);
      // Release flag on error
      setTimeout(() => {
        modeUpdateInProgress = false;
      }, 500);
    });
  }

  // Mode Switch Toggle - Attach handler
  $("#modeSwitch").on("change", handleModeSwitch);

  // Mode Switching (backup buttons if any)
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
    const newThresholdOn = parseFloat($("#tempOn").val());
    const newThresholdOff = parseFloat($("#tempOff").val());

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

          $("#tempOn").val(thresholdOn);
          $("#tempOff").val(thresholdOff);

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

  // === HELPER FUNCTIONS ===

  // Toast notification helpers
  function showSuccessToast(message) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    } else {
      console.log("✅ " + message);
    }
  }

  function showErrorToast(message) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    } else {
      console.error("❌ " + message);
    }
  }

  function showWarningToast(message) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "warning",
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    } else {
      console.warn("⚠️ " + message);
    }
  }

  function handleAjaxError(xhr) {
    console.error("❌ AJAX Error:", xhr.status, xhr.statusText);
    showErrorToast("Gagal menghubungi server. Coba lagi.");
  }

  // Initial load
  console.log("🎯 Fan Control Page Initialized");
});
