/**
 * Log Page - Activity Log Management
 * Handles RFID, DHT sensor, and door logs with filters and statistics
 */

$(function () {
  "use strict";

  let rfidData = [];
  let dhtData = [];
  let doorData = [];

  // Load all logs on page load
  refreshAllLogs();

  // Auto-refresh all logs every 30 seconds
  setInterval(refreshAllLogs, 30000);

  // Filter events
  $("#searchRfid").on("keyup", function () {
    loadRFIDLog();
  });

  $("#filterRfidStatus").on("change", function () {
    loadRFIDLog();
  });

  $("#filterDHTTime").on("change", function () {
    loadDHTLog();
  });

  $("#filterDoorStatus").on("change", function () {
    loadDoorLog();
  });

  // Expose globally
  window.loadRFIDLog = loadRFIDLog;
  window.loadDHTLog = loadDHTLog;
  window.loadDoorLog = loadDoorLog;
  window.refreshAllLogs = refreshAllLogs;
  window.toggleCard = toggleCard;

  /**
   * Smooth accordion functionality with animations
   */
  function toggleCard(cardId, cardElementId) {
    const cards = ["collapseRfid", "collapseDht", "collapseDoor"];
    const icons = ["iconRfid", "iconDht", "iconDoor"];
    const cardElements = ["cardRfid", "cardDht", "cardDoor"];

    cards.forEach((id, index) => {
      const cardBody = document.getElementById(id);
      const icon = document.getElementById(icons[index]);
      const cardElement = document.getElementById(cardElements[index]);

      if (id === cardId) {
        // Toggle the clicked card with smooth animation
        if (cardBody.classList.contains("show")) {
          cardBody.classList.remove("show");
          icon.classList.remove("fa-minus");
          icon.classList.add("fa-plus");
          cardElement.classList.remove("active-card");
        } else {
          cardBody.classList.add("show");
          icon.classList.remove("fa-plus");
          icon.classList.add("fa-minus");
          cardElement.classList.add("active-card");
        }
      } else {
        // Close other cards with smooth animation
        cardBody.classList.remove("show");
        icon.classList.remove("fa-minus");
        icon.classList.add("fa-plus");
        cardElement.classList.remove("active-card");
      }
    });
  }

  /**
   * Update statistics info boxes
   */
  function updateStatistics() {
    // RFID Stats
    $("#totalRfidLogs").text(rfidData.length);

    // DHT Stats
    if (dhtData.length > 0) {
      // Filter out NaN/invalid values ONLY, not zero or negative
      const validData = dhtData.filter((d) => {
        const temp = parseFloat(d.temperature);
        const hum = parseFloat(d.humidity);
        // ✅ Only skip if truly invalid (NaN, null, undefined)
        return (
          !isNaN(temp) &&
          !isNaN(hum) &&
          temp !== null &&
          temp !== undefined &&
          hum !== null &&
          hum !== undefined
        );
      });

      if (validData.length > 0) {
        const avgTemp =
          validData.reduce((sum, d) => sum + parseFloat(d.temperature), 0) /
          validData.length;
        const avgHum =
          validData.reduce((sum, d) => sum + parseFloat(d.humidity), 0) /
          validData.length;
        $("#avgTemp").text(avgTemp.toFixed(1) + "°C");
        $("#avgHum").text(avgHum.toFixed(1) + "%");
      } else {
        $("#avgTemp").text("-");
        $("#avgHum").text("-");
      }
    } else {
      $("#avgTemp").text("-");
      $("#avgHum").text("-");
    }

    // Door Stats
    $("#doorChanges").text(doorData.length);
  }

  /**
   * Load RFID Access Logs with filters
   */
  function loadRFIDLog() {
    $.get(
      "api/rfid_crud.php?action=getlogs",
      function (res) {
        let rows = "";
        if (res.success && res.data && res.data.length > 0) {
          rfidData = res.data;

          // Apply filters
          let filteredData = rfidData;
          const statusFilter = $("#filterRfidStatus").val();
          const searchText = $("#searchRfid").val().toLowerCase();

          if (statusFilter) {
            filteredData = filteredData.filter(
              (d) => d.status === statusFilter
            );
          }

          if (searchText) {
            filteredData = filteredData.filter(
              (d) =>
                d.uid.toLowerCase().includes(searchText) ||
                (d.name && d.name.toLowerCase().includes(searchText))
            );
          }

          filteredData.forEach((l, index) => {
            const name =
              l.name || '<em class="text-muted">Tidak terdaftar</em>';
            const statusClass = l.status === "granted" ? "success" : "danger";
            const statusIcon =
              l.status === "granted" ? "check-circle" : "times-circle";
            const statusText =
              l.status === "granted" ? "Akses Diterima" : "Akses Ditolak";

            rows += `
            <tr class="fadeIn">
              <td class="text-center"><strong>${index + 1}</strong></td>
              <td><code class="code-badge">${l.uid}</code></td>
              <td><i class="fas fa-user text-muted"></i> ${name}</td>
              <td><i class="far fa-clock text-muted"></i> ${l.access_time}</td>
              <td class="text-center">
                <span class="badge badge-${statusClass} pulse">
                  <i class="fas fa-${statusIcon}"></i> ${statusText}
                </span>
              </td>
            </tr>
          `;
          });
          $("#rfidCount").text(
            filteredData.length + " / " + rfidData.length + " records"
          );
        } else {
          rfidData = [];
          rows =
            '<tr><td colspan="5" class="text-center text-muted"><i class="fas fa-info-circle"></i> Belum ada log akses RFID</td></tr>';
          $("#rfidCount").text("0 records");
        }
        $("#tableRFIDLog tbody").html(rows);
        updateStatistics();
      },
      "json"
    ).fail(function () {
      $("#tableRFIDLog tbody").html(
        '<tr><td colspan="5" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data</td></tr>'
      );
    });
  }

  /**
   * Load DHT Sensor Logs with filters
   */
  function loadDHTLog() {
    // Get filter values
    const timeFilter = $("#filterDHTTime").val();
    const tempMin = $("#filterTempMin").val();
    const tempMax = $("#filterTempMax").val();
    const humMin = $("#filterHumMin").val();
    const humMax = $("#filterHumMax").val();

    // Build query parameters - use wide range to get all data
    const params = new URLSearchParams({
      action: "filter", // ✅ FIX: Add action parameter
      time: timeFilter,
      temp_min: tempMin || "-100",
      temp_max: tempMax || "200",
      hum_min: humMin || "-100",
      hum_max: humMax || "200",
    });

    console.log("📊 DHT Filter params:", params.toString());

    $.get(
      "api/dht_log.php?" + params.toString(),
      function (res) {
        console.log("✅ DHT Response received:", res);

        let rows = "";
        if (res.success && res.data && res.data.length > 0) {
          console.log("📈 DHT Data count:", res.data.length);
          dhtData = res.data;

          let validCount = 0;
          dhtData.forEach((l, index) => {
            const temp = parseFloat(l.temperature);
            const hum = parseFloat(l.humidity);

            // ✅ FIX: Only skip if truly invalid (NaN or null), not if zero or negative
            if (isNaN(temp) || isNaN(hum) || temp === null || hum === null) {
              console.warn("⚠️ Skipping invalid DHT data:", l);
              return; // Skip this row
            }

            validCount++;
            const tempClass =
              temp > 30
                ? "text-danger"
                : temp < 20
                ? "text-primary"
                : "text-success";
            const humClass = hum > 70 ? "text-info" : "text-muted";

            rows += `
            <tr class="fadeIn">
              <td class="text-center"><strong>${validCount}</strong></td>
              <td><i class="far fa-clock text-muted"></i> ${l.log_time}</td>
              <td class="${tempClass}">
                <i class="fas fa-thermometer-half"></i> <strong>${temp.toFixed(
                  1
                )}°C</strong>
              </td>
              <td class="${humClass}">
                <i class="fas fa-tint"></i> <strong>${hum.toFixed(1)}%</strong>
              </td>
            </tr>
          `;
          });

          console.log(
            `✅ DHT table updated: ${validCount} valid rows out of ${dhtData.length} total`
          );

          // Update count and info
          $("#dhtCount").text(res.count + " records");
          $("#dhtInfo").text(res.count + " data");

          // ✅ Update time info based on filter
          const timeLabels = {
            30: "30 menit terakhir",
            60: "1 jam terakhir",
            180: "3 jam terakhir",
            360: "6 jam terakhir",
            720: "12 jam terakhir",
            1440: "24 jam terakhir",
            2880: "2 hari terakhir",
            4320: "3 hari terakhir",
            10080: "7 hari terakhir",
            20160: "14 hari terakhir",
            43200: "30 hari terakhir",
            all: "semua waktu",
          };
          $("#dhtTimeInfo").text(
            timeLabels[timeFilter] || res.info || "rentang waktu dipilih"
          );

          // ✅ Show debug info if available
          if (res.debug) {
            console.log("🔍 Debug info:", res.debug);
          }
        } else {
          console.warn("⚠️ No DHT data found or empty response");
          console.log("Response:", res);
          dhtData = [];
          rows =
            '<tr><td colspan="4" class="text-center text-muted"><i class="fas fa-info-circle"></i> Belum ada log suhu & kelembapan</td></tr>';
          $("#dhtCount").text("0 records");
          $("#dhtInfo").text("Tidak ada data");
          $("#dhtTimeInfo").text("-");
        }
        $("#tableDHTLog tbody").html(rows);
        updateStatistics();
      },
      "json"
    ).fail(function (xhr, status, error) {
      console.error("❌ DHT Load failed:", {
        status: xhr.status,
        statusText: xhr.statusText,
        error: error,
        response: xhr.responseText,
      });

      $("#tableDHTLog tbody").html(
        '<tr><td colspan="4" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data - ' +
          xhr.status +
          " " +
          xhr.statusText +
          "</td></tr>"
      );
      $("#dhtCount").text("Error");
      $("#dhtInfo").text("Gagal memuat");
      $("#dhtTimeInfo").text("-");

      // Try to parse error response
      try {
        const errRes = JSON.parse(xhr.responseText);
        console.error("Error details:", errRes);
        if (errRes.error) {
          $("#tableDHTLog tbody").html(
            '<tr><td colspan="4" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> ' +
              errRes.error +
              "</td></tr>"
          );
        }
      } catch (e) {
        console.error(
          "Raw error response:",
          xhr.responseText.substring(0, 200)
        );
      }
    });
  }

  /**
   * Load Door Status Logs with filters
   */
  function loadDoorLog() {
    // ✅ Load semua data tanpa pagination - auto limited by backend (7 days)
    $.get(
      "api/door_log.php?action=get_logs&per_page=1000",
      function (res) {
        let rows = "";
        if (res.success && res.data && res.data.length > 0) {
          doorData = res.data;

          // Apply filter
          let filteredData = doorData;
          const statusFilter = $("#filterDoorStatus").val();

          if (statusFilter) {
            filteredData = filteredData.filter(
              (d) => d.status === statusFilter
            );
          }

          filteredData.forEach((l, index) => {
            const statusClass =
              l.status === "terbuka" ? "success" : "secondary";
            const statusIcon =
              l.status === "terbuka" ? "door-open" : "door-closed";
            const statusText = l.status.toUpperCase();

            rows += `
            <tr class="fadeIn">
              <td class="text-center"><strong>${index + 1}</strong></td>
              <td><i class="far fa-clock text-muted"></i> ${l.timestamp}</td>
              <td class="text-center">
                <span class="badge badge-${statusClass}">
                  <i class="fas fa-${statusIcon}"></i> ${statusText}
                </span>
              </td>
            </tr>
          `;
          });

          // Update count - simple display
          const totalRecords = res.total || doorData.length;
          $("#doorCount").text(
            `${filteredData.length} / ${totalRecords} records`
          );
        } else {
          doorData = [];
          rows =
            '<tr><td colspan="3" class="text-center text-muted"><i class="fas fa-info-circle"></i> Belum ada log status pintu</td></tr>';
          $("#doorCount").text("0 records");
        }
        $("#tableDoorLog tbody").html(rows);
        updateStatistics();
      },
      "json"
    ).fail(function (xhr) {
      console.error("Failed to load door log:", xhr.responseText);
      $("#tableDoorLog tbody").html(
        '<tr><td colspan="3" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data</td></tr>'
      );
    });
  }

  /**
   * Refresh all logs
   */
  function refreshAllLogs() {
    console.log("🔄 Refreshing all logs...");
    loadRFIDLog();
    loadDHTLog();
    loadDoorLog();
  }

  /**
   * ========================================
   * MQTT REALTIME CONNECTION
   * ========================================
   */
  const broker = "wildan-se.cloud.shiftr.io";
  const mqttProtocol = "wss";
  const mqttUser = "wildan-se";
  const mqttPass = "12wildan34";
  const topicRoot = "smarthome";

  let latestTemp = null;
  let latestHum = null;

  const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
    username: mqttUser,
    password: mqttPass,
    clientId: "log-page-" + Math.random().toString(16).substr(2, 8),
  });

  client.on("connect", () => {
    console.log("✅ MQTT Log Page Connected!");

    // Subscribe to all topics
    client.subscribe(`${topicRoot}/dht/temperature`);
    client.subscribe(`${topicRoot}/dht/humidity`);
    client.subscribe(`${topicRoot}/rfid/access`);
    client.subscribe(`${topicRoot}/pintu/status`);
  });

  client.on("message", (topic, message) => {
    const msg = message.toString();
    console.log(`📨 MQTT: ${topic} => ${msg}`);

    // Handle DHT Temperature & Humidity
    if (topic === `${topicRoot}/dht/temperature`) {
      const temp = parseFloat(msg);
      if (!isNaN(temp)) {
        latestTemp = temp;
        checkAndReloadDHT();
      }
    }

    if (topic === `${topicRoot}/dht/humidity`) {
      const hum = parseFloat(msg);
      if (!isNaN(hum)) {
        latestHum = hum;
        checkAndReloadDHT();
      }
    }

    // Handle RFID Access
    if (topic === `${topicRoot}/rfid/access`) {
      try {
        const data = JSON.parse(msg);
        console.log("🔑 RFID Access detected, reloading...");
        // Reload RFID log with a slight delay for DB insert
        setTimeout(() => {
          loadRFIDLog();
        }, 500);
      } catch (e) {
        console.error("Failed to parse RFID data:", e);
      }
    }

    // Handle Door Status
    if (topic === `${topicRoot}/pintu/status`) {
      console.log("🚪 Door status changed, reloading...");
      setTimeout(() => {
        loadDoorLog();
      }, 500);
    }
  });

  /**
   * Reload DHT data when both temp and humidity received
   * Note: Dashboard already saves to DB, we just reload from DB
   */
  function checkAndReloadDHT() {
    if (latestTemp !== null && latestHum !== null) {
      console.log(`🌡️ DHT Data received: ${latestTemp}°C, ${latestHum}%`);
      console.log("♻️ Reloading DHT log from database...");

      // Reload DHT log with a delay to ensure DB insert completed
      setTimeout(() => {
        loadDHTLog();
      }, 800);

      // Reset for next reading
      latestTemp = null;
      latestHum = null;
    }
  }

  /**
   * Add DHT row to table without full reload (DEPRECATED - using reload instead)
   * Kept for backward compatibility
   */
  function addDHTRowToTable(temp, hum) {
    const now = new Date();
    const timeStr =
      now.toLocaleDateString("id-ID") + " " + now.toLocaleTimeString("id-ID");

    const tempClass =
      temp > 30 ? "text-danger" : temp < 20 ? "text-primary" : "text-success";
    const humClass = hum > 70 ? "text-info" : "text-muted";

    // Add to dhtData array at the beginning
    dhtData.unshift({
      id: "new-" + Date.now(),
      temperature: temp.toFixed(1),
      humidity: hum.toFixed(1),
      log_time: timeStr,
    });

    // Rebuild table with updated data
    const tbody = $("#tableDHTLog tbody");
    let rows = "";
    let validCount = 0;

    dhtData.forEach((l, index) => {
      const t = parseFloat(l.temperature);
      const h = parseFloat(l.humidity);

      if (isNaN(t) || isNaN(h) || t === null || h === null) return;

      validCount++;
      const tClass =
        t > 30 ? "text-danger" : t < 20 ? "text-primary" : "text-success";
      const hClass = h > 70 ? "text-info" : "text-muted";

      // Highlight new row
      const isNew = typeof l.id === "string" && l.id.startsWith("new-");
      const rowClass = isNew ? "fadeIn table-success" : "fadeIn";

      rows += `
        <tr class="${rowClass}">
          <td class="text-center"><strong>${validCount}</strong></td>
          <td><i class="far fa-clock text-muted"></i> ${l.log_time}</td>
          <td class="${tClass}">
            <i class="fas fa-thermometer-half"></i> <strong>${t.toFixed(
              1
            )}°C</strong>
          </td>
          <td class="${hClass}">
            <i class="fas fa-tint"></i> <strong>${h.toFixed(1)}%</strong>
          </td>
        </tr>
      `;
    });

    tbody.html(rows);

    // Update count
    $("#dhtCount").text(validCount + " records");
    $("#dhtInfo").text(validCount + " data");

    console.log("✅ DHT table updated with new row");
  }

  client.on("error", (err) => {
    console.error("❌ MQTT Error:", err);
  });

  client.on("offline", () => {
    console.warn("⚠️ MQTT Offline");
  });
});
