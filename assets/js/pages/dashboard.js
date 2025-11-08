/**
 * Dashboard Page JavaScript
 * Handle MQTT, real-time updates, and chart
 */

$(function () {
  "use strict";

  // Clear stale blacklist data on page load
  console.log("🧹 Clearing stale blacklist data on page load...");
  clearBlacklist();

  // MQTT Configuration (from window.mqttConfig)
  const broker = window.mqttConfig.broker;
  const mqttUser = window.mqttConfig.username;
  const mqttPass = window.mqttConfig.password;
  const serial = window.mqttConfig.serial;
  const topicRoot = `smarthome/${serial}`;
  const statusTopic = `smarthome/status/${serial}`;
  const mqttProtocol = window.mqttConfig.protocol;

  // Variable untuk tracking
  let controlSource = "rfid";
  let isESP32Online = false;
  let esp32HeartbeatTimer = null;
  const HEARTBEAT_TIMEOUT = 10000;

  // Initialize Chart.js
  const ctx = document.getElementById("dhtChart").getContext("2d");
  const dhtChart = new Chart(ctx, {
    type: "line",
    data: {
      labels: [],
      datasets: [
        {
          label: "Suhu (°C)",
          data: [],
          borderColor: "rgb(255, 193, 7)",
          backgroundColor: "rgba(255, 193, 7, 0.1)",
          borderWidth: 3,
          tension: 0.4,
          fill: true,
        },
        {
          label: "Kelembapan (%)",
          data: [],
          borderColor: "rgb(23, 162, 184)",
          backgroundColor: "rgba(23, 162, 184, 0.1)",
          borderWidth: 3,
          tension: 0.4,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          position: "top",
        },
        tooltip: {
          mode: "index",
          intersect: false,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            display: true,
            color: "rgba(0, 0, 0, 0.05)",
          },
        },
        x: {
          grid: {
            display: false,
          },
        },
      },
      interaction: {
        mode: "nearest",
        axis: "x",
        intersect: false,
      },
    },
  });

  // === MQTT CONNECTION ===
  const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
    username: mqttUser,
    password: mqttPass,
    clientId: "dashboard-" + Math.random().toString(16).substr(2, 8),
  });

  client.on("connect", () => {
    console.log("✅ MQTT Dashboard Connected!");
    $("#esp_connection_time").text(
      "Terhubung pada " + new Date().toLocaleTimeString("id-ID")
    );

    // Subscribe to all topics
    client.subscribe(`${topicRoot}/#`);
    client.subscribe(statusTopic);

    // Request status
    client.publish(`${topicRoot}/system/ping`, "request_status");

    // Set initial checking status
    updateESP32Status("checking");
    updateFanStatus("checking");

    // Timeout check
    setTimeout(() => {
      const currentStatus = $("#esp_status").text().trim();
      if (currentStatus.includes("Checking")) {
        updateESP32Status("offline");
        updateFanStatus("offline");
      }
    }, 5000);
  });

  client.on("offline", () => {
    console.log("❌ MQTT Dashboard Offline");
    $("#esp_connection_time").text("Terputus...");
    updateESP32Status("offline");
    updateFanStatus("offline");

    // Clear blacklist
    clearBlacklist();
  });

  client.on("close", () => {
    console.log("🔌 MQTT Connection Closed");
    clearBlacklist();
  });

  client.on("error", (error) => {
    console.error("❌ MQTT Error:", error);
  });

  // === MQTT MESSAGE HANDLER ===
  client.on("message", (topic, message) => {
    const msg = message.toString();
    console.log("📩 MQTT:", topic, "=>", msg);

    // Reset heartbeat on any message
    resetHeartbeat();

    // ESP32 Status
    if (topic === statusTopic) {
      handleESP32Status(msg);
    }

    // Control Source
    if (topic === `${topicRoot}/kontrol/source`) {
      controlSource = msg;
      console.log("🎛️ Control Source:", controlSource);
      setTimeout(() => {
        controlSource = "rfid";
      }, 2000);
    }

    // Door Status
    if (topic === `${topicRoot}/pintu/status`) {
      handleDoorStatus(msg);
    }

    // Temperature
    if (topic === `${topicRoot}/dht/temperature`) {
      handleTemperature(msg);
    }

    // Humidity
    if (topic === `${topicRoot}/dht/humidity`) {
      handleHumidity(msg);
    }

    // Fan Status
    if (topic === `${topicRoot}/kipas/status`) {
      handleFanStatus(msg);
    }

    // Fan Mode
    if (topic === `${topicRoot}/kipas/mode`) {
      handleFanMode(msg);
    }

    // RFID Access
    if (topic === `${topicRoot}/rfid/access`) {
      handleRFIDAccess(msg);
    }

    // RFID Info
    if (topic === `${topicRoot}/rfid/info`) {
      handleRFIDInfo(msg);
    }

    // Relay Status
    if (topic === `${topicRoot}/relay/status`) {
      handleRelayStatus(msg);
    }

    // Fan Mode Status
    if (topic === `${topicRoot}/fan/mode/status`) {
      const mode = msg.toLowerCase();
      const isAuto = mode === "auto";
      $("#fan_mode_display").text(mode.toUpperCase());
      $("#fan_mode_text").html(
        '<i class="fas fa-' +
          (isAuto ? "magic" : "hand-pointer") +
          '"></i> Mode: ' +
          (isAuto ? "Auto" : "Manual")
      );
    }

    // Fan Threshold Status
    if (topic === `${topicRoot}/fan/threshold/status`) {
      const parts = msg.split(" ");
      if (parts.length >= 2) {
        $("#threshold_display").text(msg);
      }
    }
  });

  // === HANDLER FUNCTIONS ===

  function handleESP32Status(msg) {
    if (msg === "online") {
      updateESP32Status("online");
      updateFanStatus("online");
      client.publish(`${topicRoot}/relay/request_status`, "1");
    } else {
      updateESP32Status("offline");
      updateFanStatus("offline");
    }
  }

  function handleDoorStatus(msg) {
    const isOpen = msg === "terbuka";
    const updateTime = new Date().toLocaleTimeString("id-ID");

    $("#door_status").text(isOpen ? "Terbuka" : "Tertutup");
    $("#door_status")
      .closest(".small-box")
      .removeClass("bg-success bg-warning")
      .addClass(isOpen ? "bg-warning" : "bg-success");

    $("#door_icon")
      .removeClass("fa-door-closed fa-door-open")
      .addClass(isOpen ? "fa-door-open" : "fa-door-closed");

    $("#door_last_update").html(
      '<i class="fas fa-door-' +
        (isOpen ? "open" : "closed") +
        '"></i> Update: ' +
        updateTime
    );

    sendLog("door", { status: msg });
    markESP32Online();
  }

  function handleTemperature(msg) {
    const temp = parseFloat(msg);
    if (!isNaN(temp) && temp > 0 && temp < 100) {
      $("#temperature").text(temp.toFixed(1));
      addChartData("temperature", temp);
      markESP32Online();

      if (temp > 30) {
        $("#temp_status").html('<i class="fas fa-fire"></i> Panas');
      } else if (temp < 20) {
        $("#temp_status").html('<i class="fas fa-snowflake"></i> Dingin');
      } else {
        $("#temp_status").html(
          '<i class="fas fa-thermometer-half"></i> Normal'
        );
      }

      window.lastDHTData = window.lastDHTData || {};
      window.lastDHTData.temperature = temp;
      window.lastDHTData.tempTime = Date.now();

      if (
        window.lastDHTData.humidity &&
        Date.now() - window.lastDHTData.humTime < 2000
      ) {
        sendLog("dht", {
          temperature: window.lastDHTData.temperature,
          humidity: window.lastDHTData.humidity,
        });
      }
    }
  }

  function handleHumidity(msg) {
    const hum = parseFloat(msg);
    if (!isNaN(hum) && hum > 0 && hum <= 100) {
      $("#humidity").text(hum.toFixed(1));
      addChartData("humidity", hum);
      markESP32Online();

      if (hum > 70) {
        $("#hum_status").html('<i class="fas fa-tint"></i> Lembab');
      } else if (hum < 30) {
        $("#hum_status").html('<i class="fas fa-burn"></i> Kering');
      } else {
        $("#hum_status").html('<i class="fas fa-droplet"></i> Normal');
      }

      window.lastDHTData = window.lastDHTData || {};
      window.lastDHTData.humidity = hum;
      window.lastDHTData.humTime = Date.now();

      if (
        window.lastDHTData.temperature &&
        Date.now() - window.lastDHTData.tempTime < 2000
      ) {
        sendLog("dht", {
          temperature: window.lastDHTData.temperature,
          humidity: window.lastDHTData.humidity,
        });
      }
    }
  }

  function handleFanStatus(msg) {
    const fanStatus = msg.toLowerCase();
    const isOn = fanStatus === "on";

    $("#fan_status_text").text(isOn ? "ON" : "OFF");
    $("#fan_card")
      .removeClass("bg-success bg-danger bg-purple bg-warning bg-secondary")
      .addClass(isOn ? "bg-success" : "bg-danger");

    const fanIcon = $("#fan_icon_dashboard");
    if (isOn) {
      fanIcon.addClass("fan-spinning");
    } else {
      fanIcon.removeClass("fan-spinning");
    }

    markESP32Online();
  }

  function handleFanMode(msg) {
    const mode = msg.toUpperCase();
    $("#fan_mode_text").text("Mode: " + mode);
  }

  function handleRFIDAccess(msg) {
    let data = {};
    try {
      data = JSON.parse(msg);
    } catch (e) {
      console.error("Parse error:", e);
      return;
    }

    const status = data.status || "-";
    const uid = data.uid || "";

    if (uid === "MANUAL_CONTROL") {
      console.log("⚠️ Skipping manual control from RFID display");
      return;
    }

    const pendingUID = localStorage.getItem("lastAddedUID");
    const addTime = localStorage.getItem("lastAddTime");
    if (pendingUID && pendingUID === uid && addTime) {
      const timeDiff = Date.now() - parseInt(addTime);

      if (timeDiff > 0 && timeDiff < 3000) {
        console.log(
          "⚠️ BLOCKED: Newly added card from dashboard display:",
          uid,
          "Time diff:",
          timeDiff,
          "ms"
        );
        return;
      } else if (timeDiff >= 3000) {
        console.log(
          "⏰ Time expired for dashboard blacklist:",
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

    markESP32Online();
    $("#last_rfid").text(uid);

    if (status === "granted" || status === "denied") {
      console.log(
        "✅ Dashboard: Physical tap detected for:",
        uid,
        "Status:",
        status
      );

      sendLog("rfid", { uid: uid, status: status });

      setTimeout(function () {
        console.log(
          "🔄 Reloading last RFID access from database (with name)..."
        );
        loadLastRFIDAccess();
      }, 500);
    } else {
      console.log("⚠️ Dashboard: No valid status. Status:", status);
    }
  }

  function handleRFIDInfo(msg) {
    let data = {};
    try {
      data = JSON.parse(msg);
    } catch (e) {
      console.error("Parse error:", e);
      return;
    }

    const uid = data.uid || "";
    if (uid === "MANUAL_CONTROL") {
      console.log("⚠️ Skipping manual control from RFID info");
      return;
    }

    const action = data.action;
    if (action === "add" && data.result === "ok" && uid) {
      localStorage.setItem("lastAddedUID", uid);
      localStorage.setItem("lastAddTime", Date.now().toString());
      console.log("✅ Blacklist set for:", uid);

      setTimeout(function () {
        const currentUID = localStorage.getItem("lastAddedUID");
        if (currentUID === uid) {
          clearBlacklist();
          console.log("🧹 Blacklist cleared for:", uid);
        }
      }, 3000);
    }

    markESP32Online();
    if (uid) {
      $("#last_rfid").text(uid);
    }
  }

  function handleRelayStatus(msg) {
    markESP32Online();

    const isOn = msg.toLowerCase().includes("on");
    const isAuto = msg.toLowerCase().includes("auto");

    $("#fan_status_text").text(isOn ? "ON" : "OFF");

    const fanCard = $("#fan_card");
    fanCard.removeClass(
      "bg-success bg-danger bg-purple bg-warning bg-secondary"
    );

    if (isOn) {
      fanCard.addClass("bg-success");
      $("#fan_icon_dashboard").addClass("fan-spinning");
    } else {
      fanCard.addClass("bg-danger");
      $("#fan_icon_dashboard").removeClass("fan-spinning");
    }

    $("#fan_mode_text").html(
      '<i class="fas fa-' +
        (isAuto ? "magic" : "hand-pointer") +
        '"></i> Mode: ' +
        (isAuto ? "Auto" : "Manual")
    );

    console.log(
      `✅ Kipas UI Updated: ${isOn ? "ON" : "OFF"} (${
        isAuto ? "Auto" : "Manual"
      })`
    );
  }

  // === UTILITY FUNCTIONS ===

  function resetHeartbeat() {
    if (esp32HeartbeatTimer) {
      clearTimeout(esp32HeartbeatTimer);
    }

    if (!isESP32Online) {
      isESP32Online = true;
      console.log("💓 Heartbeat detected - ESP32 Online");
    }

    esp32HeartbeatTimer = setTimeout(() => {
      console.log("💔 Heartbeat timeout - ESP32 dianggap Offline");
      updateESP32Status("offline");
      updateFanStatus("offline");
    }, HEARTBEAT_TIMEOUT);
  }

  function markESP32Online() {
    const currentStatus = $("#esp_status").text().trim();

    resetHeartbeat();

    if (currentStatus !== "Online") {
      console.log("✅ ESP32 detected online from data");
      updateESP32Status("online");

      if (!isESP32Online) {
        isESP32Online = true;
        updateFanStatus("online");
      }
    }
  }

  function updateESP32Status(status) {
    const statusBox = $("#esp_status");
    const statusCard = statusBox.closest(".small-box");

    if (status === "online") {
      statusBox.text("Online");
      statusCard.removeClass("bg-danger bg-warning").addClass("bg-info");
      $("#esp_connection_time").text(
        "Online sejak " + new Date().toLocaleTimeString("id-ID")
      );
      isESP32Online = true;
    } else if (status === "offline") {
      statusBox.text("Offline");
      statusCard.removeClass("bg-info bg-warning").addClass("bg-danger");
      $("#esp_connection_time").text(
        "Offline sejak " + new Date().toLocaleTimeString("id-ID")
      );
      isESP32Online = false;
    } else if (status === "checking") {
      statusBox.html('<i class="fas fa-spinner fa-spin"></i> Checking...');
      statusCard.removeClass("bg-danger bg-info").addClass("bg-warning");
    }
  }

  function updateFanStatus(status) {
    const fanBox = $("#fan_status_text");
    const fanCard = $("#fan_card");
    const fanIcon = $("#fan_icon_dashboard");

    if (status === "offline") {
      fanBox.html('<i class="fas fa-exclamation-triangle"></i> Offline');
      fanCard
        .removeClass("bg-success bg-purple bg-warning")
        .addClass("bg-secondary");
      fanIcon.removeClass("fan-spinning");
      $("#fan_mode_text").html(
        '<i class="fas fa-wifi-slash"></i> ESP32 Offline'
      );
    } else if (status === "online") {
      console.log("🟢 Kipas: ESP32 Online - waiting for relay status");
    } else if (status === "checking") {
      fanBox.html('<i class="fas fa-spinner fa-spin"></i> Checking...');
      fanCard
        .removeClass("bg-danger bg-info bg-success bg-secondary")
        .addClass("bg-warning");
    }
  }

  function addChartData(type, value) {
    const now = new Date().toLocaleTimeString("id-ID", {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });

    if (dhtChart.data.labels.length > 20) {
      dhtChart.data.labels.shift();
      dhtChart.data.datasets[0].data.shift();
      dhtChart.data.datasets[1].data.shift();
    }

    if (
      dhtChart.data.labels.length === 0 ||
      dhtChart.data.labels[dhtChart.data.labels.length - 1] !== now
    ) {
      dhtChart.data.labels.push(now);
      dhtChart.data.datasets[0].data.push(
        type === "temperature" ? value : null
      );
      dhtChart.data.datasets[1].data.push(type === "humidity" ? value : null);
    } else {
      const lastIndex = dhtChart.data.labels.length - 1;
      if (type === "temperature") {
        dhtChart.data.datasets[0].data[lastIndex] = value;
      } else if (type === "humidity") {
        dhtChart.data.datasets[1].data[lastIndex] = value;
      }
    }

    dhtChart.update("none");
  }

  function sendLog(type, data) {
    fetch("api/receive_data.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ type, data }),
    });
  }

  function loadLastRFIDAccess() {
    $.get(
      "api/rfid_crud.php?action=getlogs&limit=1",
      function (res) {
        if (res.success && res.data && res.data.length > 0) {
          const lastLog = res.data[0];
          const uid = lastLog.uid || "-";
          const name = lastLog.name || "Tidak Dikenal";
          const status = lastLog.status || "-";
          const time = lastLog.access_time || "-";

          console.log("✅ Loading last RFID access:", {
            uid,
            name,
            status,
            time,
          });

          $("#last_rfid").html(
            '<code class="text-dark" style="font-size: 1.3rem; font-weight: bold;">' +
              uid +
              "</code>"
          );
          $("#last_rfid_name").html("<strong>" + name + "</strong>");
          $("#last_rfid_time").text(time);

          const statusElem = $("#last_rfid_status");
          if (status === "granted") {
            statusElem
              .removeClass("badge-danger badge-secondary")
              .addClass("badge-success");
            statusElem.html(
              '<i class="fas fa-check-circle"></i> Akses Diterima'
            );
          } else if (status === "denied") {
            statusElem
              .removeClass("badge-success badge-secondary")
              .addClass("badge-danger");
            statusElem.html(
              '<i class="fas fa-times-circle"></i> Akses Ditolak'
            );
          } else {
            statusElem
              .removeClass("badge-success badge-danger")
              .addClass("badge-secondary");
            statusElem.html("Status Tidak Dikenal");
          }
        } else {
          console.log("ℹ️ No RFID access history found");
          $("#last_rfid").html('<code class="text-muted">-</code>');
          $("#last_rfid_name").html(
            '<em class="text-muted">Belum ada akses</em>'
          );
          $("#last_rfid_time").text("-");
          $("#last_rfid_status")
            .removeClass("badge-success badge-danger")
            .addClass("badge-secondary")
            .html("Belum Ada Data");
        }
      },
      "json"
    ).fail(function (xhr, status, error) {
      console.error("❌ Failed to load last RFID access:", error);
      $("#last_rfid").html('<code class="text-danger">Error</code>');
      $("#last_rfid_name").html(
        '<em class="text-danger">Gagal memuat data</em>'
      );
    });
  }

  // Make function available globally for refresh button
  window.loadLastRFID = loadLastRFIDAccess;

  // Initial load
  loadLastRFIDAccess();

  // Auto-refresh every 10 seconds
  setInterval(function () {
    console.log("🔄 Auto-refreshing RFID access data...");
    loadLastRFIDAccess();
  }, 10000);

  // Initialize AdminLTE Card Widget for collapse/maximize functionality
  console.log("🎯 Dashboard Page Initialized - Activating card widgets");

  // ✅ FIX: Handle chart resize when card is collapsed/expanded or maximized
  // AdminLTE CardWidget triggers events when card state changes
  $('[data-card-widget="collapse"]').on("click", function () {
    setTimeout(function () {
      if (dhtChart) {
        dhtChart.resize();
        console.log("📊 Chart resized after collapse/expand");
      }
    }, 350); // Wait for animation to complete (AdminLTE default: 300ms)
  });

  $('[data-card-widget="maximize"]').on("click", function () {
    setTimeout(function () {
      const chartWrapper = document.getElementById("chartWrapper");
      const cardBody = chartWrapper.closest(".card-body");
      const isMaximized = cardBody
        .closest(".card")
        .classList.contains("maximized-card");

      if (isMaximized) {
        // Mode maximize - adjusted untuk sticky header & footer
        chartWrapper.style.height = "calc(100vh - 240px)";
        console.log("📈 Chart maximized - height adjusted for sticky header/footer");
      } else {
        // Mode normal - kembalikan ke height default
        chartWrapper.style.height = "400px";
        console.log("📉 Chart restored - height set to 400px");
      }

      if (dhtChart) {
        dhtChart.resize();
        console.log("📊 Chart resized after maximize/restore");
      }
    }, 350);
  });

  // ✅ Also handle window resize event
  $(window).on("resize", function () {
    if (dhtChart) {
      dhtChart.resize();
    }
  });
});
