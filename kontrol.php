<?php

/**
 * Door Control Page
 * Control servo door with RFID and manual control
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

// Page configuration
$pageTitle = 'Kontrol Pintu';
$activePage = 'kontrol';
$pageCSS = [];
$pageJS = ['assets/js/pages/door.js'];
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
              <h1>
                <i class="fas fa-door-open text-primary"></i> Kontrol Pintu Servo
              </h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Kontrol</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Alert Info -->
          <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <h5><i class="icon fas fa-info-circle"></i> Informasi</h5>
            Kontrol pintu servo secara manual atau otomatis melalui MQTT. Status akan tersinkronisasi real-time dengan ESP32.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <!-- Status Cards -->
          <div class="row">
            <div class="col-lg-6 col-12">
              <div class="card card-info card-outline shadow-sm hover-shadow fadeIn">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-door-open"></i> Status Pintu Real-time
                  </h3>
                </div>
                <div class="card-body text-center p-4">
                  <div id="doorStatus" style="font-size: 4em; margin: 30px 0;">
                    <i id="doorIcon" class="fas fa-door-closed text-secondary"></i>
                  </div>
                  <h2 id="doorText" class="font-weight-bold mb-2">Tertutup</h2>
                  <p class="text-muted mb-0">
                    <i class="fas fa-sync-alt"></i> Status dari ESP32
                  </p>
                </div>
              </div>
            </div>

            <div class="col-lg-6 col-12">
              <div class="card card-success card-outline shadow-sm hover-shadow fadeIn">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-microchip"></i> Status Koneksi ESP32
                  </h3>
                </div>
                <div class="card-body text-center p-4">
                  <div id="espStatus" style="font-size: 4em; margin: 30px 0;">
                    <i class="fas fa-circle text-danger pulse"></i>
                  </div>
                  <h2 id="espText" class="font-weight-bold mb-2 text-danger">Offline</h2>
                  <p class="text-muted mb-0">
                    <i class="fas fa-wifi"></i> Koneksi MQTT Broker
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Kontrol Manual Pintu -->
          <div class="card card-primary card-outline shadow-sm hover-shadow fadeIn mt-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-hand-pointer"></i> Kontrol Manual Pintu
              </h3>
            </div>
            <div class="card-body">
              <div class="row text-center">
                <div class="col-md-6 mb-3">
                  <button class="btn btn-success btn-lg btn-block shadow-sm" id="btnOpen" style="padding: 20px;">
                    <i class="fas fa-door-open" style="font-size: 2em;"></i>
                    <h4 class="mt-2 mb-0">Buka Pintu</h4>
                    <small class="text-white-50">Servo 90°</small>
                  </button>
                </div>
                <div class="col-md-6 mb-3">
                  <button class="btn btn-danger btn-lg btn-block shadow-sm" id="btnClose" style="padding: 20px;">
                    <i class="fas fa-door-closed" style="font-size: 2em;"></i>
                    <h4 class="mt-2 mb-0">Tutup Pintu</h4>
                    <small class="text-white-50">Servo 0°</small>
                  </button>
                </div>
              </div>
              <div id="doorResult" class="mt-3"></div>
            </div>
          </div>

          <!-- Door Activity Logs -->
          <div class="card card-warning card-outline shadow-sm hover-shadow fadeIn mt-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-history"></i> Riwayat Aktivitas Pintu
              </h3>
              <div class="card-tools">
                <button type="button" class="btn btn-tool" onclick="loadDoorLogs()">
                  <i class="fas fa-sync"></i>
                </button>
                <span class="badge badge-warning">20 Terakhir</span>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover table-striped modern-table" id="tableDoorLogs">
                  <thead>
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
    renderFooter($pageJS, $mqttConfig);
    ?>

  </div>
</body>

</html>