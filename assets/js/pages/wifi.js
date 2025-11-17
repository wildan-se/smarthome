/**
 * WiFi Configuration Page JavaScript
 * Handle MQTT communication for ESP32 WiFi management
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

  // MQTT Configuration
  const broker = window.mqttConfig.broker;
  const mqttUser = window.mqttConfig.username;
  const mqttPass = window.mqttConfig.password;
  const serial = window.mqttConfig.serial;
  const topicRoot = `smarthome/${serial}`;
  const mqttProtocol = window.mqttConfig.protocol;

  // State management
  let isWaitingForResponse = false;
  let responseTimeout = null;
  let lastStatus = null;
  const RESPONSE_TIMEOUT_MS = 30000; // 30 seconds

  // Initialize MQTT Client
  const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
    username: mqttUser,
    password: mqttPass,
    clientId: "wifi-" + Math.random().toString(16).substr(2, 8),
  });

  // === MQTT CONNECTION HANDLERS ===
  client.on("connect", function () {
    console.log("✅ MQTT Connected - WiFi Config Page");
    client.subscribe(`${topicRoot}/wifi/status`);

    // Request initial status
    setTimeout(function () {
      requestWiFiStatus();
    }, 500);
  });

  client.on("offline", function () {
    console.log("⚠️ MQTT Offline");
    updateConnectionBadge("disconnected");
  });

  client.on("close", function () {
    console.log("🔌 MQTT Connection Closed");
    updateConnectionBadge("disconnected");
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

    if (topic.endsWith("/wifi/status")) {
      handleWiFiStatus(messageStr);
    }
  });

  // === HANDLE WIFI STATUS MESSAGE ===
  function handleWiFiStatus(messageStr) {
    let data = {};
    try {
      data = JSON.parse(messageStr);
    } catch (e) {
      console.error("❌ Parse error:", e);
      return;
    }

    console.log("📊 WiFi Status Data:", data);

    // Clear timeout if waiting for response
    if (isWaitingForResponse && responseTimeout) {
      clearTimeout(responseTimeout);
      isWaitingForResponse = false;
    }

    // Handle different status types
    if (data.status === "connected") {
      updateWiFiInfo(data);
      updateConnectionBadge("connected");

      // Show success if this was after a config change
      if (lastStatus === "restarting") {
        Alert.success(
          "#wifiAlert",
          `✅ ESP32 berhasil connect ke WiFi <strong>${data.ssid}</strong>!<br>` +
            `IP Address: <code>${data.ip}</code>`
        );
      }

      lastStatus = "connected";
    } else if (data.status === "restarting") {
      updateConnectionBadge("restarting");
      lastStatus = "restarting";

      // Set timeout for connection
      isWaitingForResponse = true;
      responseTimeout = setTimeout(function () {
        if (lastStatus === "restarting") {
          updateConnectionBadge("disconnected");
          Alert.error(
            "#wifiAlert",
            "❌ <strong>Connection Timeout!</strong><br>" +
              "ESP32 tidak berhasil connect setelah 30 detik.<br>" +
              "Kemungkinan SSID atau Password salah. Silakan cek konfigurasi WiFi Anda."
          );
          lastStatus = "timeout";
        }
      }, RESPONSE_TIMEOUT_MS);
    } else if (data.status === "failed") {
      updateConnectionBadge("disconnected");

      let errorMsg = "Koneksi WiFi gagal";
      if (data.error === "wrong_password") {
        errorMsg = "Password WiFi salah";
      } else if (data.error === "ssid_not_found") {
        errorMsg = "SSID tidak ditemukan";
      } else if (data.error === "timeout") {
        errorMsg = "Timeout saat mencoba connect";
      }

      Alert.error(
        "#wifiAlert",
        `❌ <strong>${errorMsg}!</strong><br>` +
          `ESP32 gagal connect ke WiFi. Silakan periksa konfigurasi Anda.`
      );

      lastStatus = "failed";
    } else if (data.status === "disconnected") {
      updateConnectionBadge("disconnected");
      console.log("⚠️ ESP32 terputus dari WiFi");
      lastStatus = "disconnected";
    }
  }

  // === UPDATE WIFI INFO DISPLAY ===
  function updateWiFiInfo(data) {
    console.log("🔄 Updating WiFi info display:", data);

    // Update SSID
    if (data.ssid) {
      $("#currentSSID").html(escapeHtml(data.ssid));
    }

    // Update IP Address
    if (data.ip) {
      $("#currentIP").html(`<code>${escapeHtml(data.ip)}</code>`);
    }

    // Update RSSI (Signal Strength)
    if (data.rssi !== undefined && data.rssi !== null) {
      const rssi = parseInt(data.rssi);
      let signalClass = "poor";
      let signalText = "Lemah";

      if (rssi >= -50) {
        signalClass = "excellent";
        signalText = "Sangat Baik";
      } else if (rssi >= -60) {
        signalClass = "good";
        signalText = "Baik";
      } else if (rssi >= -70) {
        signalClass = "fair";
        signalText = "Cukup";
      }

      $("#currentRSSI").removeClass("excellent good fair poor");
      $("#currentRSSI").addClass(signalClass);
      $("#currentRSSI").html(
        `<i class="fas fa-signal"></i> <span>${rssi} dBm (${signalText})</span>`
      );
    }
  }

  // === UPDATE CONNECTION BADGE ===
  function updateConnectionBadge(status) {
    const $statusBadge = $("#connectionStatus").parent();
    const $statusText = $("#connectionStatus");

    $statusBadge.removeClass("connected disconnected restarting");

    if (status === "connected") {
      $statusBadge.addClass("connected");
      $statusText.html('<i class="fas fa-check-circle"></i> Terhubung');
    } else if (status === "disconnected") {
      $statusBadge.addClass("disconnected");
      $statusText.html('<i class="fas fa-times-circle"></i> Terputus');
    } else if (status === "restarting") {
      $statusBadge.addClass("restarting");
      $statusText.html('<i class="fas fa-sync-alt fa-spin"></i> Restarting...');
    }
  }

  // === REQUEST WIFI STATUS FROM ESP32 ===
  function requestWiFiStatus() {
    console.log("📤 Requesting WiFi status from ESP32...");
    client.publish(`${topicRoot}/wifi/get_status`, "request");

    // Show loading skeleton
    $("#currentSSID").html(
      '<div class="skeleton" style="width: 150px; height: 20px;"></div>'
    );
    $("#currentIP").html(
      '<div class="skeleton" style="width: 120px; height: 20px;"></div>'
    );
    $("#currentRSSI").html(
      '<div class="skeleton" style="width: 100px; height: 20px;"></div>'
    );

    // Set timeout for no response (5 seconds)
    setTimeout(function () {
      // Check if still showing skeleton (no response received)
      if ($("#currentSSID").find(".skeleton").length > 0) {
        console.warn("⚠️ No response from ESP32 after 5 seconds");
        $("#currentSSID").html(
          '<span class="text-muted"><i class="fas fa-question-circle"></i> Tidak ada respons</span>'
        );
        $("#currentIP").html('<span class="text-muted">-</span>');
        $("#currentRSSI").html(
          '<span class="signal-badge poor"><i class="fas fa-signal-slash"></i> -</span>'
        );
        updateConnectionBadge("disconnected");

        console.warn("⚠️ ESP32 tidak merespons setelah 5 detik");
      }
    }, 5000);
  }

  // === BUTTON REFRESH CLICK ===
  $("#btnRefreshStatus").on("click", function () {
    const $btn = $(this);
    const $icon = $btn.find("i");

    // Rotate animation
    $icon.addClass("fa-spin");

    requestWiFiStatus();

    setTimeout(function () {
      $icon.removeClass("fa-spin");
    }, 1000);
  });

  // === PASSWORD TOGGLE ===
  $("#togglePassword").on("click", function () {
    const $passwordInput = $("#inputPassword");
    const $icon = $(this).find("i");
    const type = $passwordInput.attr("type");

    if (type === "password") {
      $passwordInput.attr("type", "text");
      $icon.removeClass("fa-eye").addClass("fa-eye-slash");
    } else {
      $passwordInput.attr("type", "password");
      $icon.removeClass("fa-eye-slash").addClass("fa-eye");
    }
  });

  // === FORM SUBMIT - SET WIFI CONFIG ===
  $("#formSetWiFi").on("submit", function (e) {
    e.preventDefault();

    const ssid = $("#inputSSID").val().trim();
    const password = $("#inputPassword").val();

    // Validation
    if (!ssid || ssid.length === 0) {
      Alert.error("#wifiAlert", "SSID tidak boleh kosong!");
      return;
    }

    if (!password || password.length === 0) {
      Alert.error("#wifiAlert", "Password tidak boleh kosong!");
      return;
    }

    if (ssid.length > 32) {
      Alert.error("#wifiAlert", "SSID maksimal 32 karakter!");
      return;
    }

    if (password.length > 64) {
      Alert.error("#wifiAlert", "Password maksimal 64 karakter!");
      return;
    }

    // Show confirmation dialog (sama style seperti RFID delete)
    Swal.fire({
      title: "Simpan Konfigurasi WiFi?",
      html: `
        <div style="text-align: left; padding: 15px;">
          <p style="font-size: 1.1rem; margin-bottom: 15px;">
            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 1.3rem;"></i>
            <strong>ESP32 akan restart otomatis!</strong>
          </p>
          <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
            <strong><i class="fas fa-wifi"></i> SSID:</strong><br>
            <code style="font-size: 1.1rem; color: #667eea;">${escapeHtml(
              ssid
            )}</code>
          </div>
          <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
            <strong><i class="fas fa-lock"></i> Password:</strong><br>
            <code style="font-size: 1.1rem; color: #667eea;">••••••••</code>
          </div>
          <p style="margin-top: 15px; font-size: 0.95rem; color: #6c757d;">
            <i class="fas fa-clock"></i> Proses reconnect membutuhkan waktu ~30 detik
          </p>
        </div>
      `,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: '<i class="fas fa-save"></i> Ya, Simpan & Restart',
      cancelButtonText: '<i class="fas fa-times"></i> Batal',
      confirmButtonColor: "#667eea",
      cancelButtonColor: "#6c757d",
      reverseButtons: true,
      customClass: {
        popup: "swal2-square-popup",
        confirmButton: "btn btn-primary btn-square",
        cancelButton: "btn btn-secondary btn-square",
      },
      buttonsStyling: false,
      width: "450px",
    }).then((result) => {
      if (result.isConfirmed) {
        // Send WiFi config to ESP32 via MQTT
        setWiFiConfig(ssid, password);
      }
    });
  });

  // === SEND WIFI CONFIG TO ESP32 ===
  function setWiFiConfig(ssid, password) {
    console.log(`📤 Sending WiFi config to ESP32...`);
    console.log(`SSID: ${ssid}`);

    const payload = JSON.stringify({
      ssid: ssid,
      password: password,
    });

    // Publish to ESP32
    const published = client.publish(
      `${topicRoot}/wifi/set_config`,
      payload,
      { qos: 1 },
      function (err) {
        if (err) {
          console.error("❌ Publish failed:", err);
          Alert.error(
            "#wifiAlert",
            "Gagal mengirim konfigurasi ke ESP32. Coba lagi!"
          );
          return;
        }

        console.log("✅ WiFi config sent successfully");

        // Show loading alert
        Alert.loading(
          "#wifiAlert",
          '<i class="fas fa-sync-alt fa-spin"></i> <strong>Menyimpan konfigurasi...</strong><br>' +
            "ESP32 sedang restart dan mencoba connect ke WiFi baru.<br>" +
            'Harap tunggu ~30 detik... <div class="progress mt-2"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div></div>'
        );

        // Update status badge
        updateConnectionBadge("restarting");
        lastStatus = "restarting";

        // Clear form
        $("#formSetWiFi")[0].reset();

        // Set timeout for connection response
        isWaitingForResponse = true;
        responseTimeout = setTimeout(function () {
          if (lastStatus === "restarting") {
            updateConnectionBadge("disconnected");
            Alert.error(
              "#wifiAlert",
              "❌ <strong>Connection Timeout!</strong><br>" +
                "ESP32 tidak berhasil connect setelah 30 detik.<br>" +
                "Kemungkinan SSID atau Password salah.<br>" +
                '<small class="text-muted">ESP32 akan tetap mencoba connect. Cek konfigurasi WiFi atau upload ulang sketch jika diperlukan.</small>'
            );
            lastStatus = "timeout";
          }
        }, RESPONSE_TIMEOUT_MS);
      }
    );
  }

  // === UTILITY FUNCTION: ESCAPE HTML ===
  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }

  // === INITIALIZATION ===
  console.log("🚀 WiFi Configuration page initialized");
});
