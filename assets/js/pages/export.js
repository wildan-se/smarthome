/**
 * Export Page - Data Export Management
 * Handles export for RFID, DHT, and door logs with date range filtering
 */

$(function () {
  "use strict";

  // Configure locale for Indonesian
  moment.locale("id");

  const dateRangeOptions = {
    autoUpdateInput: false,
    locale: {
      format: "DD/MM/YYYY",
      separator: " - ",
      applyLabel: "Terapkan",
      cancelLabel: "Batal",
      fromLabel: "Dari",
      toLabel: "Sampai",
      customRangeLabel: "Custom",
      weekLabel: "W",
      daysOfWeek: ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"],
      monthNames: [
        "Januari",
        "Februari",
        "Maret",
        "April",
        "Mei",
        "Juni",
        "Juli",
        "Agustus",
        "September",
        "Oktober",
        "November",
        "Desember",
      ],
    },
    ranges: {
      "Hari Ini": [moment(), moment()],
      Kemarin: [moment().subtract(1, "days"), moment().subtract(1, "days")],
      "7 Hari Terakhir": [moment().subtract(6, "days"), moment()],
      "30 Hari Terakhir": [moment().subtract(29, "days"), moment()],
      "Bulan Ini": [moment().startOf("month"), moment().endOf("month")],
      "Bulan Lalu": [
        moment().subtract(1, "month").startOf("month"),
        moment().subtract(1, "month").endOf("month"),
      ],
    },
  };

  // Apply to all daterange inputs
  $("#rfid_daterange, #dht_daterange, #door_daterange").daterangepicker(
    dateRangeOptions
  );

  // Update input when date is selected
  $("#rfid_daterange, #dht_daterange, #door_daterange").on(
    "apply.daterangepicker",
    function (ev, picker) {
      $(this).val(
        picker.startDate.format("DD/MM/YYYY") +
          " - " +
          picker.endDate.format("DD/MM/YYYY")
      );
    }
  );

  // Clear input when cancelled
  $("#rfid_daterange, #dht_daterange, #door_daterange").on(
    "cancel.daterangepicker",
    function (ev, picker) {
      $(this).val("");
    }
  );

  console.log("📊 Export Page Initialized");
});
