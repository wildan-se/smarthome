/**
 * Dashboard Page JavaScript
 * Handle MQTT, real-time updates (AJAX Polling), and Charts
 */

console.log("🚀 Dashboard.js loaded - Version:", new Date().toISOString());

$(function () {
  "use strict";

  // Clear stale blacklist data on page load
  console.log("🧹 Clearing stale blacklist data on page load...");
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
  let controlSource = "rfid";
  let isESP32Online = false;
  let esp32HeartbeatTimer = null;
  const HEARTBEAT_TIMEOUT = 8000; // 8 detik

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

  // === 2. AJAX POLLING FUNCTIONS (NEW UPDATE) ===

  // Fungsi: Update Statistik Total (Kartu & Log Hari Ini)
  function updateDashboardStats() {
    $.ajax({
      url: "api/dashboard_stats.php",
      method: "GET",
      dataType: "json",
      success: function (data) {
        // Update angka di HTML (Statistik Kanan)
        $("#total-cards").text(data.cards);
        $("#total-logs").text(data.logs_today);
      },
      error: function (xhr, status, error) {
        // Silent error agar console tidak penuh spam jika gagal sesekali
        // console.warn("Gagal load stats:", error);
      },
    });
  }

  // Fungsi: Update Info Akses RFID Terakhir (Kotak Kiri)
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

          // Update Teks
          $("#last_rfid").html(
            '<code class="text-dark" style="font-size: 1.3rem; font-weight: bold;">' +
              uid +
              "</code>"
          );
          $("#last_rfid_name").html("<strong>" + name + "</strong>");
          $("#last_rfid_time").text(time);

          // Update Badge Status
          const statusElem = $("#last_rfid_status");
          statusElem.removeClass("badge-danger badge-secondary badge-success");

          if (status === "granted") {
            statusElem
              .addClass("badge-success")
              .html('<i class="fas fa-check-circle"></i> Akses Diterima');
          } else if (status === "denied") {
            statusElem
              .addClass("badge-danger")
              .html('<i class="fas fa-times-circle"></i> Akses Ditolak');
          } else {
            statusElem.addClass("badge-secondary").html("Status Tidak Dikenal");
          }
        } else {
          // Jika kosong
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
    );
  }

  // Fungsi: Load DHT Terakhir dari Database (Backup jika MQTT putus)
  function loadLatestDHT() {
    $.get(
      "api/dht_log.php?action=latest",
      function (res) {
        if (res.success && res.data) {
          const temp = parseFloat(res.data.temperature);
          const hum = parseFloat(res.data.humidity);

          if (!isNaN(temp)) {
            $("#temperature").text(temp.toFixed(1));
            // Logika Status Suhu
            if (temp > 30)
              $("#temp_status").html('<i class="fas fa-fire"></i> Panas');
            else if (temp < 20)
              $("#temp_status").html('<i class="fas fa-snowflake"></i> Dingin');
            else
              $("#temp_status").html(
                '<i class="fas fa-temperature-half"></i> Normal'
              );
          }

          if (!isNaN(hum)) {
            $("#humidity").text(hum.toFixed(1));
            // Logika Status Kelembapan
            if (hum > 70)
              $("#humidity_status").html('<i class="fas fa-tint"></i> Lembab');
            else if (hum < 30)
              $("#humidity_status").html('<i class="fas fa-burn"></i> Kering');
            else
              $("#humidity_status").html(
                '<i class="fas fa-droplet"></i> Normal'
              );
          }
        }
      },
      "json"
    );
  }

  // Fungsi: Load Status Kipas Terakhir
  function loadLatestFanStatus() {
    $.get(
      "api/kipas_crud.php?action=get_latest_status",
      function (res) {
        if (res.success && res.data) {
          const status = res.data.status === "on";
          const isAuto = res.data.mode === "auto";

          // Update UI Kipas
          $("#fan_status_text").text(status ? "ON" : "OFF");
          $("#fan_card")
            .removeClass("bg-success bg-danger bg-warning bg-secondary")
            .addClass(status ? "bg-success" : "bg-danger");

          const fanIcon = $("#fan_icon_dashboard");
          if (status) fanIcon.addClass("fan-spinning");
          else fanIcon.removeClass("fan-spinning");

          $("#fan_mode_text").html(
            '<i class="fas fa-' +
              (isAuto ? "magic" : "hand-pointer") +
              '"></i> Mode: ' +
              (isAuto ? "Auto" : "Manual")
          );
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
    client.publish(`${topicRoot}/system/ping`, "request_status");
    updateESP32Status("checking");
    updateFanStatus("checking");

    setTimeout(() => {
      const currentStatus = $("#esp_status").text().trim();
      if (currentStatus.includes("Checking")) {
        updateESP32Status("offline");
        updateFanStatus("offline");
      }
    }, 5000);
  });

  client.on("offline", () => {
    $("#esp_connection_time").text("Terputus...");
    updateESP32Status("offline");
    updateFanStatus("offline");
  });

  client.on("message", (topic, message) => {
    const msg = message.toString();
    resetHeartbeat();

    // Routing Pesan MQTT ke Fungsi Handler
    if (topic === statusTopic) handleESP32Status(msg);
    if (topic === `${topicRoot}/pintu/status`) handleDoorStatus(msg);
    if (topic === `${topicRoot}/dht/temperature`) handleTemperature(msg);
    if (topic === `${topicRoot}/dht/humidity`) handleHumidity(msg);

    // Handler Kipas (Kompleks)
    if (topic === `${topicRoot}/kipas/status`) {
      const parts = msg.split(",");
      const fanStatus = parts[0] ? parts[0].toLowerCase().trim() : "";
      const fanMode = parts[1] ? parts[1].toLowerCase().trim() : "";

      if (fanStatus) {
        const isOn = fanStatus === "on";
        $("#fan_status_text").text(isOn ? "ON" : "OFF");
        $("#fan_card")
          .removeClass("bg-success bg-danger")
          .addClass(isOn ? "bg-success" : "bg-danger");

        if (isOn) $("#fan_icon_dashboard").addClass("fan-spinning");
        else $("#fan_icon_dashboard").removeClass("fan-spinning");

        if (fanMode) {
          const isAuto = fanMode === "auto";
          $("#fan_mode_text").html(
            '<i class="fas fa-' +
              (isAuto ? "magic" : "hand-pointer") +
              '"></i> Mode: ' +
              (isAuto ? "Auto" : "Manual")
          );
        }
        markESP32Online();
      }
    }

    // Handler RFID (Realtime Pop-up)
    if (topic === `${topicRoot}/rfid/access`) {
      handleRFIDAccess(msg);
    }
  });

  // === HANDLER FUNCTIONS INTERNAL ===
  function handleESP32Status(msg) {
    if (msg === "online") {
      updateESP32Status("online");
      updateFanStatus("online");
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
    markESP32Online();
  }

  function handleTemperature(msg) {
    const temp = parseFloat(msg);
    if (!isNaN(temp)) {
      $("#temperature").text(temp.toFixed(1));
      addChartData("temperature", temp);
      markESP32Online();
    }
  }

  function handleHumidity(msg) {
    const hum = parseFloat(msg);
    if (!isNaN(hum)) {
      $("#humidity").text(hum.toFixed(1));
      addChartData("humidity", hum);
      markESP32Online();
    }
  }

  function handleRFIDAccess(msg) {
    try {
      const data = JSON.parse(msg);
      const uid = data.uid || "";
      const status = data.status || "";

      // Update tampilan langsung dari MQTT biar cepat
      $("#last_rfid").text(uid);

      // Delay sedikit lalu reload data lengkap (nama user dll) dari database
      setTimeout(loadLastRFIDAccess, 500);

      // Reload statistik juga karena ada akses baru
      setTimeout(updateDashboardStats, 500);

      markESP32Online();
    } catch (e) {}
  }

  function resetHeartbeat() {
    if (esp32HeartbeatTimer) clearTimeout(esp32HeartbeatTimer);
    if (!isESP32Online) {
      isESP32Online = true;
      updateESP32Status("online");
    }
    esp32HeartbeatTimer = setTimeout(() => {
      updateESP32Status("offline");
    }, HEARTBEAT_TIMEOUT);
  }

  function updateESP32Status(status) {
    const statusBox = $("#esp_status");
    const statusCard = statusBox.closest(".small-box");
    if (status === "online") {
      statusBox.text("Online");
      statusCard.removeClass("bg-danger bg-warning").addClass("bg-info");
    } else if (status === "offline") {
      statusBox.text("Offline");
      statusCard.removeClass("bg-info bg-warning").addClass("bg-danger");
      isESP32Online = false;
    } else {
      statusBox.html('<i class="fas fa-spinner fa-spin"></i> Checking...');
      statusCard.removeClass("bg-danger bg-info").addClass("bg-warning");
    }
  }

  function updateFanStatus(status) {
    // Helper sederhana untuk status icon kipas saat offline
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

  function markESP32Online() {
    resetHeartbeat();
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
    // Logic chart update sederhana
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
      if (type === "temperature")
        dhtChart.data.datasets[0].data[lastIndex] = value;
      else dhtChart.data.datasets[1].data[lastIndex] = value;
    }
    dhtChart.update("none");
  }

  // === 4. GLOBAL INITIALIZATION ===

  // Expose fungsi refresh untuk tombol manual
  window.loadLastRFID = function () {
    loadLastRFIDAccess();
    updateDashboardStats();
  };

  // Load Data Pertama Kali
  loadLastRFIDAccess();
  loadLatestDHT();
  loadLatestFanStatus();
  updateDashboardStats(); // <--- INI YANG BARU

  // Auto-Refresh Interval (Polling)
  // Kita set 3 detik agar terasa "realtime" untuk statistik & akses terakhir
  setInterval(function () {
    loadLastRFIDAccess();
    updateDashboardStats(); // <--- Statistik ikut di-refresh
  }, 3000);

  // Handle Resize Chart AdminLTE
  $('[data-card-widget="collapse"]').on("click", function () {
    setTimeout(() => {
      if (dhtChart) dhtChart.resize();
    }, 350);
  });
  $(window).on("resize", function () {
    if (dhtChart) dhtChart.resize();
  });
});
