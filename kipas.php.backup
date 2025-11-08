<?php

/**
 * Fan Control Page
 * Control fan with auto/manual mode and threshold settings
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
$pageTitle = 'Kontrol Kipas';
$activePage = 'kipas';
$pageCSS = [];
$pageJS = ['assets/js/pages/fan.js'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <?php
  require_once 'components/layout/head.php';
  renderHead($pageTitle, $pageCSS);
  ?>
  <style>
    /* Fan Spin Animation */
    @keyframes spin {
      from {
        transform: rotate(0deg);
      }

      to {
        transform: rotate(360deg);
      }
    }

    .fan-spinning {
      animation: spin 1s linear infinite;
      color: #fff !important;
      filter: drop-shadow(0 0 8px rgba(40, 167, 69, 0.6));
    }

    #fanIcon {
      font-size: 2.5rem;
      color: #fff;
      transition: all 0.3s ease;
    }

    .mode-btn {
      transition: all 0.3s ease;
    }

    .mode-btn.active {
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
              <h1><i class="fas fa-fan"></i> Kontrol Kipas Otomatis</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Kontrol Kipas</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Row 1: Status Cards -->
          <div class="row">
            <!-- Fan Status Card -->
            <div class="col-lg-4 col-md-6 mb-4">
              <?php
              require_once 'components/cards/status-card.php';
              renderStatusCard([
                'id' => 'fanCard',
                'title' => 'Status Kipas',
                'value' => '<i class="fas fa-fan" id="fanIcon"></i> <span id="fanStatusText">OFF</span>',
                'icon' => 'power-off',
                'color' => 'danger',
                'footer' => 'Real-time status'
              ]);
              ?>
              <i class="fas fa-power-off" id="fanStatusIcon" style="display:none;"></i>
            </div>

            <!-- Temperature Card -->
            <div class="col-lg-4 col-md-6 mb-4">
              <?php
              renderStatusCard([
                'id' => 'tempCard',
                'title' => 'Suhu Saat Ini',
                'value' => '<span id="currentTemp">0.0</span>°C',
                'icon' => 'thermometer-half',
                'color' => 'warning',
                'footer' => 'Sensor DHT22'
              ]);
              ?>
              <div id="tempIndicator" class="small-box bg-success" style="display:none;"></div>
            </div>

            <!-- Humidity Card -->
            <div class="col-lg-4 col-md-6 mb-4">
              <?php
              renderStatusCard([
                'title' => 'Kelembapan',
                'value' => '<span id="currentHum">0.0</span>%',
                'icon' => 'tint',
                'color' => 'info',
                'footer' => 'Sensor DHT22'
              ]);
              ?>
            </div>
          </div>

          <!-- Row 2: Mode Selection -->
          <div class="row">
            <div class="col-12">
              <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-sliders-h"></i> Mode Kontrol
                  </h3>
                  <div class="card-tools">
                    <span class="badge badge-primary">
                      Mode: <span id="modeDisplay">AUTO</span>
                    </span>
                  </div>
                </div>
                <div class="card-body">
                  <div class="btn-group btn-group-lg d-flex" role="group">
                    <button type="button" class="btn btn-success mode-btn w-50 active" id="btnAuto">
                      <i class="fas fa-magic"></i> Mode AUTO
                      <br><small>Otomatis berdasarkan suhu</small>
                    </button>
                    <button type="button" class="btn btn-primary mode-btn w-50" id="btnManual">
                      <i class="fas fa-hand-pointer"></i> Mode MANUAL
                      <br><small>Kontrol manual ON/OFF</small>
                    </button>
                  </div>

                  <!-- Auto Mode Info -->
                  <div id="autoInfo" class="alert alert-success mt-3">
                    <h5><i class="fas fa-info-circle"></i> Mode AUTO Aktif</h5>
                    <p class="mb-0">Kipas akan menyala/mati secara otomatis berdasarkan suhu yang telah diatur di pengaturan threshold.</p>
                  </div>

                  <!-- Manual Controls -->
                  <div id="manualControls" class="mt-3" style="display: none;">
                    <div class="alert alert-warning">
                      <h5><i class="fas fa-hand-pointer"></i> Mode MANUAL Aktif</h5>
                      <p class="mb-0">Kontrol kipas secara manual dengan tombol di bawah ini.</p>
                    </div>
                    <div class="btn-group btn-group-lg d-flex" role="group">
                      <button type="button" class="btn btn-success w-50" id="btnFanOn">
                        <i class="fas fa-power-off"></i> NYALAKAN
                      </button>
                      <button type="button" class="btn btn-danger w-50" id="btnFanOff">
                        <i class="fas fa-power-off"></i> MATIKAN
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Row 3: Threshold Settings -->
          <div class="row">
            <div class="col-12">
              <div class="card card-warning card-outline shadow-sm">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-cog"></i> Pengaturan Threshold Suhu
                  </h3>
                </div>
                <div class="card-body">
                  <div id="thresholdResult"></div>

                  <div class="row">
                    <div class="col-md-5">
                      <div class="form-group">
                        <label for="thresholdOn">
                          <i class="fas fa-fire"></i> Suhu ON (Kipas Menyala)
                        </label>
                        <div class="input-group">
                          <input type="number" class="form-control" id="thresholdOn"
                            value="30" min="20" max="60" step="0.5">
                          <div class="input-group-append">
                            <span class="input-group-text">°C</span>
                          </div>
                        </div>
                        <small class="form-text text-muted">
                          Kipas akan menyala saat suhu mencapai nilai ini (20-60°C)
                        </small>
                      </div>
                    </div>

                    <div class="col-md-5">
                      <div class="form-group">
                        <label for="thresholdOff">
                          <i class="fas fa-snowflake"></i> Suhu OFF (Kipas Mati)
                        </label>
                        <div class="input-group">
                          <input type="number" class="form-control" id="thresholdOff"
                            value="25" min="15" max="50" step="0.5">
                          <div class="input-group-append">
                            <span class="input-group-text">°C</span>
                          </div>
                        </div>
                        <small class="form-text text-muted">
                          Kipas akan mati saat suhu turun ke nilai ini (15-50°C)
                        </small>
                      </div>
                    </div>

                    <div class="col-md-2">
                      <label>&nbsp;</label>
                      <button type="button" class="btn btn-primary btn-block btn-lg" id="btnSaveThreshold">
                        <i class="fas fa-save"></i> Simpan
                      </button>
                    </div>
                  </div>

                  <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb"></i>
                    <strong>Tips:</strong> Pastikan suhu ON lebih tinggi dari suhu OFF untuk mencegah kipas menyala-mati terus-menerus.
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