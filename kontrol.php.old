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
  <title>Kontrol Pintu Servo - Smart Home IoT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/custom.css">
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
              <a href="kipas.php" class="nav-link">
                <i class="nav-icon fas fa-fan"></i>
                <p>Kontrol Kipas</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="kontrol.php" class="nav-link active">
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
                  <button class="btn btn-success btn-lg btn-block shadow-sm" onclick="bukaPintu()" style="padding: 20px;">
                    <i class="fas fa-door-open" style="font-size: 2em;"></i>
                    <h4 class="mt-2 mb-0">Buka Pintu</h4>
                    <small class="text-white-50">Servo 90°</small>
                  </button>
                </div>
                <div class="col-md-6 mb-3">
                  <button class="btn btn-danger btn-lg btn-block shadow-sm" onclick="tutupPintu()" style="padding: 20px;">
                    <i class="fas fa-door-closed" style="font-size: 2em;"></i>
                    <h4 class="mt-2 mb-0">Tutup Pintu</h4>
                    <small class="text-white-50">Servo 0°</small>
                  </button>
                </div>
              </div>
              <div id="servoResult" class="mt-3"></div>
            </div>
          </div>

          <!-- Slider Manual Servo -->
          <div class="card card-warning card-outline shadow-sm hover-shadow fadeIn mt-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-sliders-h"></i> Kontrol Presisi Servo (0° - 180°)
              </h3>
            </div>
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-md-10">
                  <label for="servoSlider" class="font-weight-bold">
                    Posisi Servo: <span id="sliderValue" class="badge badge-primary" style="font-size: 1.2em;">0</span>°
                  </label>
                  <input
                    type="range"
                    min="0"
                    max="180"
                    value="0"
                    id="servoSlider"
                    class="custom-range"
                    style="cursor: pointer;">
                  <div class="d-flex justify-content-between text-muted small">
                    <span><i class="fas fa-door-closed"></i> 0° (Tertutup)</span>
                    <span><i class="fas fa-door-open"></i> 90° (Terbuka)</span>
                    <span>180° (Maksimal)</span>
                  </div>
                </div>
                <div class="col-md-2">
                  <button class="btn btn-primary btn-block btn-lg shadow-sm" onclick="setServo()">
                    <i class="fas fa-check-circle"></i><br>
                    <small>Terapkan</small>
                  </button>
                </div>
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
    // MQTT Configuration from Database
    const broker = "<?php echo $mqttBroker; ?>";
    const mqttUser = "<?php echo $mqttCredentials['username']; ?>";
    const mqttPass = "<?php echo $mqttCredentials['password']; ?>";
    const serial = "<?php echo $deviceSerial; ?>";
    const topicRoot = `smarthome/${serial}`;
    const statusTopic = `smarthome/status/${serial}`;
    const mqttProtocol = "<?php echo $mqttProtocol; ?>";

    const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
      username: mqttUser,
      password: mqttPass,
      clientId: 'kontrol-' + Math.random().toString(16).substr(2, 8)
    });

    // Koneksi MQTT
    client.on('connect', () => {
      console.log('✅ MQTT Kontrol Connected!');
      // Subscribe ke status ESP32 dan pintu
      client.subscribe(statusTopic, {
        qos: 1
      });
      client.subscribe(`${topicRoot}/pintu/status`, {
        qos: 1
      });

      // Request status terbaru dari ESP32
      setTimeout(() => {
        client.publish(`${topicRoot}/request`, 'status', {
          qos: 1
        });
        console.log('📤 Requesting current status from ESP32...');
      }, 500);
    });

    client.on('error', (err) => {
      console.error('MQTT Error:', err);
    });

    // Handle pesan MQTT
    client.on('message', (topic, message) => {
      const msg = message.toString();
      console.log('📩 MQTT:', topic, '=>', msg);

      // Status ESP32
      if (topic === statusTopic) {
        const espIcon = document.getElementById('espStatus').querySelector('i');
        const espText = document.getElementById('espText');

        if (msg === 'online') {
          espIcon.className = 'fas fa-circle text-success pulse';
          espText.textContent = 'Online';
          espText.className = 'text-success font-weight-bold mb-2';
        } else if (msg === 'offline') {
          espIcon.className = 'fas fa-circle text-danger pulse';
          espText.textContent = 'Offline';
          espText.className = 'text-danger font-weight-bold mb-2';
        }
      }

      // Status Pintu
      if (topic === `${topicRoot}/pintu/status`) {
        const status = msg.toLowerCase();

        if (status === 'terbuka' || status === 'tertutup') {
          updateDoorUI(status);
          console.log('🚪 Door Status Updated from MQTT:', status.toUpperCase());
        }
      }
    });

    function bukaPintu() {
      // Kirim perintah servo ke ESP32
      client.publish(`${topicRoot}/servo`, '90', {
        qos: 1
      });

      // Publish status pintu dengan flag manual control
      client.publish(`${topicRoot}/pintu/status`, 'terbuka', {
        retain: true,
        qos: 1
      });

      // Publish flag untuk identifikasi kontrol manual
      client.publish(`${topicRoot}/kontrol/source`, 'manual', {
        qos: 1
      });

      // Simpan ke database via API
      saveDoorStatus('terbuka');

      // Update UI lokal
      updateDoorUI('terbuka');

      // Update slider
      document.getElementById('servoSlider').value = 90;
      document.getElementById('sliderValue').textContent = 90;

      document.getElementById('servoResult').innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Perintah buka pintu dikirim ke ESP32.</div>';

      console.log('📤 Sent: Buka Pintu (90°) - Manual Control');
    }

    function tutupPintu() {
      // Kirim perintah servo ke ESP32
      client.publish(`${topicRoot}/servo`, '0', {
        qos: 1
      });

      // Publish status pintu dengan flag manual control
      client.publish(`${topicRoot}/pintu/status`, 'tertutup', {
        retain: true,
        qos: 1
      });

      // Publish flag untuk identifikasi kontrol manual
      client.publish(`${topicRoot}/kontrol/source`, 'manual', {
        qos: 1
      });

      // Simpan ke database via API
      saveDoorStatus('tertutup');

      // Update UI lokal
      updateDoorUI('tertutup');

      // Update slider
      document.getElementById('servoSlider').value = 0;
      document.getElementById('sliderValue').textContent = 0;

      document.getElementById('servoResult').innerHTML = '<div class="alert alert-danger"><i class="fas fa-check-circle"></i> Perintah tutup pintu dikirim ke ESP32.</div>';

      console.log('📤 Sent: Tutup Pintu (0°) - Manual Control');
    }

    // Slider manual
    const slider = document.getElementById('servoSlider');
    const sliderValue = document.getElementById('sliderValue');
    slider.addEventListener('input', function() {
      sliderValue.textContent = slider.value;
    });

    function setServo() {
      const pos = parseInt(slider.value);

      // Kirim perintah servo ke ESP32
      client.publish(`${topicRoot}/servo`, slider.value, {
        qos: 1
      });

      // Tentukan status pintu berdasarkan posisi (sama seperti logika di ESP32)
      const status = pos > 45 ? 'terbuka' : 'tertutup';
      client.publish(`${topicRoot}/pintu/status`, status, {
        retain: true,
        qos: 1
      });

      // Publish flag untuk identifikasi kontrol manual
      client.publish(`${topicRoot}/kontrol/source`, 'manual', {
        qos: 1
      });

      // Simpan ke database via API
      saveDoorStatus(status);

      // Update UI lokal
      updateDoorUI(status);

      // Update UI
      document.getElementById('servoResult').innerHTML = `<div class="alert alert-info"><i class="fas fa-check-circle"></i> Posisi servo diatur ke ${slider.value}° (Status: ${status})</div>`;

      console.log(`📤 Sent: Servo ${pos}° => Status ${status} - Manual Control`);
    }

    // Fungsi untuk update UI pintu
    function updateDoorUI(status) {
      const doorIcon = document.getElementById('doorIcon');
      const doorText = document.getElementById('doorText');

      if (status === 'terbuka') {
        doorIcon.className = 'fas fa-door-open text-success pulse';
        doorText.textContent = 'Terbuka';
        doorText.className = 'text-success font-weight-bold mb-2';
      } else {
        doorIcon.className = 'fas fa-door-closed text-secondary';
        doorText.textContent = 'Tertutup';
        doorText.className = 'text-secondary font-weight-bold mb-2';
      }
    }

    // Fungsi untuk simpan status ke database
    function saveDoorStatus(status) {
      fetch('api/receive_data.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            type: 'door',
            data: {
              status: status
            }
          })
        }).then(response => response.json())
        .then(data => {
          console.log('💾 Door status saved to DB:', data);
        })
        .catch(err => {
          console.error('❌ Error saving door status:', err);
        });
    }

    // Load status pintu dari database saat halaman dimuat
    function loadDoorStatus() {
      $.get('servo.php', function(data) {
        if (data && data.status) {
          updateDoorUI(data.status.toLowerCase());
          console.log('📊 Status dari database:', data.status);
        }
      }, 'json').fail(function() {
        console.warn('⚠️ Gagal load status dari database');
      });
    }

    // Load status saat halaman dimuat
    $(document).ready(function() {
      loadDoorStatus();

      // Refresh status setiap 10 detik sebagai backup
      setInterval(loadDoorStatus, 10000);
    });

    // Real-time Clock Update (Waktu Indonesia)
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
      const formattedTime = now.toLocaleString('id-ID', options);
      $('#current-time').text(formattedTime);
    }
    updateClock();
    setInterval(updateClock, 1000);
  </script>
</body>

</html>