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

    /* Temperature Gauge */
    .temp-gauge {
      position: relative;
      width: 100%;
      height: 30px;
      background: linear-gradient(to right, #17a2b8, #28a745, #ffc107, #dc3545);
      border-radius: 15px;
      overflow: hidden;
    }

    .temp-indicator {
      position: absolute;
      top: -5px;
      width: 3px;
      height: 40px;
      background: #000;
      box-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
      transition: left 0.3s ease;
    }

    /* Mode Switch */
    .mode-switch {
      position: relative;
      display: inline-block;
      width: 60px;
      height: 34px;
    }

    .mode-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .mode-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 34px;
    }

    .mode-slider:before {
      position: absolute;
      content: "";
      height: 26px;
      width: 26px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }

    input:checked+.mode-slider {
      background-color: #28a745;
    }

    input:checked+.mode-slider:before {
      transform: translateX(26px);
    }

    /* Thermometer Icon Animation */
    .thermometer-icon {
      transition: color 0.3s ease;
    }

    .temp-cold {
      color: #17a2b8 !important;
    }

    .temp-cool {
      color: #28a745 !important;
    }

    .temp-warm {
      color: #ffc107 !important;
    }

    .temp-hot {
      color: #dc3545 !important;
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
                <i class="fas fa-fan text-success"></i> Kontrol Kipas Otomatis
              </h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Kipas</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Monitoring Cards -->
          <div class="row">
            <!-- Suhu Card -->
            <div class="col-lg-3 col-6">
              <div class="info-box shadow-sm">
                <span class="info-box-icon bg-info elevation-1">
                  <i class="fas fa-thermometer-half thermometer-icon temp-medium" id="tempIcon"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text">Suhu Ruangan</span>
                  <span class="info-box-number" id="currentTemp">
                    <small>Loading...</small>
                  </span>
                </div>
              </div>
            </div>

            <!-- Kelembapan Card -->
            <div class="col-lg-3 col-6">
              <div class="info-box shadow-sm">
                <span class="info-box-icon bg-primary elevation-1">
                  <i class="fas fa-tint"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text">Kelembapan</span>
                  <span class="info-box-number" id="currentHumidity">
                    <small>Loading...</small>
                  </span>
                </div>
              </div>
            </div>

            <!-- Status Kipas Card -->
            <div class="col-lg-3 col-6">
              <div class="info-box shadow-sm">
                <span class="info-box-icon bg-secondary elevation-1" id="fanStatusIcon">
                  <i class="fas fa-fan" id="fanIcon"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text">Status Kipas</span>
                  <span class="info-box-number" id="fanStatusText"><strong class="text-secondary">OFF</strong></span>
                </div>
              </div>
            </div>

            <!-- Mode Card -->
            <div class="col-lg-3 col-6">
              <div class="info-box shadow-sm">
                <span class="info-box-icon bg-warning elevation-1">
                  <i class="fas fa-cog"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text">Mode</span>
                  <span class="info-box-number" id="modeText">AUTO</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Temperature Gauge -->
          <div class="card card-info card-outline shadow-sm">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-chart-line"></i> Indikator Suhu</h3>
            </div>
            <div class="card-body">
              <div class="temp-gauge">
                <div class="temp-indicator" id="tempIndicator" style="left: 0%;"></div>
              </div>
              <div class="d-flex justify-content-between mt-2">
                <small class="text-muted">0°C</small>
                <small class="text-muted">25°C</small>
                <small class="text-muted">50°C</small>
              </div>
            </div>
          </div>

          <!-- Control Panel -->
          <div class="row">
            <!-- Manual Control -->
            <div class="col-md-6">
              <div class="card card-success card-outline shadow-sm">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-hand-pointer"></i> Kontrol Manual</h3>
                </div>
                <div class="card-body">
                  <div class="form-group">
                    <label>Mode Kipas:</label>
                    <div class="d-flex align-items-center">
                      <span class="mr-3 font-weight-bold">MANUAL</span>
                      <label class="mode-switch mb-0">
                        <input type="checkbox" id="modeSwitch" checked>
                        <span class="mode-slider"></span>
                      </label>
                      <span class="ml-3 font-weight-bold">AUTO</span>
                    </div>
                    <small class="text-muted">Mode AUTO: Kipas nyala/mati otomatis sesuai suhu</small>
                  </div>

                  <div id="manualControls">
                    <hr>
                    <div class="d-flex justify-content-center gap-3">
                      <button class="btn btn-success btn-lg mr-2" id="btnFanOn">
                        <i class="fas fa-power-off"></i> Nyalakan Kipas
                      </button>
                      <button class="btn btn-danger btn-lg" id="btnFanOff">
                        <i class="fas fa-ban"></i> Matikan Kipas
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Auto Mode Settings -->
            <div class="col-md-6">
              <div class="card card-warning card-outline shadow-sm">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-cog"></i> Pengaturan Auto Mode</h3>
                </div>
                <div class="card-body">
                  <div id="thresholdResult"></div>

                  <div class="form-group">
                    <label for="tempOn">
                      <i class="fas fa-thermometer-full text-danger"></i>
                      Suhu Nyala (°C):
                    </label>
                    <input type="number" class="form-control" id="tempOn" placeholder="30" min="20" max="60" step="0.1">
                    <small class="text-muted">Kipas akan menyala jika suhu ≥ nilai ini</small>
                  </div>

                  <div class="form-group">
                    <label for="tempOff">
                      <i class="fas fa-thermometer-half text-success"></i>
                      Suhu Mati (°C):
                    </label>
                    <input type="number" class="form-control" id="tempOff" placeholder="25" min="15" max="50" step="0.1">
                    <small class="text-muted">Kipas akan mati jika suhu ≤ nilai ini</small>
                  </div>

                  <button type="button" class="btn btn-primary btn-block" id="btnSaveThreshold">
                    <i class="fas fa-save"></i> Simpan Pengaturan
                  </button>
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