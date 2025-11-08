/**
 * Export Page - Data Export Management
 * Handles CSV/Excel export for RFID, DHT, and door logs
 */

$(function () {
  "use strict";

  // Export buttons
  $("#btnExportRFIDCards").on("click", function () {
    exportData("cards", "export_cards.php");
  });

  $("#btnExportRFIDLogs").on("click", function () {
    exportData("rfid", "export_rfid.php");
  });

  $("#btnExportDHTLogs").on("click", function () {
    exportData("dht", "export_dht.php");
  });

  $("#btnExportDoorLogs").on("click", function () {
    exportData("door", "export_door.php");
  });
});

/**
 * Export data to CSV/Excel
 * @param {string} type - Data type (cards, rfid, dht, door)
 * @param {string} apiEndpoint - API endpoint for export
 */
function exportData(type, apiEndpoint) {
  // Show loading
  showLoading("Mengekspor data...");

  // Determine file name
  const timestamp = new Date().toISOString().slice(0, 10);
  let fileName = "";

  switch (type) {
    case "cards":
      fileName = `rfid_cards_${timestamp}.csv`;
      break;
    case "rfid":
      fileName = `rfid_logs_${timestamp}.csv`;
      break;
    case "dht":
      fileName = `dht_logs_${timestamp}.csv`;
      break;
    case "door":
      fileName = `door_logs_${timestamp}.csv`;
      break;
    default:
      fileName = `export_${timestamp}.csv`;
  }

  // Create download link
  const downloadUrl = `api/${apiEndpoint}`;

  // Use XMLHttpRequest to download file
  const xhr = new XMLHttpRequest();
  xhr.open("GET", downloadUrl, true);
  xhr.responseType = "blob";

  xhr.onload = function () {
    hideLoading();

    if (xhr.status === 200) {
      // Create blob and download
      const blob = new Blob([xhr.response], { type: "text/csv" });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = fileName;
      document.body.appendChild(a);
      a.click();

      // Cleanup
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);

      // Show success message
      showSuccessToast(`Data ${getTypeLabel(type)} berhasil diekspor!`);
    } else {
      showErrorToast("Gagal mengekspor data. Silakan coba lagi.");
    }
  };

  xhr.onerror = function () {
    hideLoading();
    showErrorToast("Terjadi kesalahan saat mengekspor data.");
  };

  xhr.send();
}

/**
 * Get label for data type
 * @param {string} type - Data type
 * @returns {string} - Type label
 */
function getTypeLabel(type) {
  switch (type) {
    case "cards":
      return "Kartu RFID";
    case "rfid":
      return "Log Akses RFID";
    case "dht":
      return "Log Sensor DHT";
    case "door":
      return "Log Aktivitas Pintu";
    default:
      return "Data";
  }
}

// Expose functions globally
window.exportData = exportData;
