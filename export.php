<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'config/config.php';

// Get statistics
$rfid_count = $conn->query("SELECT COUNT(*) as total FROM rfid_logs")->fetch_assoc()['total'];
$dht_count = $conn->query("SELECT COUNT(*) as total FROM dht_logs")->fetch_assoc()['total'];
$door_count = $conn->query("SELECT COUNT(*) as total FROM door_status")->fetch_assoc()['total'];
$cards_count = $conn->query("SELECT COUNT(*) as total FROM rfid_cards")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Export Data - Smart Home IoT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
  <link rel="stylesheet" href="assets/css/custom.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
  <div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
          <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
        </li>
      </ul>
      <ul class="navbar-nav ml-auto">
        <li class="nav-item">
          <span class="nav-link"><i class="far fa-clock"></i> <span id="current-time"></span></span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </li>
      </ul>
    </nav>

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
      <a href="index.php" class="brand-link">
        <i class="fas fa-home brand-image" style="font-size: 2rem; opacity: .8;"></i>
        <span class="brand-text font-weight-bold">Smart Home</span>
      </a>
      <div class="sidebar">
        <nav class="mt-3">
          <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
            <li class="nav-item">
              <a href="index.php" class="nav-link">
                <i class="nav-icon fas fa-tachometer-alt"></i>
                <p>Dashboard</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="rfid.php" class="nav-link">
                <i class="nav-icon fas fa-id-card"></i>
                <p>Manajemen RFID</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="kontrol.php" class="nav-link">
                <i class="nav-icon fas fa-door-open"></i>
                <p>Kontrol Pintu</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="log.php" class="nav-link">
                <i class="nav-icon fas fa-list"></i>
                <p>Log</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="export.php" class="nav-link active">
                <i class="nav-icon fas fa-file-export"></i>
                <p>Export Data</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="settings.php" class="nav-link">
                <i class="nav-icon fas fa-cog"></i>
                <p>Pengaturan Sistem</p>
              </a>
            </li>
            <li class="nav-header">ACCOUNT</li>
            <li class="nav-item">
              <a href="logout.php" class="nav-link">
                <i class="nav-icon fas fa-sign-out-alt"></i>
                <p>Logout</p>
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </aside>

    <div class="content-wrapper">
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

          <!-- FASE 1 - Prioritas Tinggi -->
          <h4 class="mb-3 mt-4">
            <i class="fas fa-star text-warning"></i> Data Utama
          </h4>

          <div class="row">
            <!-- 1. Export Log RFID -->
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
                      <input
                        type="text"
                        class="form-control"
                        name="daterange"
                        id="rfid_daterange"
                        placeholder="Klik untuk memilih tanggal (opsional)">
                      <small class="form-text text-muted">Kosongkan untuk export semua data</small>
                    </div>

                    <div class="form-group">
                      <label class="font-weight-bold">
                        <i class="fas fa-filter"></i> Filter Status:
                      </label>
                      <select class="form-control p-1" name="status">
                        <option value="">Semua Status</option>
                        <option value="granted">✅ Akses Diterima</option>
                        <option value="denied">❌ Akses Ditolak</option>
                      </select>
                    </div>

                    <div class="form-group mb-0">
                      <label class="font-weight-bold">
                        <i class="fas fa-file-download"></i> Pilih Format:
                      </label>
                      <div class="d-flex  gap-2">
                        <button type="submit" name="format" value="excel" class="btn btn-success flex-fill mx-2  shadow-sm">
                          <i class="fas fa-file-excel"></i> Excel (.xlsx)
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-danger flex-fill mx-2 shadow-sm">
                          <i class="fas fa-file-pdf"></i> PDF
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- 2. Export Log DHT -->
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
                      <input
                        type="text"
                        class="form-control"
                        name="daterange"
                        id="dht_daterange"
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
                        <button type="submit" name="format" value="excel" class="btn btn-success mx-2 flex-fill shadow-sm">
                          <i class="fas fa-file-excel"></i> Excel (.xlsx)
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-danger mx-2 flex-fill shadow-sm">
                          <i class="fas fa-file-pdf"></i> PDF
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <!-- FASE 2 - Opsional -->
          <h4 class="mb-3 mt-5">
            <i class="fas fa-star-half-alt text-info"></i> Data Pendukung
          </h4>

          <div class="row">
            <!-- 3. Export Log Status Pintu -->
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
                      <input
                        type="text"
                        class="form-control"
                        name="daterange"
                        id="door_daterange"
                        placeholder="Klik untuk memilih tanggal (opsional)">
                      <small class="form-text text-muted">Kosongkan untuk export semua data</small>
                    </div>

                    <div class="form-group">
                      <label class="font-weight-bold">
                        <i class="fas fa-filter"></i> Filter Status:
                      </label>
                      <select class="form-control p-1" name="status">
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
                        <button type="submit" name="format" value="excel" class="btn btn-success mx-2 flex-fill shadow-sm">
                          <i class="fas fa-file-excel"></i> Excel (.xlsx)
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-danger mx-2 flex-fill shadow-sm">
                          <i class="fas fa-file-pdf"></i> PDF
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- 4. Export Daftar Kartu RFID -->
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
                    <i class="fas fa-info-circle"></i> Export daftar kartu RFID yang terdaftar di sistem (untuk backup).
                  </p>

                  <form action="api/export_cards.php" method="GET" target="_blank">
                    <div class="alert alert-secondary border-left-info shadow-sm">
                      <i class="fas fa-database"></i> <strong>Database Backup:</strong><br>
                      Export ini akan menghasilkan file berisi seluruh daftar kartu RFID terdaftar beserta informasi pemilik dan admin yang menambahkan.
                    </div>

                    <div class="form-group mb-0">
                      <label class="font-weight-bold">
                        <i class="fas fa-file-download"></i> Pilih Format:
                      </label>
                      <div class="d-flex gap-2">
                        <button type="submit" name="format" value="excel" class="btn btn-success mx-2 flex-fill shadow-sm">
                          <i class="fas fa-file-excel"></i> Excel (.xlsx)
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-danger mx-2 flex-fill shadow-sm">
                          <i class="fas fa-file-pdf"></i> PDF
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

        </div>
      </section>
    </div>
    <!-- Footer -->
    <footer class="main-footer">
      <strong>Copyright &copy; <?= date('Y') ?> <a href="index.php">Koneksi Pintar</a>.</strong>

      <div class="float-right d-none d-sm-inline-block">
        <b>Version</b> 1.0.0
      </div>
    </footer>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/moment/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
  <script>
    $(function() {
      // Initialize daterangepicker
      const datePickerOptions = {
        autoUpdateInput: false,
        locale: {
          cancelLabel: 'Clear',
          format: 'YYYY-MM-DD',
          separator: ' s/d ',
          applyLabel: 'Terapkan',
          cancelLabel: 'Batal',
          fromLabel: 'Dari',
          toLabel: 'Sampai',
          customRangeLabel: 'Custom',
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

      $('#rfid_daterange, #dht_daterange, #door_daterange').daterangepicker(datePickerOptions);

      $('#rfid_daterange, #dht_daterange, #door_daterange').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' s/d ' + picker.endDate.format('YYYY-MM-DD'));
      });

      $('#rfid_daterange, #dht_daterange, #door_daterange').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
      });
    });

    // Real-time Clock Update (Waktu Indonesia)
    function updateClock() {
      const now = new Date();
      const options = {
        timeZone: 'Asia/Jakarta',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      };
      const formattedTime = now.toLocaleString('id-ID', options);
      $('#current-time').text(formattedTime);
    }
    updateClock();
    setInterval(updateClock, 1000);
  </script>
</body>

</html>
<?php $conn->close(); ?>