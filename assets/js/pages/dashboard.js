$(function () {
  "use strict";

  // Clear stale blacklist data on page load
  if (typeof clearBlacklist === "function") {
    clearBlacklist();
  }

  // MQTT Configuration (from window.mqttConfig)
  const broker = window.mqttConfig.broker;
  const mqttUser = window.mqttConfig.username;
  const mqttPass = window.mqttConfig.password;
  const serial = window.mqttConfig.serial;
  const topicRoot = `smarthome/${serial}`;
  const statusTopic = `smarthome/status/${serial}`;
  const mqttProtocol = window.mqttConfig.protocol;

  // Variable untuk tracking
  let isESP32Online = false;
  let esp32HeartbeatTimer = null;
  const HEARTBEAT_TIMEOUT = 10000; // Naikkan ke 10 detik agar tidak flicker

  // === 1. INITIALIZE CHART.JS ===
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
        legend: { display: true, position: "top" },
        tooltip: { mode: "index", intersect: false },
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { display: true, color: "rgba(0, 0, 0, 0.05)" },
        },
        x: { grid: { display: false } },
      },
      interaction: { mode: "nearest", axis: "x", intersect: false },
    },
  });

  // === 2. AJAX POLLING FUNCTIONS ===
  function updateDashboardStats() {
    $.ajax({
      url: "api/dashboard_stats.php",
      method: "GET",
      dataType: "json",
      success: function (data) {
        $("#total-cards").text(data.cards);
        $("#total-logs").text(data.logs_today);
      },
      error: function (xhr) {
        /* Silent error */
      },
    });
  }

  function loadLastRFIDAccess() {
    $.get(
      "api/rfid_crud.php?action=getlogs&limit=1",
      function (res) {
        if (res.success && res.data && res.data.length > 0) {
          const log = res.data[0];
          $("#last_rfid").html(
            `<code class="text-dark" style="font-size: 1.3rem; font-weight: bold;">${log.uid}</code>`
          );
          $("#last_rfid_name").html(
            `<strong>${log.name || "Tidak Dikenal"}</strong>`
          );
          $("#last_rfid_time").text(log.access_time);

          const statusElem = $("#last_rfid_status");
          statusElem.removeClass("badge-danger badge-secondary badge-success");

          if (log.status === "granted") {
            statusElem
              .addClass("badge-success")
              .html('<i class="fas fa-check-circle"></i> Akses Diterima');
          } else {
            statusElem
              .addClass("badge-danger")
              .html('<i class="fas fa-times-circle"></i> Akses Ditolak');
          }
        }
      },
      "json"
    );
  }

  function loadLatestDHT() {
    $.get(
      "api/dht_log.php?action=latest",
      function (res) {
        if (res.success && res.data) {
          const temp = parseFloat(res.data.temperature);
          const hum = parseFloat(res.data.humidity);
          if (!isNaN(temp)) {
            $("#temperature").text(temp.toFixed(1));
            updateTempStatus(temp);
          }
          if (!isNaN(hum)) {
            $("#humidity").text(hum.toFixed(1));
            updateHumStatus(hum);
          }
        }
      },
      "json"
    );
  }

  function loadLatestFanStatus() {
    $.get(
      "api/kipas_crud.php?action=get_latest_status",
      function (res) {
        if (res.success && res.data) {
          updateFanUI(res.data.status === "on", res.data.mode === "auto");
        }
      },
      "json"
    );
  }

  // === 3. MQTT CONNECTION & HANDLERS ===
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

    client.subscribe(`${topicRoot}/#`);
    client.subscribe(statusTopic);

    // Status awal: Checking
    updateESP32Status("checking");
    updateFanStatus("checking");

    // Timeout Check awal (jika tidak ada respon sama sekali dalam 5 detik)
    setTimeout(() => {
      const currentStatus = $("#esp_status").text().trim();
      if (currentStatus.toLowerCase().includes("checking")) {
        updateESP32Status("offline");
        updateFanStatus("offline");
      }
    }, 5000);
  });

  client.on("offline", () => {
    updateESP32Status("offline");
    updateFanStatus("offline");
  });

  // ✅ FIX UTAMA DI SINI: Tambahkan parameter 'packet'
  client.on("message", (topic, message, packet) => {
    const msg = message.toString();

    // 1. Cek Topik Status Khusus (Prioritas Tertinggi)
    // Ini menangani LWT (Last Will) dari ESP32
    if (topic === statusTopic) {
      handleESP32Status(msg);
      return; // Jangan lanjut ke logika heartbeat biasa
    }

    // 2. Logika Heartbeat (Hanya untuk pesan BARU/LIVE)
    // packet.retain = true berarti pesan lama yang disimpan broker.
    // Kita HANYA reset heartbeat jika packet.retain = false.
    if (!packet.retain) {
      resetHeartbeat();
    } else {
      console.log(`⚠️ Ignored retained message on ${topic}: ${msg}`);
    }

    // 3. Routing Pesan ke Handler
    if (topic === `${topicRoot}/pintu/status`) handleDoorStatus(msg);
    if (topic === `${topicRoot}/dht/temperature`) handleTemperature(msg);
    if (topic === `${topicRoot}/dht/humidity`) handleHumidity(msg);
    if (topic === `${topicRoot}/rfid/access`) handleRFIDAccess(msg);

    // Handler Kipas
    if (topic === `${topicRoot}/kipas/status`) {
      const parts = msg.split(",");
      const fanStatus = parts[0] ? parts[0].toLowerCase().trim() : "";
      const fanMode = parts[1] ? parts[1].toLowerCase().trim() : "";

      if (fanStatus) {
        updateFanUI(fanStatus === "on", fanMode === "auto");
        // Jika pesan ini live, tandai online
        if (!packet.retain) resetHeartbeat();
      }
    }
  });

  // === HANDLER FUNCTIONS ===

  function handleESP32Status(msg) {
    console.log("Status Topic Received:", msg);
    if (msg === "online") {
      updateESP32Status("online");
      updateFanStatus("online");
      // Minta update status relay terbaru saat online
      client.publish(`${topicRoot}/relay/request_status`, "1");
    } else {
      updateESP32Status("offline");
      updateFanStatus("offline");
    }
  }

  function resetHeartbeat() {
    if (esp32HeartbeatTimer) clearTimeout(esp32HeartbeatTimer);

    // Jika sebelumnya offline/checking, ubah jadi online sekarang
    if (!isESP32Online) {
      isESP32Online = true;
      updateESP32Status("online");
      updateFanStatus("online");
    }

    // Set timer mati otomatis jika tidak ada pesan baru selama 10 detik
    esp32HeartbeatTimer = setTimeout(() => {
      console.log("💔 Heartbeat timeout - Device silent for too long");
      updateESP32Status("offline");
      updateFanStatus("offline");
      isESP32Online = false;
    }, HEARTBEAT_TIMEOUT);
  }

  function updateESP32Status(status) {
    const statusBox = $("#esp_status");
    const statusCard = statusBox.closest(".small-box");

    if (status === "online") {
      statusBox.text("Online");
      statusCard.removeClass("bg-danger bg-warning").addClass("bg-info");
      isESP32Online = true;
    } else if (status === "offline") {
      statusBox.text("Offline");
      statusCard.removeClass("bg-info bg-warning").addClass("bg-danger");
      $("#esp_connection_time").text("Terputus...");
      isESP32Online = false;
    } else {
      statusBox.html('<i class="fas fa-spinner fa-spin"></i> Checking...');
      statusCard.removeClass("bg-danger bg-info").addClass("bg-warning");
    }
  }

  function updateFanStatus(status) {
    if (status === "offline") {
      $("#fan_status_text").html(
        '<i class="fas fa-exclamation-triangle"></i> Offline'
      );
      $("#fan_card")
        .removeClass("bg-success bg-danger")
        .addClass("bg-secondary");
      $("#fan_icon_dashboard").removeClass("fan-spinning");
    }
  }

  function updateFanUI(isOn, isAuto) {
    $("#fan_status_text").text(isOn ? "ON" : "OFF");
    $("#fan_card")
      .removeClass("bg-success bg-danger bg-warning bg-secondary")
      .addClass(isOn ? "bg-success" : "bg-danger");

    if (isOn) $("#fan_icon_dashboard").addClass("fan-spinning");
    else $("#fan_icon_dashboard").removeClass("fan-spinning");

    $("#fan_mode_text").html(
      `<i class="fas fa-${isAuto ? "magic" : "hand-pointer"}"></i> Mode: ${
        isAuto ? "Auto" : "Manual"
      }`
    );
  }

  // --- Handlers UI Lainnya ---
  function handleDoorStatus(msg) {
    const isOpen = msg === "terbuka";
    $("#door_status").text(isOpen ? "Terbuka" : "Tertutup");
    $("#door_status")
      .closest(".small-box")
      .removeClass("bg-success bg-warning")
      .addClass(isOpen ? "bg-warning" : "bg-success");
    $("#door_icon")
      .removeClass("fa-door-closed fa-door-open")
      .addClass(isOpen ? "fa-door-open" : "fa-door-closed");
    $("#door_last_update").html(
      '<i class="fas fa-clock"></i> Update: ' +
        new Date().toLocaleTimeString("id-ID")
    );
  }

  function handleTemperature(msg) {
    const temp = parseFloat(msg);
    if (!isNaN(temp)) {
      $("#temperature").text(temp.toFixed(1));
      updateTempStatus(temp);
      addChartData("temperature", temp);
    }
  }

  function handleHumidity(msg) {
    const hum = parseFloat(msg);
    if (!isNaN(hum)) {
      $("#humidity").text(hum.toFixed(1));
      updateHumStatus(hum);
      addChartData("humidity", hum);
    }
  }

  function handleRFIDAccess(msg) {
    try {
      const data = JSON.parse(msg);
      $("#last_rfid").text(data.uid || "");
      setTimeout(loadLastRFIDAccess, 500); // Reload detail dari DB
      setTimeout(updateDashboardStats, 500);
    } catch (e) {}
  }

  function updateTempStatus(temp) {
    if (temp > 30) $("#temp_status").html('<i class="fas fa-fire"></i> Panas');
    else if (temp < 20)
      $("#temp_status").html('<i class="fas fa-snowflake"></i> Dingin');
    else
      $("#temp_status").html('<i class="fas fa-temperature-half"></i> Normal');
  }

  function updateHumStatus(hum) {
    if (hum > 70)
      $("#humidity_status").html('<i class="fas fa-tint"></i> Lembab');
    else if (hum < 30)
      $("#humidity_status").html('<i class="fas fa-burn"></i> Kering');
    else $("#humidity_status").html('<i class="fas fa-droplet"></i> Normal');
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
    const len = dhtChart.data.labels.length;
    if (len === 0 || dhtChart.data.labels[len - 1] !== now) {
      dhtChart.data.labels.push(now);
      dhtChart.data.datasets[0].data.push(
        type === "temperature" ? value : null
      );
      dhtChart.data.datasets[1].data.push(type === "humidity" ? value : null);
    } else {
      const lastIdx = len - 1;
      if (type === "temperature")
        dhtChart.data.datasets[0].data[lastIdx] = value;
      else dhtChart.data.datasets[1].data[lastIdx] = value;
    }
    dhtChart.update("none");
  }

  // === 4. GLOBAL INITIALIZATION ===
  window.loadLastRFID = function () {
    loadLastRFIDAccess();
    updateDashboardStats();
  };

  // Load Data Pertama Kali
  loadLastRFIDAccess();
  loadLatestDHT();
  loadLatestFanStatus();
  updateDashboardStats();

  // Auto-Refresh Interval
  setInterval(function () {
    loadLastRFIDAccess();
    updateDashboardStats();
  }, 3000);

  // Resize Chart Handler
  $('[data-card-widget="collapse"]').on("click", function () {
    setTimeout(() => {
      if (dhtChart) dhtChart.resize();
    }, 350);
  });
  $(window).on("resize", function () {
    if (dhtChart) dhtChart.resize();
  });
});
