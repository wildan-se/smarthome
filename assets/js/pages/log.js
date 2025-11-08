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
      // Filter out NaN/invalid values
      const validData = dhtData.filter((d) => {
        const temp = parseFloat(d.temperature);
        const hum = parseFloat(d.humidity);
        return !isNaN(temp) && !isNaN(hum) && temp > 0 && hum > 0;
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

    // Build query parameters
    const params = new URLSearchParams({
      time: timeFilter,
      temp_min: tempMin || "0",
      temp_max: tempMax || "100",
      hum_min: humMin || "0",
      hum_max: "100",
    });

    $.get(
      "api/dht_log.php?" + params.toString(),
      function (res) {
        let rows = "";
        if (res.success && res.data && res.data.length > 0) {
          dhtData = res.data;

          dhtData.forEach((l, index) => {
            const temp = parseFloat(l.temperature);
            const hum = parseFloat(l.humidity);

            // Skip if NaN or invalid
            if (isNaN(temp) || isNaN(hum) || temp <= 0 || hum <= 0) {
              return; // Skip this row
            }

            const tempClass =
              temp > 30
                ? "text-danger"
                : temp < 20
                ? "text-primary"
                : "text-success";
            const humClass = hum > 70 ? "text-info" : "text-muted";

            rows += `
            <tr class="fadeIn">
              <td class="text-center"><strong>${index + 1}</strong></td>
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

          // Update count and info
          $("#dhtCount").text(res.count + " records");
          $("#dhtInfo").text(res.info || dhtData.length + " data tersedia");
        } else {
          dhtData = [];
          rows =
            '<tr><td colspan="4" class="text-center text-muted"><i class="fas fa-info-circle"></i> Belum ada log suhu & kelembapan</td></tr>';
          $("#dhtCount").text("0 records");
          $("#dhtInfo").text("Tidak ada data");
        }
        $("#tableDHTLog tbody").html(rows);
        updateStatistics();
      },
      "json"
    ).fail(function () {
      $("#tableDHTLog tbody").html(
        '<tr><td colspan="4" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data</td></tr>'
      );
    });
  }

  /**
   * Load Door Status Logs with filters
   */
  function loadDoorLog() {
    $.get(
      "api/door_log.php?action=get_logs&limit=100",
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
          $("#doorCount").text(
            filteredData.length + " / " + doorData.length + " records"
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
    ).fail(function () {
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
});
