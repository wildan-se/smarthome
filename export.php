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
$pageCSS = [];
$pageJS = ['assets/js/pages/export.js'];
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
              <h1><i class="fas fa-download"></i> Export Data</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Export Data</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Info Alert -->
          <div class="alert alert-info alert-dismissible fade show shadow-sm">
            <h5><i class="icon fas fa-info-circle"></i> Informasi</h5>
            Export data sistem ke format CSV untuk backup atau analisis lebih lanjut.
            <button type="button" class="close" data-dismiss="alert">
              <span>&times;</span>
            </button>
          </div>

          <!-- Export Cards Row -->
          <div class="row">

            <!-- RFID Cards Export -->
            <div class="col-lg-6 mb-4">
              <div class="card card-primary card-outline shadow-sm h-100">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-id-card"></i> Kartu RFID Terdaftar
                  </h3>
                </div>
                <div class="card-body">
                  <div class="info-box bg-light">
                    <span class="info-box-icon bg-primary">
                      <i class="fas fa-id-card"></i>
                    </span>
                    <div class="info-box-content">
                      <span class="info-box-text">Total Kartu</span>
                      <span class="info-box-number"><?= number_format($cards_count) ?></span>
                    </div>
                  </div>
                  <p class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Export semua kartu RFID yang terdaftar di sistem beserta informasi pemilik.
                  </p>
                </div>
                <div class="card-footer">
                  <button type="button" class="btn btn-primary btn-block" id="btnExportRFIDCards">
                    <i class="fas fa-download"></i> Export Kartu RFID
                  </button>
                </div>
              </div>
            </div>

            <!-- RFID Logs Export -->
            <div class="col-lg-6 mb-4">
              <div class="card card-success card-outline shadow-sm h-100">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-history"></i> Log Akses RFID
                  </h3>
                </div>
                <div class="card-body">
                  <div class="info-box bg-light">
                    <span class="info-box-icon bg-success">
                      <i class="fas fa-history"></i>
                    </span>
                    <div class="info-box-content">
                      <span class="info-box-text">Total Log</span>
                      <span class="info-box-number"><?= number_format($rfid_count) ?></span>
                    </div>
                  </div>
                  <p class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Export semua riwayat akses RFID (granted dan denied) beserta timestamp.
                  </p>
                </div>
                <div class="card-footer">
                  <button type="button" class="btn btn-success btn-block" id="btnExportRFIDLogs">
                    <i class="fas fa-download"></i> Export Log RFID
                  </button>
                </div>
              </div>
            </div>

            <!-- DHT Sensor Logs Export -->
            <div class="col-lg-6 mb-4">
              <div class="card card-warning card-outline shadow-sm h-100">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-thermometer-half"></i> Log Sensor DHT
                  </h3>
                </div>
                <div class="card-body">
                  <div class="info-box bg-light">
                    <span class="info-box-icon bg-warning">
                      <i class="fas fa-thermometer-half"></i>
                    </span>
                    <div class="info-box-content">
                      <span class="info-box-text">Total Log</span>
                      <span class="info-box-number"><?= number_format($dht_count) ?></span>
                    </div>
                  </div>
                  <p class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Export data sensor suhu dan kelembaban untuk analisis historis.
                  </p>
                </div>
                <div class="card-footer">
                  <button type="button" class="btn btn-warning btn-block" id="btnExportDHTLogs">
                    <i class="fas fa-download"></i> Export Log DHT
                  </button>
                </div>
              </div>
            </div>

            <!-- Door Activity Logs Export -->
            <div class="col-lg-6 mb-4">
              <div class="card card-info card-outline shadow-sm h-100">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-door-open"></i> Log Aktivitas Pintu
                  </h3>
                </div>
                <div class="card-body">
                  <div class="info-box bg-light">
                    <span class="info-box-icon bg-info">
                      <i class="fas fa-door-open"></i>
                    </span>
                    <div class="info-box-content">
                      <span class="info-box-text">Total Log</span>
                      <span class="info-box-number"><?= number_format($door_count) ?></span>
                    </div>
                  </div>
                  <p class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Export riwayat aktivitas pintu (buka/tutup) dan sumber kontrolnya.
                  </p>
                </div>
                <div class="card-footer">
                  <button type="button" class="btn btn-info btn-block" id="btnExportDoorLogs">
                    <i class="fas fa-download"></i> Export Log Pintu
                  </button>
                </div>
              </div>
            </div>

          </div>

          <!-- Export Information -->
          <div class="row">
            <div class="col-12">
              <div class="card card-secondary card-outline shadow-sm">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-question-circle"></i> Informasi Export
                  </h3>
                </div>
                <div class="card-body">
                  <h5><i class="fas fa-file-csv"></i> Format File</h5>
                  <p>Semua data akan diekspor dalam format <strong>CSV (Comma-Separated Values)</strong> yang dapat dibuka dengan:</p>
                  <ul>
                    <li>Microsoft Excel</li>
                    <li>Google Sheets</li>
                    <li>LibreOffice Calc</li>
                    <li>Text Editor (Notepad++, VS Code, dll)</li>
                  </ul>

                  <hr>

                  <h5><i class="fas fa-info-circle"></i> Catatan Penting</h5>
                  <ul>
                    <li>File CSV menggunakan encoding <strong>UTF-8 with BOM</strong> untuk kompatibilitas karakter Indonesia</li>
                    <li>Kolom dipisahkan dengan tanda <strong>koma (,)</strong></li>
                    <li>Format tanggal: <strong>YYYY-MM-DD HH:mm:ss</strong></li>
                    <li>File akan otomatis terunduh setelah tombol export diklik</li>
                  </ul>
                </div>
              </div>
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
</body>

</html>