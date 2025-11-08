<?php

/**
 * Log Activity Page
 * Display RFID, DHT sensor, and door activity logs
 */

session_start();

// Include core files
require_once 'core/Auth.php';
require_once 'config/config.php';

// Check authentication
Auth::check();

// Page configuration
$pageTitle = 'Log Aktivitas';
$activePage = 'log';
$pageCSS = [];
$pageJS = ['assets/js/pages/log.js'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <?php
  require_once 'components/layout/head.php';
  renderHead($pageTitle, $pageCSS);
  ?>
  <style>
    /* Scrollable table with sticky header */
    .table-responsive.scrollable-table {
      max-height: 500px;
      overflow-y: auto;
      border: 1px solid #dee2e6;
      border-radius: 0.25rem;
    }

    .table-responsive.scrollable-table::-webkit-scrollbar {
      width: 8px;
    }

    .table-responsive.scrollable-table::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .table-responsive.scrollable-table::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 10px;
    }

    .table-responsive.scrollable-table::-webkit-scrollbar-thumb:hover {
      background: #555;
    }

    .thead-sticky {
      position: sticky;
      top: 0;
      background: #f8f9fa;
      z-index: 10;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .thead-sticky th {
      border-bottom: 2px solid #dee2e6 !important;
    }

    /* Smooth accordion animations */
    .card-body-accordion {
      overflow: hidden;
      transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1),
        opacity 0.3s ease,
        padding 0.4s ease;
      max-height: 0;
      opacity: 0;
      padding: 0 1.25rem;
    }

    .card-body-accordion.show {
      max-height: 3000px;
      opacity: 1;
      padding: 1.25rem;
    }

    /* Smooth icon rotation */
    .btn-tool i {
      transition: transform 0.3s ease;
    }

    .btn-tool i.rotate {
      transform: rotate(180deg);
    }

    /* Badge enhancements */
    .badge {
      font-size: 0.875rem;
      padding: 0.35em 0.65em;
    }
  </style>
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
              <h1><i class="fas fa-history"></i> Log Aktivitas Sistem</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Log Aktivitas</li>
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
            Menampilkan riwayat aktivitas RFID, sensor DHT, dan pintu. Data diperbarui otomatis setiap 30 detik.
            <button type="button" class="close" data-dismiss="alert">
              <span>&times;</span>
            </button>
          </div>

          <!-- RFID Access Log -->
          <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-id-card"></i> Log Akses RFID
              </h3>
              <div class="card-tools">
                <button type="button" class="btn btn-tool btn-toggle-card" data-target="#rfidLogBody">
                  <i class="fas fa-chevron-down"></i>
                </button>
                <button type="button" class="btn btn-tool" onclick="loadRFIDLog()">
                  <i class="fas fa-sync"></i>
                </button>
              </div>
            </div>
            <div class="card-body card-body-accordion show" id="rfidLogBody">
              <div class="table-responsive scrollable-table">
                <table class="table table-hover table-striped modern-table" id="tableRFIDLog">
                  <thead class="thead-sticky">
                    <tr>
                      <th width="50" class="text-center">#</th>
                      <th><i class="fas fa-fingerprint"></i> UID</th>
                      <th><i class="fas fa-user"></i> Nama</th>
                      <th><i class="fas fa-shield-alt"></i> Status</th>
                      <th><i class="far fa-clock"></i> Waktu</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="5" class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Memuat data...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- DHT Sensor Log -->
          <div class="card card-success card-outline shadow-sm">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-thermometer-half"></i> Log Sensor DHT
              </h3>
              <div class="card-tools">
                <button type="button" class="btn btn-tool btn-toggle-card" data-target="#dhtLogBody">
                  <i class="fas fa-chevron-down"></i>
                </button>
                <button type="button" class="btn btn-tool" onclick="loadDHTLog()">
                  <i class="fas fa-sync"></i>
                </button>
              </div>
            </div>
            <div class="card-body card-body-accordion show" id="dhtLogBody">
              <div class="table-responsive scrollable-table">
                <table class="table table-hover table-striped modern-table" id="tableDHTLog">
                  <thead class="thead-sticky">
                    <tr>
                      <th width="50" class="text-center">#</th>
                      <th><i class="fas fa-thermometer-half"></i> Suhu</th>
                      <th><i class="fas fa-tint"></i> Kelembaban</th>
                      <th><i class="far fa-clock"></i> Waktu</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="4" class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Memuat data...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Door Activity Log -->
          <div class="card card-warning card-outline shadow-sm">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-door-open"></i> Log Aktivitas Pintu
              </h3>
              <div class="card-tools">
                <button type="button" class="btn btn-tool btn-toggle-card" data-target="#doorLogBody">
                  <i class="fas fa-chevron-down"></i>
                </button>
                <button type="button" class="btn btn-tool" onclick="loadDoorLog()">
                  <i class="fas fa-sync"></i>
                </button>
              </div>
            </div>
            <div class="card-body card-body-accordion show" id="doorLogBody">
              <div class="table-responsive scrollable-table">
                <table class="table table-hover table-striped modern-table" id="tableDoorLog">
                  <thead class="thead-sticky">
                    <tr>
                      <th width="50" class="text-center">#</th>
                      <th><i class="fas fa-door-open"></i> Status</th>
                      <th><i class="fas fa-hand-pointer"></i> Sumber</th>
                      <th><i class="far fa-clock"></i> Waktu</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="4" class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Memuat data...
                      </td>
                    </tr>
                  </tbody>
                </table>
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