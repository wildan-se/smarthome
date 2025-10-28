<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'config/config.php';
require_once 'config/config_helper.php';

// Load MQTT config
$mqttCredentials = getMqttCredentials();
$mqttBroker = getConfig('mqtt_broker');
$deviceSerial = getDeviceSerial();
$mqttProtocol = getConfig('mqtt_protocol', 'wss');
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Kontrol Kipas - Smart Home IoT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/custom.css">
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

    /* Fan Icon Size */
    #fanIcon {
      font-size: 2.5rem;
      color: #fff;
      transition: all 0.3s ease;
    }

    #fanStatusIcon {
      transition: all 0.3s ease;
    }

    /* Thermometer Animation */
    .thermometer-icon {
      position: relative;
      display: inline-block;
    }

    .temp-high {
      color: #dc3545;
      animation: tempPulse 1.5s ease-in-out infinite;
    }

    .temp-medium {
      color: #ffc107;
    }

    .temp-low {
      color: #17a2b8;
    }

    @keyframes tempPulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.1);
      }
    }

    /* Gauge Style */
    .temp-gauge {
      background: linear-gradient(to right, #17a2b8 0%, #28a745 30%, #ffc107 60%, #dc3545 100%);
      height: 30px;
      border-radius: 15px;
      position: relative;
      overflow: hidden;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .temp-indicator {
      position: absolute;
      top: -5px;
      width: 4px;
      height: 40px;
      background: #212529;
      border-radius: 2px;
      transition: left 0.5s ease;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    /* Card Hover Effect */
    .info-box:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      transition: all 0.3s ease;
    }

    /* Switch Toggle */
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

    /* Number Input Styling */
    .temp-input {
      font-size: 1.5rem;
      font-weight: 700;
      text-align: center;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      padding: 10px;
      transition: all 0.3s ease;
    }

    .temp-input:focus {
      border-color: #007bff;
      box-shadow: 0 0 10px rgba(0, 123, 255, 0.3);
    }

    /* Scrollable History Table */
    .scrollable-history {
      max-height: 500px;
      overflow-y: auto;
      border: 1px solid #dee2e6;
      border-radius: 0.25rem;
    }

    .scrollable-history::-webkit-scrollbar {
      width: 8px;
    }

    .scrollable-history::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .scrollable-history::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 10px;
    }

    .scrollable-history::-webkit-scrollbar-thumb:hover {
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
  </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
  <div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
          <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
        </li>
      </ul>
      <ul class="navbar-nav ml-auto">
        <li class="nav-item">
          <span class="nav-link"><i class="far fa-clock"></i> <span id="current-time"></span></span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </li>
      </ul>
    </nav>

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
      <a href="index.php" class="brand-link">
        <i class="fas fa-home brand-image" style="font-size: 2rem; opacity: .8;"></i>
        <span class="brand-text font-weight-bold">Smart Home</span>
      </a>
      <div class="sidebar">
        <nav class="mt-3">
          <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
            <li class="nav-item">
              <a href="index.php" class="nav-link">
                <i class="nav-icon fas fa-tachometer-alt"></i>
                <p>Dashboard</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="rfid.php" class="nav-link">
                <i class="nav-icon fas fa-id-card"></i>
                <p>Manajemen RFID</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="kipas.php" class="nav-link active">
                <i class="nav-icon fas fa-fan"></i>
                <p>Kontrol Kipas</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="kontrol.php" class="nav-link">
                <i class="nav-icon fas fa-door-open"></i>
                <p>Kontrol Pintu</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="log.php" class="nav-link">
                <i class="nav-icon fas fa-list"></i>
                <p>Log</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="export.php" class="nav-link">
                <i class="nav-icon fas fa-file-export"></i>
                <p>Export Data</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="settings.php" class="nav-link">
                <i class="nav-icon fas fa-cog"></i>
                <p>Pengaturan Sistem</p>
              </a>
            </li>
            <li class="nav-header">ACCOUNT</li>
            <li class="nav-item">
              <a href="logout.php" class="nav-link">
                <i class="nav-icon fas fa-sign-out-alt"></i>
                <p>Logout</p>
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </aside>

    <div class="content-wrapper">
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
                      <button class="btn btn-success btn-lg" id="btnFanOn" style="width: 150px;">
                        <i class="fas fa-power-off"></i> Nyalakan
                      </button>
                      <button class="btn btn-danger btn-lg ml-3" id="btnFanOff" style="width: 150px;">
                        <i class="fas fa-power-off"></i> Matikan
                      </button>
                    </div>
                  </div>

                  <div id="autoInfo" style="display: none;">
                    <div class="alert alert-info mt-3 mb-0">
                      <i class="fas fa-info-circle"></i> Mode AUTO aktif. Kipas dikontrol otomatis berdasarkan threshold suhu.
                    </div>
                  </div>

                  <div id="controlResult" class="mt-3"></div>
                </div>
              </div>
            </div>

            <!-- Auto Settings -->
            <div class="col-md-6">
              <div class="card card-warning card-outline shadow-sm">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-sliders-h"></i> Pengaturan Threshold Suhu</h3>
                </div>
                <div class="card-body">
                  <div class="form-group">
                    <label>Suhu Nyala Kipas (°C):</label>
                    <input type="number" class="form-control temp-input" id="thresholdOn" min="20" max="60" step="0.5" value="38">
                    <small class="text-muted">Kipas akan menyala jika suhu ≥ nilai ini</small>
                  </div>

                  <div class="form-group">
                    <label>Suhu Mati Kipas (°C):</label>
                    <input type="number" class="form-control temp-input" id="thresholdOff" min="15" max="50" step="0.5" value="30">
                    <small class="text-muted">Kipas akan mati jika suhu ≤ nilai ini</small>
                  </div>

                  <button class="btn btn-primary btn-block btn-lg" id="btnSaveThreshold">
                    <i class="fas fa-save"></i> Simpan Pengaturan
                  </button>

                  <div id="thresholdResult" class="mt-3"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- History Log -->
          <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-history"></i> Riwayat Aktivitas Kipas <span id="historyCount" class="badge badge-info ml-2">0</span></h3>
              <div class="card-tools">
                <button type="button" class="btn btn-sm btn-primary" onclick="loadHistory()">
                  <i class="fas fa-sync-alt"></i> Refresh
                </button>
              </div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive scrollable-history">
                <table class="table table-hover table-striped mb-0">
                  <thead class="thead-sticky">
                    <tr>
                      <th width="50">#</th>
                      <th><i class="fas fa-power-off"></i> Status</th>
                      <th><i class="fas fa-cog"></i> Mode</th>
                      <th><i class="fas fa-thermometer-half"></i> Suhu</th>
                      <th><i class="fas fa-tint"></i> Kelembapan</th>
                      <th><i class="fas fa-bolt"></i> Trigger</th>
                      <th><i class="far fa-clock"></i> Waktu</th>
                    </tr>
                  </thead>
                  <tbody id="historyTable">
                    <tr>
                      <td colspan="7" class="text-center text-muted">
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

    <footer class="main-footer">
      <strong>Copyright &copy; <?= date('Y') ?> <a href="index.php">Koneksi Pintar</a>.</strong>
      <div class="float-right d-none d-sm-inline-block">
        <b>Version</b> 1.0.0
      </div>
    </footer>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
  <script>
    // MQTT Configuration
    const broker = "<?php echo $mqttBroker; ?>";
    const mqttUser = "<?php echo $mqttCredentials['username']; ?>";
    const mqttPass = "<?php echo $mqttCredentials['password']; ?>";
    const serial = "<?php echo $deviceSerial; ?>";
    const topicRoot = `smarthome/${serial}`;
    const mqttProtocol = "<?php echo $mqttProtocol; ?>";

    const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
      username: mqttUser,
      password: mqttPass,
      clientId: 'kipas-' + Math.random().toString(16).substr(2, 8)
    });

    let currentMode = 'auto';
    let currentTemp = 0;
    let currentHumidity = 0;
    let currentFanStatus = 'off';

    // Subscribe MQTT
    client.on('connect', function() {
      console.log('✅ MQTT Connected');
      client.subscribe(`${topicRoot}/dht/temperature`);
      client.subscribe(`${topicRoot}/dht/humidity`);
      client.subscribe(`${topicRoot}/kipas/status`);
      client.subscribe(`${topicRoot}/kipas/mode`);

      // Load initial data
      loadSettings();
      loadHistory();
    });

    // Handle MQTT Messages
    client.on('message', function(topic, message) {
      const payload = message.toString();
      console.log('📩 MQTT:', topic, '=>', payload);

      if (topic.endsWith('/dht/temperature')) {
        currentTemp = parseFloat(payload);
        updateTemperature(currentTemp);
      }

      if (topic.endsWith('/dht/humidity')) {
        currentHumidity = parseFloat(payload);
        updateHumidity(currentHumidity);

        // Log DHT data to database setelah dapat temperature dan humidity
        if (currentTemp > 0 && currentHumidity > 0) {
          $.post('api/kipas_crud.php', {
            action: 'log_dht',
            temperature: currentTemp,
            humidity: currentHumidity
          });
        }
      }

      if (topic.endsWith('/kipas/status')) {
        const newStatus = payload.toLowerCase();

        // Log perubahan status kipas ke database
        if (newStatus !== currentFanStatus) {
          // Determine trigger message
          let triggerMsg = '';
          if (currentMode === 'auto') {
            if (newStatus === 'on') {
              triggerMsg = `Suhu mencapai ${currentTemp.toFixed(1)}°C (≥ threshold ON)`;
            } else {
              triggerMsg = `Suhu turun ke ${currentTemp.toFixed(1)}°C (≤ threshold OFF)`;
            }
          } else {
            triggerMsg = newStatus === 'on' ? 'Manual ON oleh user' : 'Manual OFF oleh user';
          }

          $.post('api/kipas_crud.php', {
            action: 'log_status',
            status: newStatus,
            mode: currentMode,
            temperature: currentTemp,
            humidity: currentHumidity,
            trigger: triggerMsg
          }, function(res) {
            if (res.success) {
              console.log('✅ Status kipas berhasil di-log');
              // Refresh history setelah 500ms
              setTimeout(loadHistory, 500);
            }
          }, 'json');
        }

        currentFanStatus = newStatus;
        updateFanStatus(currentFanStatus);
      }

      if (topic.endsWith('/kipas/mode')) {
        const newMode = payload.toLowerCase();

        // Log perubahan mode
        if (newMode !== currentMode) {
          currentMode = newMode;
          updateMode(currentMode);

          // Refresh history karena mode berubah
          setTimeout(loadHistory, 500);
        }
      }
    });

    // Update UI Functions
    function updateTemperature(temp) {
      $('#currentTemp').html(`<strong>${temp.toFixed(1)}°C</strong>`);

      // Update gauge indicator (0-50°C range)
      const percentage = Math.min(Math.max((temp / 50) * 100, 0), 100);
      $('#tempIndicator').css('left', percentage + '%');

      // Update icon color
      const icon = $('#tempIcon');
      icon.removeClass('temp-low temp-medium temp-high');
      if (temp >= 35) {
        icon.addClass('temp-high');
      } else if (temp >= 25) {
        icon.addClass('temp-medium');
      } else {
        icon.addClass('temp-low');
      }
    }

    function updateHumidity(humidity) {
      $('#currentHumidity').html(`<strong>${humidity.toFixed(1)}%</strong>`);
    }

    function updateFanStatus(status) {
      const icon = $('#fanIcon');
      const iconBg = $('#fanStatusIcon');
      const text = $('#fanStatusText');

      if (status === 'on') {
        icon.addClass('fan-spinning');
        iconBg.removeClass('bg-secondary').addClass('bg-success');
        text.html('<strong class="text-success">ON</strong>');
      } else {
        icon.removeClass('fan-spinning');
        iconBg.removeClass('bg-success').addClass('bg-secondary');
        text.html('<strong class="text-secondary">OFF</strong>');
      }
    }

    function updateMode(mode) {
      currentMode = mode;
      const text = $('#modeText');
      const modeSwitch = $('#modeSwitch');

      if (mode === 'auto') {
        text.html('<strong class="text-warning">AUTO</strong>');
        modeSwitch.prop('checked', true);
        $('#manualControls').hide();
        $('#autoInfo').show();
      } else {
        text.html('<strong class="text-info">MANUAL</strong>');
        modeSwitch.prop('checked', false);
        $('#manualControls').show();
        $('#autoInfo').hide();
      }
    }

    // Mode Switch Handler
    $('#modeSwitch').change(function() {
      const isAuto = $(this).is(':checked');
      const newMode = isAuto ? 'auto' : 'manual';

      client.publish(`${topicRoot}/kipas/mode`, newMode);

      // Update database
      $.post('api/kipas_crud.php', {
        action: 'update_settings',
        mode: newMode,
        threshold_on: $('#thresholdOn').val(),
        threshold_off: $('#thresholdOff').val()
      }, function(res) {
        if (res.success) {
          showAlert('#controlResult', 'success', 'Mode berhasil diubah ke ' + newMode.toUpperCase());
        }
      }, 'json');
    });

    // Manual Control Buttons
    $('#btnFanOn').click(function() {
      client.publish(`${topicRoot}/kipas/control`, 'on');
      showAlert('#controlResult', 'info', '<i class="fas fa-spinner fa-spin"></i> Mengirim perintah ON...');

      // Tunggu status update dari MQTT, tapi tetap log manual action
      setTimeout(function() {
        showAlert('#controlResult', 'success', '✅ Kipas berhasil dinyalakan secara manual');
      }, 1000);
    });

    $('#btnFanOff').click(function() {
      client.publish(`${topicRoot}/kipas/control`, 'off');
      showAlert('#controlResult', 'info', '<i class="fas fa-spinner fa-spin"></i> Mengirim perintah OFF...');

      // Tunggu status update dari MQTT, tapi tetap log manual action
      setTimeout(function() {
        showAlert('#controlResult', 'success', '✅ Kipas berhasil dimatikan secara manual');
      }, 1000);
    });

    // Save Threshold
    $('#btnSaveThreshold').click(function() {
      const thresholdOn = parseFloat($('#thresholdOn').val());
      const thresholdOff = parseFloat($('#thresholdOff').val());

      if (thresholdOn <= thresholdOff) {
        showAlert('#thresholdResult', 'danger', '❌ Suhu ON harus lebih tinggi dari suhu OFF');
        return;
      }

      $.post('api/kipas_crud.php', {
        action: 'update_settings',
        threshold_on: thresholdOn,
        threshold_off: thresholdOff,
        mode: currentMode
      }, function(res) {
        if (res.success) {
          // Publish to ESP32
          const thresholdData = JSON.stringify({
            on: thresholdOn,
            off: thresholdOff
          });
          client.publish(`${topicRoot}/kipas/threshold`, thresholdData);

          showAlert('#thresholdResult', 'success', '✅ Pengaturan threshold berhasil disimpan!');
        } else {
          showAlert('#thresholdResult', 'danger', '❌ ' + res.error);
        }
      }, 'json');
    });

    // Load Settings
    function loadSettings() {
      $.get('api/kipas_crud.php?action=get_settings', function(res) {
        if (res.success) {
          $('#thresholdOn').val(res.data.threshold_on);
          $('#thresholdOff').val(res.data.threshold_off);
          updateMode(res.data.mode);
        }
      }, 'json');

      // Load latest DHT
      $.get('api/kipas_crud.php?action=get_latest_dht', function(res) {
        if (res.success) {
          updateTemperature(res.data.temperature);
          updateHumidity(res.data.humidity);
          currentTemp = res.data.temperature;
          currentHumidity = res.data.humidity;
        }
      }, 'json');
    }

    // Load History
    function loadHistory() {
      $.get('api/kipas_crud.php?action=get_logs&limit=50', function(res) {
        let rows = '';
        if (res.success && res.data.length > 0) {
          // Update badge count
          $('#historyCount').text(res.data.length);

          res.data.forEach((log, index) => {
            const statusBadge = log.status === 'on' ?
              '<span class="badge badge-success"><i class="fas fa-power-off"></i> ON</span>' :
              '<span class="badge badge-secondary"><i class="fas fa-power-off"></i> OFF</span>';

            const modeBadge = log.mode === 'auto' ?
              '<span class="badge badge-warning"><i class="fas fa-magic"></i> AUTO</span>' :
              '<span class="badge badge-info"><i class="fas fa-hand-paper"></i> MANUAL</span>';

            const temp = log.temperature ? log.temperature.toFixed(1) + '°C' : '-';
            const humidity = log.humidity ? log.humidity.toFixed(1) + '%' : '-';

            // Format trigger dengan badge warna
            let triggerBadge = '';
            const trigger = log.trigger || '-';
            if (trigger.includes('Manual')) {
              triggerBadge = `<span class="badge badge-info"><i class="fas fa-user"></i> ${trigger}</span>`;
            } else if (trigger.includes('mencapai') || trigger.includes('melebihi')) {
              triggerBadge = `<span class="badge badge-danger"><i class="fas fa-arrow-up"></i> ${trigger}</span>`;
            } else if (trigger.includes('turun')) {
              triggerBadge = `<span class="badge badge-success"><i class="fas fa-arrow-down"></i> ${trigger}</span>`;
            } else {
              triggerBadge = `<span class="badge badge-light">${trigger}</span>`;
            }

            // Format waktu
            const timestamp = new Date(log.logged_at);
            const formattedTime = timestamp.toLocaleString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit'
            });

            rows += `
              <tr>
                <td>${index + 1}</td>
                <td>${statusBadge}</td>
                <td>${modeBadge}</td>
                <td><strong>${temp}</strong></td>
                <td><strong>${humidity}</strong></td>
                <td>${triggerBadge}</td>
                <td><small class="text-muted"><i class="far fa-clock"></i> ${formattedTime}</small></td>
              </tr>
            `;
          });
        } else {
          $('#historyCount').text('0');
          rows = '<tr><td colspan="7" class="text-center text-muted"><i class="fas fa-inbox"></i> Belum ada riwayat aktivitas</td></tr>';
        }
        $('#historyTable').html(rows);
      }, 'json').fail(function(xhr) {
        $('#historyCount').text('0');
        $('#historyTable').html('<tr><td colspan="7" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data</td></tr>');
        console.error('Error loading history:', xhr.responseText);
      });
    }

    // Alert Helper
    function showAlert(target, type, message) {
      const alert = `
        <div class="alert alert-${type} alert-dismissible fade show">
          ${message}
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      `;
      $(target).html(alert);
      setTimeout(() => $(target).html(''), 5000);
    }

    // Init
    $(function() {
      loadSettings();
      loadHistory();

      // Auto-refresh history setiap 30 detik
      setInterval(function() {
        loadHistory();
      }, 30000);

      // Update clock
      function updateClock() {
        const now = new Date();
        const options = {
          timeZone: 'Asia/Jakarta',
          day: '2-digit',
          month: 'short',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: false
        };
        $('#current-time').text(now.toLocaleString('id-ID', options));
      }
      updateClock();
      setInterval(updateClock, 1000);
    });
  </script>
</body>

</html>