<?php

/**
 * Dashboard Page
 * Main dashboard with real-time monitoring
 */

session_start();

// Include core files
require_once 'core/Auth.php';
require_once 'config/config.php';
require_once 'config/config_helper.php';

// Check authentication
Auth::check();

// Load MQTT config
$mqttCredentials = getMqttCredentials();
$mqttBroker = getConfig('mqtt_broker');
$deviceSerial = getDeviceSerial();
$mqttProtocol = getConfig('mqtt_protocol', 'wss');

// Prepare MQTT config for JavaScript
$mqttConfig = [
  'broker' => $mqttBroker,
  'username' => $mqttCredentials['username'],
  'password' => $mqttCredentials['password'],
  'serial' => $deviceSerial,
  'protocol' => $mqttProtocol
];

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM rfid_cards");
$stmt->execute();
$total_cards = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM rfid_logs WHERE DATE(access_time) = CURDATE()");
$stmt->execute();
$today_access = $stmt->get_result()->fetch_assoc()['total'];

// Page configuration
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$pageCSS = [];
$pageJS = [
  'https://cdn.jsdelivr.net/npm/chart.js',
  'assets/js/pages/dashboard.js'
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <?php
  require_once 'components/layout/head.php';
  renderHead($pageTitle, $pageCSS);
  ?>
  <style>
    /* Chart Maximize Mode Enhancement - HANYA untuk card chart */
    #chartCard.maximized-card .card-body {
      padding: 30px !important;
      overflow-y: auto !important;
      max-height: calc(100vh - 180px) !important;
      background: linear-gradient(to bottom, #f8f9fa 0%, #ffffff 100%) !important;
    }

    #chartCard.maximized-card .card-header {
      position: sticky !important;
      top: 0 !important;
      z-index: 1050 !important;
      background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
      border-bottom: 3px solid rgba(255, 255, 255, 0.4) !important;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15) !important;
      padding: 16px 30px !important;
    }

    #chartCard.maximized-card .card-header .card-title {
      color: white !important;
      font-size: 1.5rem !important;
      font-weight: 600 !important;
      text-shadow: 0 2px 6px rgba(0, 0, 0, 0.3) !important;
      margin: 0 !important;
      letter-spacing: 0.3px !important;
    }

    #chartCard.maximized-card .card-header .card-title i {
      display: none !important;
    }

    /* Hide hanya tombol collapse, biarkan maximize tetap tampil */
    #chartCard.maximized-card .card-header .card-tools [data-card-widget="collapse"] {
      display: none !important;
    }

    /* Sembunyikan tombol maximize di header karena ada floating button */
    #chartCard.maximized-card .card-header .card-tools [data-card-widget="maximize"] {
      display: none !important;
    }

    #chartCard.maximized-card .card-footer {
      position: sticky !important;
      bottom: 0 !important;
      z-index: 1050 !important;
      background: linear-gradient(to top, rgba(255, 255, 255, 0.98), rgba(248, 249, 250, 0.95)) !important;
      border-top: 2px solid rgba(0, 123, 255, 0.2) !important;
      box-shadow: 0 -3px 12px rgba(0, 0, 0, 0.08) !important;
      padding: 12px 30px !important;
      backdrop-filter: blur(12px) !important;
    }

    #chartCard.maximized-card #chartWrapper {
      height: calc(100vh - 240px) !important;
      min-height: 450px !important;
      padding: 10px !important;
      background: white !important;
      border-radius: 8px !important;
      box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.05) !important;
    }

    #chartCard.maximized-card canvas {
      max-height: 100% !important;
    }

    /* Better chart visibility */
    #dhtChart {
      max-width: 100%;
      height: 100% !important;
    }

    /* Enhanced: Tombol maximize floating dengan desain modern - HANYA untuk chart card */
    #chartCard.maximized-card [data-card-widget="maximize"] {
      position: fixed !important;
      bottom: 30px !important;
      right: 30px !important;
      z-index: 9999 !important;

      /* Modern Gradient Background */
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;

      /* Perfect Circle */
      width: 60px !important;
      height: 60px !important;
      border-radius: 50% !important;

      /* Centering */
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;

      /* Shadow & Border */
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.5),
        0 4px 12px rgba(0, 0, 0, 0.3) !important;
      border: 3px solid rgba(255, 255, 255, 0.4) !important;

      /* Smooth Transition */
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;

      /* Text/Icon */
      color: white !important;
      cursor: pointer !important;

      /* Pastikan tidak transparent */
      opacity: 1 !important;
    }

    /* Pulse Animation for Attention */
    #chartCard.maximized-card [data-card-widget="maximize"]::before {
      content: '' !important;
      position: absolute !important;
      top: -2px !important;
      left: -2px !important;
      right: -2px !important;
      bottom: -2px !important;
      border-radius: 50% !important;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      z-index: -1 !important;
      opacity: 0 !important;
      animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite !important;
    }

    @keyframes pulse-ring {
      0% {
        transform: scale(1);
        opacity: 0.8;
      }

      50% {
        transform: scale(1.15);
        opacity: 0.3;
      }

      100% {
        transform: scale(1.3);
        opacity: 0;
      }
    }

    /* Enhanced Hover Effect */
    #chartCard.maximized-card [data-card-widget="maximize"]:hover {
      transform: scale(1.1) rotate(90deg) !important;
      background: linear-gradient(135deg, #764ba2 0%, #667eea 100%) !important;
      box-shadow: 0 12px 32px rgba(102, 126, 234, 0.6),
        0 6px 16px rgba(118, 75, 162, 0.4) !important;
    }

    /* Active/Click Effect */
    #chartCard.maximized-card [data-card-widget="maximize"]:active {
      transform: scale(0.95) !important;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
    }

    /* Icon Styling */
    #chartCard.maximized-card [data-card-widget="maximize"] i {
      font-size: 1.6rem !important;
      filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.3)) !important;
      transition: transform 0.3s ease !important;
    }

    /* Tombol collapse tetap di header (hidden saat maximize) - HANYA untuk chart card */
    #chartCard.maximized-card [data-card-widget="collapse"] {
      display: none !important;
    }

    /* Normal mode - card tidak maximize */
    #chartCard:not(.maximized-card) #chartWrapper {
      height: 400px !important;
    }

    /* Smooth transition untuk chart wrapper */
    #chartWrapper {
      transition: height 0.35s cubic-bezier(0.4, 0, 0.2, 1) !important;
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
      <div class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1 class="m-0"><i class="fas fa-tachometer-alt text-primary"></i> Dashboard Real-time</h1>
              <p class="text-muted mb-0">Monitor sistem smart home secara real-time</p>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li class="breadcrumb-item active">Dashboard</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Row 1: All Status Cards (ESP32, Door, Temperature, Humidity, Fan) - 5 Columns -->
          <div class="row">
            <div class="col-lg col-md-6 col-sm-6 mb-4">
              <div class="small-box bg-danger fade-in">
                <div class="inner">
                  <h3 id="esp_status">Offline</h3>
                  <p>Status ESP32</p>
                </div>
                <div class="icon"><i class="fas fa-microchip"></i></div>
                <div class="small-box-footer">
                  <span id="esp_connection_time">Menghubungkan...</span>
                </div>
              </div>
            </div>

            <div class="col-lg col-md-6 col-sm-6 mb-4">
              <div class="small-box bg-success fade-in">
                <div class="inner">
                  <h3 id="door_status">Tertutup</h3>
                  <p>Status Pintu</p>
                </div>
                <div class="icon"><i class="fas fa-door-closed" id="door_icon"></i></div>
                <div class="small-box-footer">
                  <span id="door_last_update">-</span>
                </div>
              </div>
            </div>

            <div class="col-lg col-md-6 col-sm-6 mb-4">
              <div class="small-box bg-warning fade-in">
                <div class="inner">
                  <h3 id="temperature">-</h3>
                  <p>Suhu (°C)</p>
                </div>
                <div class="icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="small-box-footer">
                  <span id="temp_status">Menunggu data...</span>
                </div>
              </div>
            </div>

            <div class="col-lg col-md-6 col-sm-6 mb-4">
              <div class="small-box bg-info fade-in">
                <div class="inner">
                  <h3 id="humidity">-</h3>
                  <p>Kelembapan (%)</p>
                </div>
                <div class="icon"><i class="fas fa-tint"></i></div>
                <div class="small-box-footer">
                  <span id="humidity_status">Menunggu data...</span>
                </div>
              </div>
            </div>

            <div class="col-lg col-md-6 col-sm-6 mb-4">
              <div class="small-box bg-success fade-in" id="fan_card">
                <div class="inner">
                  <h3><i class="fas fa-fan" id="fan_icon_dashboard"></i> <span id="fan_status_text">OFF</span></h3>
                  <p>Status Kipas</p>
                </div>
                <div class="icon"><i class="fas fa-fan"></i></div>
                <a href="kipas.php" class="small-box-footer">
                  <span id="fan_mode_text">Mode: -</span> <i class="fas fa-arrow-circle-right"></i>
                </a>
              </div>
            </div>
          </div>

          <!-- Row 2: RFID Last Access & Statistics -->
          <div class="row">
            <!-- RFID Last Access Info -->
            <div class="col-lg-6 mb-4">
              <div class="card card-primary card-outline shadow-sm hover-shadow fade-in h-100">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-id-card"></i> Akses RFID Terakhir
                  </h3>
                  <div class="card-tools">
                    <button type="button" class="btn btn-tool" onclick="loadLastRFID()">
                      <i class="fas fa-sync"></i>
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="info-box mb-3 bg-light shadow-sm">
                    <span class="info-box-icon bg-info">
                      <i class="fas fa-fingerprint"></i>
                    </span>
                    <div class="info-box-content">
                      <span class="info-box-text">UID Kartu</span>
                      <span class="info-box-number" id="last_rfid">-</span>
                    </div>
                  </div>

                  <div class="info-box mb-3 bg-light shadow-sm">
                    <span class="info-box-icon" style="background-color: #6f42c1; color: white;">
                      <i class="fas fa-user"></i>
                    </span>
                    <div class="info-box-content">
                      <span class="info-box-text">Nama Pengguna</span>
                      <span class="info-box-number" id="last_rfid_name">-</span>
                    </div>
                  </div>

                  <div class="info-box mb-0 bg-light shadow-sm">
                    <span class="info-box-icon bg-warning">
                      <i class="far fa-clock"></i>
                    </span>
                    <div class="info-box-content">
                      <span class="info-box-text">Waktu Akses</span>
                      <span class="info-box-number" id="last_rfid_time" style="font-size: 1rem;">-</span>
                      <span id="last_rfid_status" class="badge badge-secondary mt-1">-</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Statistics Cards -->
            <div class="col-lg-6 mb-4">
              <div class="card card-success card-outline shadow-sm hover-shadow fade-in h-100">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i> Statistik Sistem
                  </h3>
                </div>
                <div class="card-body">
                  <div class="info-box mb-3 shadow-sm">
                    <span class="info-box-icon bg-primary">
                      <i class="fas fa-id-card"></i>
                    </span>
                    <div class="info-box-content">
                      <span class="info-box-text">Total Kartu Terdaftar</span>
                      <span class="info-box-number"><?= number_format($total_cards) ?></span>
                      <div class="progress">
                        <div class="progress-bar bg-primary" style="width: 100%"></div>
                      </div>
                      <span class="progress-description">Kartu RFID aktif dalam sistem</span>
                    </div>
                  </div>

                  <div class="info-box mb-0 shadow-sm">
                    <span class="info-box-icon bg-success">
                      <i class="fas fa-calendar-day"></i>
                    </span>
                    <div class="info-box-content">
                      <span class="info-box-text">Akses Hari Ini</span>
                      <span class="info-box-number"><?= number_format($today_access) ?></span>
                      <div class="progress">
                        <div class="progress-bar bg-success" style="width: 70%"></div>
                      </div>
                      <span class="progress-description">Aktivitas akses pada <?= date('d/m/Y') ?></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Row 3: Chart Full Width -->
          <div class="row">
            <div class="col-12">
              <div class="card card-primary shadow-md fade-in" id="chartCard">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-chart-line mr-2"></i>Grafik Suhu & Kelembapan Real-time
                  </h3>
                  <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                      <i class="fas fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-tool" data-card-widget="maximize">
                      <i class="fas fa-expand"></i>
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div style="position: relative; height: 400px;" id="chartWrapper">
                    <canvas id="dhtChart"></canvas>
                  </div>
                </div>
                <div class="card-footer bg-white">
                  <small class="text-muted">
                    <i class="far fa-clock"></i> Update otomatis setiap data baru diterima
                  </small>
                </div>
              </div>
            </div>
          </div>

        </div>
      </section>
    </div>

    <?php
    require_once 'components/layout/footer.php';
    renderFooter($pageJS, $mqttConfig);
    ?>

  </div>
</body>

</html>