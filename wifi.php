<?php

/**
 * WiFi Configuration Page
 * Manage ESP32 WiFi settings via MQTT
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
$pageTitle = 'Konfigurasi WiFi';
$activePage = 'wifi';
$pageCSS = [];
$pageJS = ['assets/js/utils.js', 'assets/js/pages/wifi.js'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <?php
  require_once 'components/layout/head.php';
  renderHead($pageTitle, $pageCSS);
  ?>
  <style>
    /* Enhanced Card Styling */
    .info-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
      margin-bottom: 20px;
    }

    .info-card .info-item {
      display: flex;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .info-card .info-item:last-child {
      border-bottom: none;
    }

    .info-card .info-label {
      font-weight: 600;
      margin-right: 15px;
      min-width: 100px;
      opacity: 0.9;
    }

    .info-card .info-value {
      font-family: 'Courier New', monospace;
      font-weight: bold;
      font-size: 1.1rem;
    }

    .info-card i {
      margin-right: 10px;
      font-size: 1.2rem;
    }

    /* Signal Strength Badge */
    .signal-badge {
      display: inline-flex;
      align-items: center;
      background: rgba(255, 255, 255, 0.2);
      padding: 5px 12px;
      border-radius: 20px;
      font-weight: 600;
    }

    .signal-badge.excellent {
      background: rgba(40, 167, 69, 0.3);
    }

    .signal-badge.good {
      background: rgba(255, 193, 7, 0.3);
    }

    .signal-badge.fair {
      background: rgba(255, 152, 0, 0.3);
    }

    .signal-badge.poor {
      background: rgba(220, 53, 69, 0.3);
    }

    /* Password Toggle */
    .input-group-append .btn {
      border-left: none;
    }

    .password-toggle {
      cursor: pointer;
      border-radius: 0 0.25rem 0.25rem 0;
    }

    /* Status Badge Animation */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
    }

    .status-badge.connected {
      background: rgba(40, 167, 69, 0.2);
      color: #28a745;
    }

    .status-badge.disconnected {
      background: rgba(220, 53, 69, 0.2);
      color: #dc3545;
    }

    .status-badge.restarting {
      background: rgba(255, 193, 7, 0.2);
      color: #ffc107;
    }

    .status-pulse {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: currentColor;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
        transform: scale(1);
      }

      50% {
        opacity: 0.5;
        transform: scale(1.2);
      }
    }

    /* Form Enhancements */
    .form-control:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .btn-save-wifi {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      color: white;
      font-weight: 600;
      padding: 10px 25px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
      transition: all 0.3s ease;
    }

    .btn-save-wifi:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
      color: white;
    }

    .btn-refresh {
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .btn-refresh:hover {
      transform: rotate(180deg);
    }

    /* Alert Animations */
    .alert {
      border-radius: 8px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
      animation: slideInDown 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    /* Loading Skeleton */
    .skeleton {
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
      background-size: 200% 100%;
      animation: loading 1.5s infinite;
      border-radius: 4px;
      height: 20px;
    }

    @keyframes loading {
      0% {
        background-position: 200% 0;
      }

      100% {
        background-position: -200% 0;
      }
    }
  </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
  <div class="wrapper">

    <!-- Header -->
    <?php
    require_once 'components/layout/header.php';
    renderHeader();
    ?>

    <!-- Sidebar -->
    <?php
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
              <h1 class="m-0">
                <i class="fas fa-wifi text-primary"></i>
                Konfigurasi WiFi ESP32
              </h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">WiFi Config</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">

          <!-- Alert Container -->
          <div id="wifiAlert"></div>

          <!-- Current WiFi Status Card -->
          <div class="row">
            <div class="col-lg-6">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-signal"></i>
                    Status Koneksi Saat Ini
                  </h3>
                  <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-tool btn-refresh" id="btnRefreshStatus" title="Refresh Status">
                      <i class="fas fa-sync-alt"></i>
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="info-card" id="wifiStatusCard">
                    <div class="info-item">
                      <i class="fas fa-circle-notch fa-spin"></i>
                      <span class="info-label">Status:</span>
                      <span class="info-value">
                        <span class="status-badge">
                          <span class="status-pulse"></span>
                          <span id="connectionStatus">Memuat...</span>
                        </span>
                      </span>
                    </div>
                    <div class="info-item">
                      <i class="fas fa-wifi"></i>
                      <span class="info-label">SSID:</span>
                      <span class="info-value" id="currentSSID">-</span>
                    </div>
                    <div class="info-item">
                      <i class="fas fa-network-wired"></i>
                      <span class="info-label">IP Address:</span>
                      <span class="info-value" id="currentIP">-</span>
                    </div>
                    <div class="info-item">
                      <i class="fas fa-signal"></i>
                      <span class="info-label">Signal:</span>
                      <span class="info-value">
                        <span class="signal-badge" id="currentRSSI">
                          <i class="fas fa-signal"></i>
                          <span>-</span>
                        </span>
                      </span>
                    </div>
                  </div>

                  <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    <strong>Info:</strong> Klik tombol <i class="fas fa-sync-alt"></i> untuk refresh status terbaru dari ESP32.
                  </div>
                </div>
              </div>
            </div>

            <!-- Set WiFi Config Form -->
            <div class="col-lg-6">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-cog"></i>
                    Ubah Konfigurasi WiFi
                  </h3>
                </div>
                <div class="card-body">
                  <form id="formSetWiFi">
                    <!-- SSID Input -->
                    <div class="form-group">
                      <label for="inputSSID">
                        <i class="fas fa-wifi"></i>
                        SSID (Nama WiFi)
                      </label>
                      <input type="text"
                        class="form-control"
                        id="inputSSID"
                        name="ssid"
                        placeholder="Masukkan nama WiFi"
                        required
                        maxlength="32"
                        autocomplete="off">
                      <small class="form-text text-muted">
                        Maksimal 32 karakter
                      </small>
                    </div>

                    <!-- Password Input with Toggle -->
                    <div class="form-group">
                      <label for="inputPassword">
                        <i class="fas fa-lock"></i>
                        Password WiFi
                      </label>
                      <div class="input-group">
                        <input type="password"
                          class="form-control"
                          id="inputPassword"
                          name="password"
                          placeholder="Masukkan password WiFi"
                          required
                          maxlength="64"
                          autocomplete="new-password">
                        <div class="input-group-append">
                          <button class="btn btn-outline-secondary password-toggle" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                          </button>
                        </div>
                      </div>
                      <small class="form-text text-muted">
                        Maksimal 64 karakter. Password akan dikirim secara aman via MQTT.
                      </small>
                    </div>

                    <!-- Warning -->
                    <div class="alert alert-warning">
                      <i class="fas fa-exclamation-triangle"></i>
                      <strong>Perhatian!</strong>
                      <ul class="mb-0 mt-2">
                        <li>ESP32 akan <strong>restart otomatis</strong> setelah konfigurasi disimpan</li>
                        <li>Proses reconnect membutuhkan waktu <strong>~30 detik</strong></li>
                        <li>Pastikan SSID dan Password <strong>benar</strong> untuk menghindari koneksi gagal</li>
                      </ul>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-save-wifi btn-block">
                      <i class="fas fa-save"></i>
                      Simpan & Restart ESP32
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <!-- Help Section -->
          <div class="row">
            <div class="col-12">
              <div class="card card-outline card-info collapsed-card">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-question-circle"></i>
                    Bantuan & Troubleshooting
                  </h3>
                  <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                      <i class="fas fa-plus"></i>
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6">
                      <h5><i class="fas fa-check-circle text-success"></i> Cara Menggunakan</h5>
                      <ol>
                        <li>Klik tombol <i class="fas fa-sync-alt"></i> untuk melihat status WiFi saat ini</li>
                        <li>Masukkan <strong>SSID</strong> dan <strong>Password</strong> WiFi baru</li>
                        <li>Klik tombol <strong>Simpan & Restart ESP32</strong></li>
                        <li>Tunggu ~30 detik hingga ESP32 reconnect ke WiFi baru</li>
                        <li>Status akan otomatis update jika koneksi berhasil</li>
                      </ol>
                    </div>
                    <div class="col-md-6">
                      <h5><i class="fas fa-exclamation-circle text-warning"></i> Troubleshooting</h5>
                      <ul>
                        <li><strong>ESP32 tidak reconnect:</strong>
                          <ul>
                            <li>Pastikan SSID dan Password benar</li>
                            <li>Cek jarak WiFi router dengan ESP32</li>
                            <li>Restart ESP32 secara manual (tekan tombol RST)</li>
                          </ul>
                        </li>
                        <li><strong>Signal lemah (RSSI < -70 dBm):</strong>
                              <ul>
                                <li>Dekatkan ESP32 dengan WiFi router</li>
                                <li>Hindari penghalang logam/beton tebal</li>
                              </ul>
                        </li>
                        <li><strong>Timeout setelah 30 detik:</strong>
                          <ul>
                            <li>ESP32 gagal connect ke WiFi baru</li>
                            <li>ESP32 akan tetap retry terus-menerus</li>
                            <li>Upload ulang sketch dengan WiFi yang benar jika perlu</li>
                          </ul>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </section>
    </div>

    <!-- Footer -->
    <?php
    require_once 'components/layout/footer.php';
    renderFooter($pageJS, $mqttConfig);
    ?>

  </div>
</body>

</html>