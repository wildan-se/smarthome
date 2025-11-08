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
              <div class="card card-primary shadow-md fade-in">
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
                  <canvas id="dhtChart" height="60"></canvas>
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