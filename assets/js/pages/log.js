/**
 * Log Page - Activity Log Management
 * Handles RFID, DHT sensor, and door logs
 */

$(function () {
  "use strict";

  // Load all logs on page load
  loadRFIDLog();
  loadDHTLog();
  loadDoorLog();

  // Toggle accordion sections
  $(".btn-toggle-card").on("click", function () {
    const target = $(this).data("target");
    const icon = $(this).find("i");
    const cardBody = $(target);

    // Toggle visibility
    cardBody.toggleClass("show");

    // Rotate icon
    icon.toggleClass("rotate");
  });

  // Auto-refresh all logs every 30 seconds
  setInterval(function () {
    loadRFIDLog();
    loadDHTLog();
    loadDoorLog();
  }, 30000);
});

/**
 * Load RFID Access Logs
 */
function loadRFIDLog() {
  $.ajax({
    url: "api/rfid_crud.php",
    type: "GET",
    data: { action: "read_log" },
    dataType: "json",
    success: function (response) {
      const tbody = $("#tableRFIDLog tbody");
      tbody.empty();

      if (response.success && response.data && response.data.length > 0) {
        response.data.forEach(function (log, index) {
          const statusBadge =
            log.status === "granted"
              ? '<span class="badge badge-success"><i class="fas fa-check"></i> Granted</span>'
              : '<span class="badge badge-danger"><i class="fas fa-times"></i> Denied</span>';

          const cardName =
            log.name || '<em class="text-muted">Unknown Card</em>';

          const tr = `
                        <tr>
                            <td class="text-center">${index + 1}</td>
                            <td><code>${log.uid}</code></td>
                            <td>${cardName}</td>
                            <td>${statusBadge}</td>
                            <td><small>${log.timestamp}</small></td>
                        </tr>
                    `;
          tbody.append(tr);
        });
      } else {
        tbody.html(`
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            <i class="fas fa-inbox"></i> Tidak ada data log RFID
                        </td>
                    </tr>
                `);
      }
    },
    error: function (xhr, status, error) {
      console.error("Error loading RFID log:", error);
      $("#tableRFIDLog tbody").html(`
                <tr>
                    <td colspan="5" class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Gagal memuat data: ${error}
                    </td>
                </tr>
            `);
    },
  });
}

/**
 * Load DHT Sensor Logs
 */
function loadDHTLog() {
  $.ajax({
    url: "api/dht_log.php",
    type: "GET",
    dataType: "json",
    success: function (response) {
      const tbody = $("#tableDHTLog tbody");
      tbody.empty();

      if (response.success && response.data && response.data.length > 0) {
        response.data.forEach(function (log, index) {
          // Temperature badge color
          let tempColor = "info";
          if (log.temperature > 30) tempColor = "danger";
          else if (log.temperature > 25) tempColor = "warning";
          else if (log.temperature < 20) tempColor = "primary";

          // Humidity badge color
          let humColor = "info";
          if (log.humidity > 70) humColor = "warning";
          else if (log.humidity > 80) humColor = "danger";

          const tr = `
                        <tr>
                            <td class="text-center">${index + 1}</td>
                            <td>
                                <span class="badge badge-${tempColor}">
                                    <i class="fas fa-thermometer-half"></i> ${
                                      log.temperature
                                    }°C
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-${humColor}">
                                    <i class="fas fa-tint"></i> ${log.humidity}%
                                </span>
                            </td>
                            <td><small>${log.timestamp}</small></td>
                        </tr>
                    `;
          tbody.append(tr);
        });
      } else {
        tbody.html(`
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            <i class="fas fa-inbox"></i> Tidak ada data log DHT
                        </td>
                    </tr>
                `);
      }
    },
    error: function (xhr, status, error) {
      console.error("Error loading DHT log:", error);
      $("#tableDHTLog tbody").html(`
                <tr>
                    <td colspan="4" class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Gagal memuat data: ${error}
                    </td>
                </tr>
            `);
    },
  });
}

/**
 * Load Door Activity Logs
 */
function loadDoorLog() {
  $.ajax({
    url: "api/door_log.php",
    type: "GET",
    dataType: "json",
    success: function (response) {
      const tbody = $("#tableDoorLog tbody");
      tbody.empty();

      if (response.success && response.data && response.data.length > 0) {
        response.data.forEach(function (log, index) {
          const statusIcon =
            log.status === "terbuka"
              ? '<i class="fas fa-door-open text-warning"></i>'
              : '<i class="fas fa-door-closed text-success"></i>';

          const statusBadge =
            log.status === "terbuka"
              ? '<span class="badge badge-warning">Terbuka</span>'
              : '<span class="badge badge-success">Tertutup</span>';

          let sourceBadge =
            '<span class="badge badge-secondary">Unknown</span>';
          if (log.source === "rfid") {
            sourceBadge =
              '<span class="badge badge-primary"><i class="fas fa-id-card"></i> RFID</span>';
          } else if (log.source === "manual") {
            sourceBadge =
              '<span class="badge badge-info"><i class="fas fa-hand-pointer"></i> Manual</span>';
          }

          const tr = `
                        <tr>
                            <td class="text-center">${index + 1}</td>
                            <td>${statusIcon} ${statusBadge}</td>
                            <td>${sourceBadge}</td>
                            <td><small>${log.timestamp}</small></td>
                        </tr>
                    `;
          tbody.append(tr);
        });
      } else {
        tbody.html(`
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            <i class="fas fa-inbox"></i> Tidak ada data log pintu
                        </td>
                    </tr>
                `);
      }
    },
    error: function (xhr, status, error) {
      console.error("Error loading door log:", error);
      $("#tableDoorLog tbody").html(`
                <tr>
                    <td colspan="4" class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Gagal memuat data: ${error}
                    </td>
                </tr>
            `);
    },
  });
}

// Expose functions globally
window.loadRFIDLog = loadRFIDLog;
window.loadDHTLog = loadDHTLog;
window.loadDoorLog = loadDoorLog;
