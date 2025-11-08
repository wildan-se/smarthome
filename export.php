<?php

/**
 * Export Data Page
 * Export RFID cards, logs, DHT sensor data, and door activity
 */

session_start();

// Include core files
require_once 'core/Auth.php';
require_once 'core/Database.php';
require_once 'config/config.php';

// Check authentication
Auth::check();

// Get database instance
$db = Database::getInstance();
$conn = $db->getConnection();

// Get statistics
$rfid_count = $conn->query("SELECT COUNT(*) as total FROM rfid_logs")->fetch_assoc()['total'];
$dht_count = $conn->query("SELECT COUNT(*) as total FROM dht_logs")->fetch_assoc()['total'];
$door_count = $conn->query("SELECT COUNT(*) as total FROM door_status")->fetch_assoc()['total'];
$cards_count = $conn->query("SELECT COUNT(*) as total FROM rfid_cards")->fetch_assoc()['total'];

// Page configuration
$pageTitle = 'Export Data';
$activePage = 'export';
$pageCSS = [
  'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css'
];
$pageJS = [
  'https://cdn.jsdelivr.net/momentjs/latest/moment.min.js',
  'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
  'assets/js/pages/export.js'
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <?php
  require_once 'components/layout/head.php';
  renderHead($pageTitle, $pageCSS);
  ?>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
  <div class="wrapper">

    <?php
    require_once 'components/layout/header.php';
    renderHeader();

    require_once 'components/layout/sidebar.php';
    renderSidebar($activePage);
    ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">

      <!-- Content Header -->
      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1 class="fadeInLeft">
                <i class="fas fa-download text-primary"></i> Export Data Smart Home IoT
              </h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Export</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Info Alert -->
          <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <h5><i class="icon fas fa-info-circle"></i> Informasi Export</h5>
            Pilih jenis data yang ingin di-export, atur filter jika diperlukan, lalu klik tombol format yang diinginkan (Excel atau PDF).
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <!-- Data Utama -->
          <h4 class="mb-3 mt-4">
            <i class="fas fa-star text-warning"></i> Data Utama
          </h4>

          <div class="row">
            <!-- Export Log RFID -->
            <div class="col-md-6">
              <div class="card card-primary card-outline shadow-sm hover-shadow fadeIn">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-id-card"></i> Log Akses RFID
                  </h3>
                  <div class="card-tools">
                    <span class="badge badge-info"><?= number_format($rfid_count) ?> records</span>
                  </div>
                </div>
                <div class="card-body">
                  <p class="text-muted mb-3">
                    <i class="fas fa-info-circle"></i> Export data log akses kartu RFID dengan filter tanggal dan status.
                  </p>

                  <form action="api/export_rfid.php" method="GET" target="_blank">
                    <div class="form-group">
                      <label class="font-weight-bold">
                        <i class="far fa-calendar-alt"></i> Range Tanggal:
                      </label>
                      <input type="text" class="form-control" name="daterange" id="rfid_daterange"
                        placeholder="Klik untuk memilih tanggal (opsional)">
                      <small class="form-text text-muted">Kosongkan untuk export semua data</small>
                    </div>

                    <div class="form-group">
                      <label class="font-weight-bold">
                        <i class="fas fa-filter"></i> Filter Status:
                      </label>
                      <select class="form-control" name="status">
                        <option value="">Semua Status</option>
                        <option value="granted">✅ Akses Diterima</option>
                        <option value="denied">❌ Akses Ditolak</option>
                      </select>
                    </div>

                    <div class="form-group mb-0">
                      <label class="font-weight-bold">
                        <i class="fas fa-file-download"></i> Pilih Format:
                      </label>
                      <div class="d-flex gap-2">
                        <button type="submit" name="format" value="excel" class="btn btn-success flex-fill mx-1 shadow-sm">
                          <i class="fas fa-file-excel"></i> Excel (.xlsx)
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-danger flex-fill mx-1 shadow-sm">
                          <i class="fas fa-file-pdf"></i> PDF
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Export Log DHT -->
            <div class="col-md-6">
              <div class="card card-success card-outline shadow-sm hover-shadow fadeIn">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-thermometer-half"></i> Log Suhu & Kelembapan
                  </h3>
                  <div class="card-tools">
                    <span class="badge badge-info"><?= number_format($dht_count) ?> records</span>
                  </div>
                </div>
                <div class="card-body">
                  <p class="text-muted mb-3">
                    <i class="fas fa-info-circle"></i> Export data log sensor DHT22 dengan filter tanggal dan range nilai.
                  </p>

                  <form action="api/export_dht.php" method="GET" target="_blank">
                    <div class="form-group">
                      <label class="font-weight-bold">
                        <i class="far fa-calendar-alt"></i> Range Tanggal:
                      </label>
                      <input type="text" class="form-control" name="daterange" id="dht_daterange"
                        placeholder="Klik untuk memilih tanggal (opsional)">
                      <small class="form-text text-muted">Kosongkan untuk export semua data</small>
                    </div>

                    <div class="row">
                      <div class="col-6">
                        <div class="form-group">
                          <label class="font-weight-bold">
                            <i class="fas fa-temperature-low"></i> Suhu Min:
                          </label>
                          <input type="number" class="form-control" name="temp_min" placeholder="0" step="0.1">
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="form-group">
                          <label class="font-weight-bold">
                            <i class="fas fa-temperature-high"></i> Suhu Max:
                          </label>
                          <input type="number" class="form-control" name="temp_max" placeholder="100" step="0.1">
                        </div>
                      </div>
                    </div>

                    <div class="form-group mb-0">
                      <label class="font-weight-bold">
                        <i class="fas fa-file-download"></i> Pilih Format:
                      </label>
                      <div class="d-flex gap-2">
                        <button type="submit" name="format" value="excel" class="btn btn-success flex-fill mx-1 shadow-sm">
                          <i class="fas fa-file-excel"></i> Excel (.xlsx)
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-danger flex-fill mx-1 shadow-sm">
                          <i class="fas fa-file-pdf"></i> PDF
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <!-- Data Pendukung -->
          <h4 class="mb-3 mt-5">
            <i class="fas fa-star-half-alt text-info"></i> Data Pendukung
          </h4>

          <div class="row">
            <!-- Export Log Status Pintu -->
            <div class="col-md-6">
              <div class="card card-warning card-outline shadow-sm hover-shadow fadeIn">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-door-open"></i> Log Status Pintu
                  </h3>
                  <div class="card-tools">
                    <span class="badge badge-info"><?= number_format($door_count) ?> records</span>
                  </div>
                </div>
                <div class="card-body">
                  <p class="text-muted mb-3">
                    <i class="fas fa-info-circle"></i> Export data log perubahan status pintu (terbuka/tertutup).
                  </p>

                  <form action="api/export_door.php" method="GET" target="_blank">
                    <div class="form-group">
                      <label class="font-weight-bold">
                        <i class="far fa-calendar-alt"></i> Range Tanggal:
                      </label>
                      <input type="text" class="form-control" name="daterange" id="door_daterange"
                        placeholder="Klik untuk memilih tanggal (opsional)">
                      <small class="form-text text-muted">Kosongkan untuk export semua data</small>
                    </div>

                    <div class="form-group">
                      <label class="font-weight-bold">
                        <i class="fas fa-filter"></i> Filter Status:
                      </label>
                      <select class="form-control" name="status">
                        <option value="">Semua Status</option>
                        <option value="terbuka">🔓 Terbuka</option>
                        <option value="tertutup">🔒 Tertutup</option>
                      </select>
                    </div>

                    <div class="form-group mb-0">
                      <label class="font-weight-bold">
                        <i class="fas fa-file-download"></i> Pilih Format:
                      </label>
                      <div class="d-flex gap-2">
                        <button type="submit" name="format" value="excel" class="btn btn-success flex-fill mx-1 shadow-sm">
                          <i class="fas fa-file-excel"></i> Excel (.xlsx)
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-danger flex-fill mx-1 shadow-sm">
                          <i class="fas fa-file-pdf"></i> PDF
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Export Daftar Kartu RFID -->
            <div class="col-md-6">
              <div class="card card-info card-outline shadow-sm hover-shadow fadeIn">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-address-card"></i> Daftar Kartu RFID
                  </h3>
                  <div class="card-tools">
                    <span class="badge badge-info"><?= number_format($cards_count) ?> cards</span>
                  </div>
                </div>
                <div class="card-body">
                  <p class="text-muted mb-3">
                    <i class="fas fa-info-circle"></i> Export daftar kartu RFID yang terdaftar di sistem.
                  </p>

                  <form action="api/export_cards.php" method="GET" target="_blank">
                    <div class="alert alert-light border">
                      <i class="fas fa-info-circle text-info"></i>
                      <strong>Informasi:</strong><br>
                      Export ini akan menghasilkan file berisi semua kartu RFID yang terdaftar beserta nama pemilik dan tanggal registrasi.
                    </div>

                    <div class="form-group mb-0">
                      <label class="font-weight-bold">
                        <i class="fas fa-file-download"></i> Pilih Format:
                      </label>
                      <div class="d-flex gap-2">
                        <button type="submit" name="format" value="excel" class="btn btn-success flex-fill mx-1 shadow-sm">
                          <i class="fas fa-file-excel"></i> Excel (.xlsx)
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-danger flex-fill mx-1 shadow-sm">
                          <i class="fas fa-file-pdf"></i> PDF
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <!-- Informasi Format -->
          <div class="card card-secondary card-outline shadow-sm mt-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-question-circle"></i> Informasi Format Export
              </h3>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <h5 class="text-success">
                    <i class="fas fa-file-excel"></i> Format Excel (.xlsx)
                  </h5>
                  <ul>
                    <li>Dapat dibuka dan diedit dengan Microsoft Excel, Google Sheets, atau LibreOffice Calc</li>
                    <li>Mendukung formula dan formatting lanjutan</li>
                    <li>Ideal untuk analisis data lebih lanjut</li>
                    <li>File size lebih besar daripada CSV</li>
                  </ul>
                </div>
                <div class="col-md-6">
                  <h5 class="text-danger">
                    <i class="fas fa-file-pdf"></i> Format PDF
                  </h5>
                  <ul>
                    <li>Format dokumen universal yang dapat dibuka di semua perangkat</li>
                    <li>Tidak dapat diedit, cocok untuk arsip atau laporan</li>
                    <li>Tampilan profesional dengan header dan footer</li>
                    <li>Ideal untuk printing atau sharing</li>
                  </ul>
                </div>
              </div>

              <hr>

              <h5><i class="fas fa-calendar-alt"></i> Filter Tanggal</h5>
              <p class="text-muted">
                Gunakan date range picker untuk memfilter data berdasarkan tanggal. Format yang didukung:
              </p>
              <ul>
                <li><strong>Range:</strong> 01/01/2024 - 31/01/2024</li>
                <li><strong>Single Date:</strong> Pilih tanggal yang sama untuk start dan end</li>
                <li><strong>All Data:</strong> Kosongkan field untuk export semua data</li>
              </ul>
            </div>
          </div>

        </div>
      </section>
    </div>

    <?php
    require_once 'components/layout/footer.php';
    renderFooter($pageJS);
    ?>

  </div>

  <script>
    // Initialize daterangepicker
    $(function() {
      // Configure locale for Indonesian
      moment.locale('id');

      const dateRangeOptions = {
        autoUpdateInput: false,
        locale: {
          format: 'DD/MM/YYYY',
          separator: ' - ',
          applyLabel: 'Terapkan',
          cancelLabel: 'Batal',
          fromLabel: 'Dari',
          toLabel: 'Sampai',
          customRangeLabel: 'Custom',
          weekLabel: 'W',
          daysOfWeek: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
          monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
        },
        ranges: {
          'Hari Ini': [moment(), moment()],
          'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
          '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
          '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
          'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
          'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
      };

      // Apply to all daterange inputs
      $('#rfid_daterange, #dht_daterange, #door_daterange').daterangepicker(dateRangeOptions);

      // Update input when date is selected
      $('#rfid_daterange, #dht_daterange, #door_daterange').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
      });

      // Clear input when cancelled
      $('#rfid_daterange, #dht_daterange, #door_daterange').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
      });
    });
  </script>
</body>

</html>