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

    /* Card header hover effect */
    .card-header-clickable {
      cursor: pointer;
      transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }

    .card-header-clickable:hover {
      background-color: rgba(0, 0, 0, 0.02);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }

    .card-header-clickable:active {
      transform: scale(0.99);
    }

    /* Smooth card transitions */
    .card {
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card.active-card {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
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
              <h1 class="fadeInLeft">
                <i class="fas fa-history text-primary"></i> Log Akses & Sensor
              </h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Log</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Statistics Row -->
          <div class="row">
            <div class="col-lg-3 col-md-6 col-12">
              <div class="info-box shadow-sm hover-shadow fadeIn" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                <span class="info-box-icon" style="background: rgba(255,255,255,0.2);">
                  <i class="fas fa-id-card"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text" style="color: rgba(255,255,255,0.9);">Total Akses RFID</span>
                  <span class="info-box-number" id="totalRfidLogs">-</span>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
              <div class="info-box shadow-sm hover-shadow fadeIn" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <span class="info-box-icon" style="background: rgba(255,255,255,0.2);">
                  <i class="fas fa-thermometer-half"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text" style="color: rgba(255,255,255,0.9);">Suhu Rata-rata</span>
                  <span class="info-box-number" id="avgTemp">-</span>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
              <div class="info-box shadow-sm hover-shadow fadeIn" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <span class="info-box-icon" style="background: rgba(255,255,255,0.2);">
                  <i class="fas fa-tint"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text" style="color: rgba(255,255,255,0.9);">Kelembapan Rata-rata</span>
                  <span class="info-box-number" id="avgHum">-</span>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
              <div class="info-box shadow-sm hover-shadow fadeIn" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                <span class="info-box-icon" style="background: rgba(255,255,255,0.2);">
                  <i class="fas fa-door-open"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text" style="color: rgba(255,255,255,0.9);">Perubahan Pintu</span>
                  <span class="info-box-number" id="doorChanges">-</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Accordion for Logs -->
          <div id="accordionLogs">

            <!-- Log RFID Access -->
            <div class="card card-primary card-outline shadow-sm hover-shadow fadeIn mt-4 active-card" id="cardRfid">
              <div class="card-header card-header-clickable" onclick="toggleCard('collapseRfid', 'cardRfid')">
                <h3 class="card-title">
                  <i class="fas fa-id-card"></i> Log Akses RFID
                </h3>
                <div class="card-tools">
                  <span class="badge badge-info mr-2" id="rfidCount">0 records</span>
                  <button type="button" class="btn btn-tool">
                    <i class="fas fa-minus" id="iconRfid"></i>
                  </button>
                </div>
              </div>
              <div class="card-body-accordion show" id="collapseRfid">
                <div class="card-body">
                  <!-- Filter Section -->
                  <div class="card card-secondary card-outline mb-3">
                    <div class="card-body">
                      <div class="row align-items-end">
                        <div class="col-md-5">
                          <div class="form-group mb-0">
                            <label class="d-block"><i class="fas fa-filter"></i> Filter Status:</label>
                            <select class="form-control p-1" id="filterRfidStatus">
                              <option value="">Semua Status</option>
                              <option value="granted">✅ Akses Diterima</option>
                              <option value="denied">❌ Akses Ditolak</option>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-5">
                          <div class="form-group mb-0">
                            <label class="d-block"><i class="fas fa-search"></i> Cari UID/Nama:</label>
                            <input type="text" class="form-control" id="searchRfid" placeholder="Ketik untuk mencari...">
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="form-group mb-0">
                            <button class="btn btn-primary btn-block shadow-sm" onclick="loadRFIDLog()">
                              <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="table-responsive scrollable-table">
                    <table class="table table-hover table-striped modern-table mb-0" id="tableRFIDLog">
                      <thead class="thead-sticky">
                        <tr>
                          <th width="50" class="text-center">#</th>
                          <th><i class="fas fa-fingerprint"></i> UID Kartu</th>
                          <th><i class="fas fa-user"></i> Nama Pengguna</th>
                          <th><i class="far fa-clock"></i> Waktu Akses</th>
                          <th width="150" class="text-center"><i class="fas fa-check-circle"></i> Status</th>
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
            </div>

            <!-- Log DHT Sensor -->
            <div class="card card-success card-outline shadow-sm hover-shadow fadeIn mt-4" id="cardDht">
              <div class="card-header card-header-clickable" onclick="toggleCard('collapseDht', 'cardDht')">
                <h3 class="card-title">
                  <i class="fas fa-thermometer-half"></i> Log Suhu & Kelembapan
                </h3>
                <div class="card-tools">
                  <span class="badge badge-info mr-2" id="dhtCount">0 records</span>
                  <button type="button" class="btn btn-tool">
                    <i class="fas fa-plus" id="iconDht"></i>
                  </button>
                </div>
              </div>
              <div class="card-body-accordion" id="collapseDht">
                <div class="card-body">
                  <!-- Filter Section -->
                  <div class="card card-secondary card-outline mb-3">
                    <div class="card-body">
                      <div class="row align-items-end">
                        <div class="col-md-3">
                          <div class="form-group mb-0">
                            <label class="d-block"><i class="fas fa-clock"></i> Rentang Waktu:</label>
                            <select class="form-control p-1" id="filterDHTTime">
                              <option value="30">🕐 30 Menit Terakhir</option>
                              <option value="60" selected>🕑 1 Jam Terakhir</option>
                              <option value="180">🕒 3 Jam Terakhir</option>
                              <option value="360">🕕 6 Jam Terakhir</option>
                              <option value="1440">📅 24 Jam Terakhir</option>
                              <option value="10080">📆 7 Hari Terakhir</option>
                              <option value="all">📊 Semua Data</option>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="form-group mb-0">
                            <label class="d-block"><i class="fas fa-temperature-low"></i> Suhu Min:</label>
                            <input type="number" class="form-control" id="filterTempMin" placeholder="0">
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="form-group mb-0">
                            <label class="d-block"><i class="fas fa-temperature-high"></i> Suhu Max:</label>
                            <input type="number" class="form-control" id="filterTempMax" placeholder="50">
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="form-group mb-0">
                            <label class="d-block"><i class="fas fa-tint"></i> Lembap Min:</label>
                            <input type="number" class="form-control" id="filterHumMin" placeholder="0">
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group mb-0">
                            <button class="btn btn-primary btn-block shadow-sm" onclick="loadDHTLog()">
                              <i class="fas fa-sync-alt"></i> Terapkan Filter
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="table-responsive scrollable-table">
                    <table class="table table-hover table-striped modern-table mb-0" id="tableDHTLog">
                      <thead class="thead-sticky">
                        <tr>
                          <th width="50" class="text-center">#</th>
                          <th><i class="far fa-clock"></i> Waktu</th>
                          <th width="180"><i class="fas fa-thermometer-half"></i> Suhu (°C)</th>
                          <th width="180"><i class="fas fa-tint"></i> Kelembapan (%)</th>
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
                  <div class="card-footer bg-light">
                    <small class="text-muted">
                      <i class="fas fa-info-circle"></i>
                      Menampilkan: <span id="dhtInfo" class="font-weight-bold">-</span>
                    </small>
                  </div>
                </div>
              </div>
            </div>

            <!-- Log Door Status -->
            <div class="card card-warning card-outline shadow-sm hover-shadow fadeIn mt-4 mb-4" id="cardDoor">
              <div class="card-header card-header-clickable" onclick="toggleCard('collapseDoor', 'cardDoor')">
                <h3 class="card-title">
                  <i class="fas fa-door-open"></i> Log Status Pintu
                </h3>
                <div class="card-tools">
                  <span class="badge badge-info mr-2" id="doorCount">0 records</span>
                  <button type="button" class="btn btn-tool">
                    <i class="fas fa-plus" id="iconDoor"></i>
                  </button>
                </div>
              </div>
              <div class="card-body-accordion" id="collapseDoor">
                <div class="card-body">
                  <!-- Filter Section -->
                  <div class="card card-secondary card-outline mb-3">
                    <div class="card-body">
                      <div class="row align-items-end">
                        <div class="col-md-8">
                          <div class="form-group mb-0">
                            <label class="d-block"><i class="fas fa-filter"></i> Filter Status Pintu:</label>
                            <select class="form-control p-1" id="filterDoorStatus">
                              <option value="">Semua Status</option>
                              <option value="terbuka">🔓 Terbuka</option>
                              <option value="tertutup">🔒 Tertutup</option>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-group mb-0">
                            <button class="btn btn-primary btn-block shadow-sm" onclick="loadDoorLog()">
                              <i class="fas fa-sync-alt"></i> Terapkan Filter
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="table-responsive scrollable-table">
                    <table class="table table-hover table-striped modern-table mb-0" id="tableDoorLog">
                      <thead class="thead-sticky">
                        <tr>
                          <th width="50" class="text-center">#</th>
                          <th><i class="far fa-clock"></i> Waktu</th>
                          <th width="200" class="text-center"><i class="fas fa-door-open"></i> Status Pintu</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td colspan="3" class="text-center text-muted">
                            <i class="fas fa-spinner fa-spin"></i> Memuat data...
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- End accordionLogs -->

          <?php
          require_once 'components/layout/footer.php';
          renderFooter($pageJS);
          ?>

        </div>
</body>

</html>