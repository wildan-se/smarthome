<?php
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

require_once 'config/config.php';
require_once 'config/config_helper.php';

// Get username from database if not in session
if (!isset($_SESSION['username'])) {
  $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($row = $result->fetch_assoc()) {
    $_SESSION['username'] = $row['username'];
  }
}

// Load all configurations
$configs = getAllConfigWithMeta();
$configData = [];
foreach ($configs as $config) {
  $configData[$config['config_key']] = $config['config_value'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengaturan Sistem - Smart Home IoT</title>

  <!-- AdminLTE -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="assets/css/custom.css">

  <style>
    .config-section {
      margin-bottom: 30px;
    }

    .config-section .card-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .form-group label {
      font-weight: 600;
      color: #495057;
    }

    .help-text {
      font-size: 0.85rem;
      color: #6c757d;
      margin-top: 5px;
    }

    .btn-test {
      margin-left: 10px;
    }

    .test-result {
      margin-top: 10px;
      padding: 10px;
      border-radius: 4px;
      display: none;
    }

    .test-result.success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .test-result.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>

<body class="hold-transition sidebar-mini">
  <div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
      </ul>
      <ul class="navbar-nav ml-auto">
        <li class="nav-item">
          <span class="nav-link">
            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
          </span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Keluar
          </a>
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
              <a href="kontrol.php" class="nav-link">
                <i class="nav-icon fas fa-door-open"></i>
                <p>Kontrol Pintu</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="log.php" class="nav-link">
                <i class="nav-icon fas fa-list"></i>
                <p>Log & Sensor</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="export.php" class="nav-link">
                <i class="nav-icon fas fa-file-export"></i>
                <p>Export Data</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="settings.php" class="nav-link active">
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

    <!-- Content -->
    <div class="content-wrapper">
      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1><i class="fas fa-cog"></i> Pengaturan Sistem</h1>
            </div>
          </div>
        </div>
      </section>

      <section class="content">
        <div class="container-fluid">

          <!-- Alert untuk feedback -->
          <div id="alertMessage" style="display: none;"></div>

          <!-- Form Pengaturan -->
          <form id="settingsForm">

            <!-- MQTT Configuration -->
            <div class="config-section">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-server"></i> Konfigurasi MQTT Broker</h3>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="mqtt_broker">Broker Host</label>
                        <input type="text" class="form-control" id="mqtt_broker" name="mqtt_broker"
                          value="<?php echo htmlspecialchars($configData['mqtt_broker']); ?>" required>
                        <small class="help-text">Hostname atau IP address MQTT broker</small>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label for="mqtt_protocol">Protokol</label>
                        <select class="form-control p-1" id="mqtt_protocol" name="mqtt_protocol">
                          <option value="wss" <?php echo $configData['mqtt_protocol'] === 'wss' ? 'selected' : ''; ?>>WSS (WebSocket Secure)</option>
                          <option value="ws" <?php echo $configData['mqtt_protocol'] === 'ws' ? 'selected' : ''; ?>>WS (WebSocket)</option>
                          <option value="mqtts" <?php echo $configData['mqtt_protocol'] === 'mqtts' ? 'selected' : ''; ?>>MQTTS (MQTT Secure)</option>
                          <option value="mqtt" <?php echo $configData['mqtt_protocol'] === 'mqtt' ? 'selected' : ''; ?>>MQTT</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label for="mqtt_port">Port</label>
                        <input type="number" class="form-control" id="mqtt_port" name="mqtt_port"
                          value="<?php echo htmlspecialchars($configData['mqtt_port']); ?>"
                          min="1" max="65535" required>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="mqtt_username">Username</label>
                        <input type="text" class="form-control" id="mqtt_username" name="mqtt_username"
                          value="<?php echo htmlspecialchars($configData['mqtt_username']); ?>" required>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="mqtt_password">Password</label>
                        <div class="input-group">
                          <input type="password" class="form-control" id="mqtt_password" name="mqtt_password"
                            value="<?php echo htmlspecialchars($configData['mqtt_password']); ?>" required>
                          <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                              <i class="fas fa-eye"></i>
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-12">
                      <button type="button" class="btn btn-info" id="btnTestMqtt">
                        <i class="fas fa-plug"></i> Test Koneksi MQTT
                      </button>
                      <div id="mqttTestResult" class="test-result"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Device Configuration -->
            <div class="config-section">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-microchip"></i> Konfigurasi Perangkat</h3>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="device_serial">Serial Number Perangkat</label>
                        <input type="text" class="form-control" id="device_serial" name="device_serial"
                          value="<?php echo htmlspecialchars($configData['device_serial']); ?>" required>
                        <small class="help-text">Nomor seri unik untuk identifikasi perangkat ESP32</small>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label for="sensor_interval">Interval Sensor (ms)</label>
                        <input type="number" class="form-control" id="sensor_interval" name="sensor_interval"
                          value="<?php echo htmlspecialchars($configData['sensor_interval']); ?>"
                          min="100" required>
                        <small class="help-text">Interval pembacaan sensor DHT22</small>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label for="door_timeout">Door Timeout (ms)</label>
                        <input type="number" class="form-control" id="door_timeout" name="door_timeout"
                          value="<?php echo htmlspecialchars($configData['door_timeout']); ?>"
                          min="100" required>
                        <small class="help-text">Durasi pintu terbuka otomatis</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- General Configuration -->
            <div class="config-section">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-sliders-h"></i> Konfigurasi Umum</h3>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="site_name">Nama Situs</label>
                        <input type="text" class="form-control" id="site_name" name="site_name"
                          value="<?php echo htmlspecialchars($configData['site_name']); ?>" required>
                        <small class="help-text">Nama yang ditampilkan di header aplikasi</small>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="timezone">Timezone</label>
                        <select class="form-control p-1" id="timezone" name="timezone">
                          <option value="Asia/Jakarta" <?php echo $configData['timezone'] === 'Asia/Jakarta' ? 'selected' : ''; ?>>Asia/Jakarta (WIB)</option>
                          <option value="Asia/Makassar" <?php echo $configData['timezone'] === 'Asia/Makassar' ? 'selected' : ''; ?>>Asia/Makassar (WITA)</option>
                          <option value="Asia/Jayapura" <?php echo $configData['timezone'] === 'Asia/Jayapura' ? 'selected' : ''; ?>>Asia/Jayapura (WIT)</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="row">
              <div class="col-12">
                <button type="submit" class="btn btn-success btn-lg">
                  <i class="fas fa-save"></i> Simpan Pengaturan
                </button>
                <button type="button" class="btn btn-warning btn-lg" id="btnReset">
                  <i class="fas fa-undo"></i> Reset ke Default
                </button>
              </div>
            </div>

          </form>

        </div>
      </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
      <strong>&copy; 2024 Smart Home IoT.</strong> All rights reserved.
    </footer>
  </div>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Bootstrap -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- AdminLTE -->
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

  <script>
    $(document).ready(function() {

      // Toggle password visibility
      $('#togglePassword').click(function() {
        const passwordField = $('#mqtt_password');
        const icon = $(this).find('i');

        if (passwordField.attr('type') === 'password') {
          passwordField.attr('type', 'text');
          icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
          passwordField.attr('type', 'password');
          icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
      });

      // Test MQTT Connection
      $('#btnTestMqtt').click(function() {
        const btn = $(this);
        const result = $('#mqttTestResult');

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
        result.hide();

        $.ajax({
          url: 'api/config_crud.php?action=test_mqtt',
          type: 'POST',
          contentType: 'application/json',
          data: JSON.stringify({
            broker: $('#mqtt_broker').val(),
            username: $('#mqtt_username').val(),
            password: $('#mqtt_password').val(),
            port: $('#mqtt_port').val(),
            protocol: $('#mqtt_protocol').val()
          }),
          success: function(response) {
            result.removeClass('error').addClass('success')
              .html('<i class="fas fa-check-circle"></i> ' + response.message)
              .show();
          },
          error: function(xhr) {
            const response = xhr.responseJSON || {
              message: 'Connection test failed'
            };
            result.removeClass('success').addClass('error')
              .html('<i class="fas fa-times-circle"></i> ' + response.message)
              .show();
          },
          complete: function() {
            btn.prop('disabled', false).html('<i class="fas fa-plug"></i> Test Koneksi MQTT');
          }
        });
      });

      // Save Settings
      $('#settingsForm').submit(function(e) {
        e.preventDefault();

        const formData = {};
        $(this).serializeArray().forEach(item => {
          formData[item.name] = item.value;
        });

        $.ajax({
          url: 'api/config_crud.php?action=update',
          type: 'POST',
          contentType: 'application/json',
          data: JSON.stringify(formData),
          success: function(response) {
            showAlert('success', '<i class="fas fa-check-circle"></i> Pengaturan berhasil disimpan!');

            // Reload page after 2 seconds to apply new settings
            setTimeout(() => {
              location.reload();
            }, 2000);
          },
          error: function(xhr) {
            const response = xhr.responseJSON || {
              message: 'Gagal menyimpan pengaturan'
            };
            showAlert('danger', '<i class="fas fa-times-circle"></i> ' + response.message);
          }
        });
      });

      // Reset to Default
      $('#btnReset').click(function() {
        if (!confirm('Apakah Anda yakin ingin mereset semua pengaturan ke nilai default?')) {
          return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Resetting...');

        $.ajax({
          url: 'api/config_crud.php?action=reset',
          type: 'POST',
          success: function(response) {
            showAlert('success', '<i class="fas fa-check-circle"></i> Pengaturan direset ke default!');

            // Reload page after 2 seconds
            setTimeout(() => {
              location.reload();
            }, 2000);
          },
          error: function(xhr) {
            const response = xhr.responseJSON || {
              message: 'Gagal mereset pengaturan'
            };
            showAlert('danger', '<i class="fas fa-times-circle"></i> ' + response.message);
            btn.prop('disabled', false).html('<i class="fas fa-undo"></i> Reset ke Default');
          }
        });
      });

      // Show alert message
      function showAlert(type, message) {
        const alert = $('#alertMessage');
        alert.removeClass().addClass('alert alert-' + type + ' alert-dismissible fade show')
          .html(message + '<button type="button" class="close" data-dismiss="alert">&times;</button>')
          .show();

        // Auto hide after 5 seconds
        setTimeout(() => {
          alert.fadeOut();
        }, 5000);
      }
    });
  </script>

</body>

</html>