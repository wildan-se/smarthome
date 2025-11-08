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
              <h1 class="m-0"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item active">Dashboard</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Row 1: Status Cards (ESP32, Door, Temperature, Humidity, Fan) -->
          <div class="row">
            <!-- ESP32 Status -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <?php
              require_once 'components/cards/status-card.php';
              renderStatusCard([
                'id' => 'esp32_card',
                'title' => 'ESP32 Status',
                'value' => '<span id="esp_status">Offline</span>',
                'icon' => 'microchip',
                'color' => 'danger',
                'footer' => '<small id="esp_connection_time">Menghubungkan...</small>'
              ]);
              ?>
            </div>

            <!-- Door Status -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <?php
              renderStatusCard([
                'id' => 'door_card',
                'title' => 'Status Pintu',
                'value' => '<span id="door_status">Tertutup</span>',
                'icon' => 'door-closed',
                'color' => 'success',
                'footer' => '<small id="door_last_update">Waiting...</small>'
              ]);
              ?>
              <i class="fas fa-door-closed" id="door_icon" style="display:none;"></i>
            </div>

            <!-- Temperature -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <?php
              renderStatusCard([
                'title' => 'Suhu',
                'value' => '<span id="temperature">0.0</span>°C',
                'icon' => 'thermometer-half',
                'color' => 'warning',
                'footer' => '<small id="temp_status">Waiting...</small>'
              ]);
              ?>
            </div>

            <!-- Humidity -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <?php
              renderStatusCard([
                'title' => 'Kelembapan',
                'value' => '<span id="humidity">0.0</span>%',
                'icon' => 'tint',
                'color' => 'info',
                'footer' => '<small id="hum_status">Waiting...</small>'
              ]);
              ?>
            </div>

            <!-- Fan Status -->
            <div class="col-lg-4 col-md-8 col-sm-12 mb-3">
              <?php
              renderStatusCard([
                'id' => 'fan_card',
                'title' => 'Status Kipas',
                'value' => '<i class="fas fa-fan" id="fan_icon_dashboard"></i> <span id="fan_status_text">OFF</span>',
                'icon' => 'fan',
                'color' => 'danger',
                'footer' => '<small id="fan_mode_text">Mode: -</small>',
                'link' => 'kipas.php'
              ]);
              ?>
            </div>
          </div>

          <!-- Row 2: RFID Info & Statistics -->
          <div class="row">
            <!-- RFID Last Access -->
            <div class="col-lg-6 col-md-12 mb-4">
              <div class="card card-info shadow-md fade-in h-100">
                <div class="card-header">
                  <h3 class="card-title text-dark">
                    <i class="fas fa-id-card mr-2"></i>Akses RFID Terakhir
                  </h3>
                </div>
                <div class="card-body">
                  <?php
                  renderInfoBox([
                    'icon' => 'fingerprint',
                    'iconColor' => 'info',
                    'text' => 'UID Kartu',
                    'number' => '<span id="last_rfid">-</span>',
                    'class' => 'mb-3 shadow-sm'
                  ]);

                  renderInfoBox([
                    'icon' => 'user',
                    'iconColor' => 'purple',
                    'text' => 'Nama Pengguna',
                    'number' => '<span id="last_rfid_name">-</span>',
                    'class' => 'mb-3 shadow-sm'
                  ]);

                  renderInfoBox([
                    'icon' => 'clock',
                    'iconColor' => 'warning',
                    'text' => 'Waktu & Status',
                    'number' => '<div id="last_rfid_time" class="mb-1">-</div><span id="last_rfid_status" class="badge badge-secondary">-</span>',
                    'class' => 'mb-0 shadow-sm'
                  ]);
                  ?>
                </div>
              </div>
            </div>

            <!-- Statistics -->
            <div class="col-lg-6 col-md-12 mb-4">
              <div class="card card-success shadow-md fade-in h-100">
                <div class="card-header">
                  <h3 class="card-title text-dark">
                    <i class="fas fa-chart-bar mr-2"></i>Statistik Sistem
                  </h3>
                </div>
                <div class="card-body">
                  <?php
                  renderInfoBox([
                    'icon' => 'id-card-alt',
                    'iconColor' => 'primary',
                    'text' => 'Total Kartu Terdaftar',
                    'number' => number_format($total_cards),
                    'class' => 'mb-3 shadow-sm'
                  ]);

                  renderInfoBox([
                    'icon' => 'history',
                    'iconColor' => 'success',
                    'text' => 'Akses Hari Ini',
                    'number' => number_format($today_access),
                    'class' => 'mb-0 shadow-sm'
                  ]);
                  ?>
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
                    <span class="badge badge-light">Update setiap 10 detik</span>
                  </div>
                </div>
                <div class="card-body">
                  <canvas id="dhtChart" style="height: 300px;"></canvas>
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