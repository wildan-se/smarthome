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
              <h1><i class="fas fa-door-open"></i> Kontrol Pintu Servo</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Kontrol Pintu</li>
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
            Pintu dapat dibuka/ditutup secara otomatis dengan kartu RFID atau manual melalui tombol di bawah ini.
            <button type="button" class="close" data-dismiss="alert">
              <span>&times;</span>
            </button>
          </div>

          <!-- Result Messages -->
          <div id="doorResult"></div>

          <!-- Row 1: Door Status Card -->
          <div class="row">
            <div class="col-lg-6 mx-auto mb-4">
              <?php
              require_once 'components/cards/status-card.php';
              renderStatusCard([
                'id' => 'doorCard',
                'title' => 'Status Pintu',
                'value' => '<i class="fas fa-door-closed" id="doorIcon"></i> <span id="doorStatusText">TERTUTUP</span>',
                'icon' => 'door-closed',
                'color' => 'success',
                'footer' => '<small id="lastUpdate">Waiting...</small>'
              ]);
              ?>
            </div>
          </div>

          <!-- Row 2: Control Buttons -->
          <div class="row">
            <div class="col-12">
              <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-hand-pointer"></i> Kontrol Manual
                  </h3>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <button type="button" class="btn btn-warning btn-lg btn-block shadow" id="btnOpen">
                        <i class="fas fa-door-open fa-2x"></i>
                        <br>
                        <strong>BUKA PINTU</strong>
                        <br>
                        <small>Buka pintu servo</small>
                      </button>
                    </div>
                    <div class="col-md-6 mb-3">
                      <button type="button" class="btn btn-success btn-lg btn-block shadow" id="btnClose">
                        <i class="fas fa-door-closed fa-2x"></i>
                        <br>
                        <strong>TUTUP PINTU</strong>
                        <br>
                        <small>Tutup pintu servo</small>
                      </button>
                    </div>
                  </div>

                  <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Perhatian:</strong> Pastikan tidak ada halangan saat mengontrol pintu.
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Row 3: Door Activity Logs -->
          <div class="row">
            <div class="col-12">
              <div class="card card-info card-outline shadow-sm">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-history"></i> Riwayat Aktivitas Pintu
                  </h3>
                  <div class="card-tools">
                    <button type="button" class="btn btn-tool" onclick="loadDoorLogs()">
                      <i class="fas fa-sync"></i>
                    </button>
                    <span class="badge badge-info">20 Terakhir</span>
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