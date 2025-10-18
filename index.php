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

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM rfid_cards");
$stmt->execute();
$total_cards = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM rfid_logs WHERE DATE(access_time) = CURDATE()");
$stmt->execute();
$today_access = $stmt->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Dashboard - Smart Home IoT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/custom.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
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
              <a href="index.php" class="nav-link active">
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
                <p>Log </p>
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

          <!-- Status Cards -->
          <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
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

            <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
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

            <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
              <div class="small-box bg-warning fade-in">
                <div class="inner">
                  <h3 id="temperature">-</h3>
                  <p>Suhu Ruangan (°C)</p>
                </div>
                <div class="icon"><i class="fas fa-thermometer-half"></i></div>
                <div class="small-box-footer">
                  <span id="temp_status">Menunggu data...</span>
                </div>
              </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
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
          </div>

          <!-- Charts and Info -->
          <div class="row">
            <!-- Chart -->
            <div class="col-lg-8 col-md-12 mb-4">
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
                  <canvas id="dhtChart" height="80"></canvas>
                </div>
                <div class="card-footer bg-white">
                  <small class="text-muted">
                    <i class="far fa-clock"></i> Update otomatis setiap data baru diterima
                  </small>
                </div>
              </div>
            </div>

            <!-- RFID Info & Stats -->
            <div class="col-lg-4 col-md-12">
              <!-- RFID Last Access -->
              <div class="card card-info shadow-md fade-in mb-4">
                <div class="card-header">
                  <h3 class="card-title"><i class="fas fa-id-card mr-2"></i>Akses RFID Terakhir</h3>
                </div>
                <div class="card-body">
                  <div class="info-box mb-3 shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-fingerprint"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">UID Kartu</span>
                      <span class="info-box-number" id="last_rfid" style="font-size: 1.2rem;">-</span>
                    </div>
                  </div>
                  <table class="table table-sm table-borderless">
                    <tr>
                      <td><i class="fas fa-clock text-primary"></i> Waktu:</td>
                      <td id="last_rfid_time" class="text-right font-weight-bold">-</td>
                    </tr>
                    <tr>
                      <td><i class="fas fa-check-circle text-success"></i> Status:</td>
                      <td class="text-right"><span id="last_rfid_status" class="badge badge-secondary">-</span></td>
                    </tr>
                  </table>
                </div>
              </div>

              <!-- Quick Stats -->
              <div class="row">
                <div class="col-12">
                  <div class="info-box shadow-md fade-in">
                    <span class="info-box-icon bg-primary"><i class="fas fa-id-card-alt"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">Total Kartu Terdaftar</span>
                      <span class="info-box-number"><?= number_format($total_cards) ?></span>
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="info-box shadow-md fade-in">
                    <span class="info-box-icon bg-success"><i class="fas fa-history"></i></span>
                    <div class="info-box-content">
                      <span class="info-box-text">Akses Hari Ini</span>
                      <span class="info-box-number"><?= number_format($today_access) ?></span>
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
    <footer class="main-footer">
      <strong>Copyright &copy; <?= date('Y') ?> <a href="index.php">Koneksi Pintar</a>.</strong>

      <div class="float-right d-none d-sm-inline-block">
        <b>Version</b> 1.0.0
      </div>
    </footer>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

  <script>
    // MQTT Configuration from Database
    const broker = "<?php echo $mqttBroker; ?>";
    const mqttUser = "<?php echo $mqttCredentials['username']; ?>";
    const mqttPass = "<?php echo $mqttCredentials['password']; ?>";
    const serial = "<?php echo $deviceSerial; ?>";
    const topicRoot = `smarthome/${serial}`;
    const statusTopic = `smarthome/status/${serial}`;
    const mqttProtocol = "<?php echo $mqttProtocol; ?>";

    // Variable untuk tracking sumber kontrol
    let controlSource = 'rfid'; // default: dari RFID, bisa jadi 'manual'

    // MQTT Client Connection
    const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
      username: mqttUser,
      password: mqttPass,
      clientId: 'dashboard-' + Math.random().toString(16).substr(2, 8)
    });

    client.on('connect', () => {
      console.log('✅ MQTT Dashboard Connected!');
      $('#esp_connection_time').text('Terhubung pada ' + new Date().toLocaleTimeString('id-ID'));
      client.subscribe(`${topicRoot}/#`);
      client.subscribe(statusTopic);

      // Request status ESP32 saat connect
      client.publish(`${topicRoot}/system/ping`, 'request_status');

      // Set initial status sebagai "Checking..."
      const statusBox = $('#esp_status');
      const statusCard = statusBox.closest('.small-box');
      statusBox.html('<i class="fas fa-spinner fa-spin"></i> Checking...');
      statusCard.removeClass('bg-danger bg-info').addClass('bg-warning');

      // Timeout 5 detik untuk cek apakah ESP32 response
      setTimeout(() => {
        const currentStatus = statusBox.text().trim();
        if (currentStatus.includes('Checking')) {
          // Jika masih checking, berarti ESP32 tidak response = offline
          statusBox.text('Offline');
          statusCard.removeClass('bg-warning bg-info').addClass('bg-danger');
          $('#esp_connection_time').text('ESP32 tidak merespon');
        }
      }, 5000);
    });

    client.on('offline', () => {
      console.log('❌ MQTT Dashboard Offline');
      $('#esp_connection_time').text('Terputus...');

      // Set ESP32 status offline juga
      const statusBox = $('#esp_status');
      const statusCard = statusBox.closest('.small-box');
      statusBox.text('Offline');
      statusCard.removeClass('bg-info bg-warning').addClass('bg-danger');
    });

    client.on('message', (topic, message) => {
      const msg = message.toString();
      console.log('📩 MQTT:', topic, '=>', msg);

      // ESP32 Status
      if (topic === statusTopic) {
        const statusBox = $('#esp_status');
        const statusCard = statusBox.closest('.small-box');
        if (msg === 'online') {
          statusBox.text('Online');
          statusCard.removeClass('bg-danger bg-warning').addClass('bg-info');
          $('#esp_connection_time').text('Online sejak ' + new Date().toLocaleTimeString('id-ID'));
        } else {
          statusBox.text('Offline');
          statusCard.removeClass('bg-info bg-warning').addClass('bg-danger');
          $('#esp_connection_time').text('Offline sejak ' + new Date().toLocaleTimeString('id-ID'));
        }
      }

      // Kontrol Source (manual atau rfid)
      if (topic === `${topicRoot}/kontrol/source`) {
        controlSource = msg; // 'manual' atau 'rfid'
        console.log('🎛️ Control Source:', controlSource);

        // Reset ke rfid setelah 2 detik (untuk handle kontrol berikutnya)
        setTimeout(() => {
          controlSource = 'rfid';
        }, 2000);
      }

      // Door Status
      if (topic === `${topicRoot}/pintu/status`) {
        const doorBox = $('#door_status');
        const doorCard = doorBox.closest('.small-box');
        const doorIcon = $('#door_icon');
        const updateTime = new Date().toLocaleTimeString('id-ID');

        // Auto-detect ESP32 online from door data
        markESP32Online();

        if (msg === 'terbuka') {
          doorBox.text('Terbuka');
          doorCard.removeClass('bg-success').addClass('bg-warning');
          doorIcon.removeClass('fa-door-closed').addClass('fa-door-open');
          $('#door_last_update').html('<i class="fas fa-door-open"></i> Update: ' + updateTime);

          // Hanya simpan log door, TIDAK simpan ke RFID log
          sendLog('door', {
            status: 'terbuka'
          });
        } else if (msg === 'tertutup') {
          doorBox.text('Tertutup');
          doorCard.removeClass('bg-warning').addClass('bg-success');
          doorIcon.removeClass('fa-door-open').addClass('fa-door-closed');
          $('#door_last_update').html('<i class="fas fa-door-closed"></i> Update: ' + updateTime);

          // Hanya simpan log door, TIDAK simpan ke RFID log
          sendLog('door', {
            status: 'tertutup'
          });
        }
      }

      // Temperature
      if (topic === `${topicRoot}/dht/temperature`) {
        const temp = parseFloat(msg);
        if (!isNaN(temp) && temp > 0 && temp < 100) {
          $('#temperature').text(temp.toFixed(1));
          addChartData('temperature', temp);

          // Auto-detect ESP32 online from sensor data
          markESP32Online();

          // Status indicator
          if (temp > 30) {
            $('#temp_status').html('<i class="fas fa-fire"></i> Panas');
          } else if (temp < 20) {
            $('#temp_status').html('<i class="fas fa-snowflake"></i> Dingin');
          } else {
            $('#temp_status').html('<i class="fas fa-check-circle"></i> Normal');
          }

          window.lastDHTData = window.lastDHTData || {};
          window.lastDHTData.temperature = temp;
          window.lastDHTData.tempTime = Date.now();

          if (window.lastDHTData.humidity && (Date.now() - window.lastDHTData.humTime) < 2000) {
            sendLog('dht', {
              temperature: temp,
              humidity: window.lastDHTData.humidity
            });
            delete window.lastDHTData.humidity;
            delete window.lastDHTData.humTime;
          }
        }
      }

      // Humidity
      if (topic === `${topicRoot}/dht/humidity`) {
        const hum = parseFloat(msg);
        if (!isNaN(hum) && hum > 0 && hum <= 100) {
          $('#humidity').text(hum.toFixed(1));
          addChartData('humidity', hum);

          // Auto-detect ESP32 online from sensor data
          markESP32Online();

          // Status indicator
          if (hum > 70) {
            $('#humidity_status').html('<i class="fas fa-water"></i> Lembap');
          } else if (hum < 30) {
            $('#humidity_status').html('<i class="fas fa-wind"></i> Kering');
          } else {
            $('#humidity_status').html('<i class="fas fa-check-circle"></i> Normal');
          }

          window.lastDHTData = window.lastDHTData || {};
          window.lastDHTData.humidity = hum;
          window.lastDHTData.humTime = Date.now();

          if (window.lastDHTData.temperature && (Date.now() - window.lastDHTData.tempTime) < 2000) {
            sendLog('dht', {
              temperature: window.lastDHTData.temperature,
              humidity: hum
            });
            delete window.lastDHTData.temperature;
            delete window.lastDHTData.tempTime;
          }
        }
      }

      // RFID Access
      if (topic === `${topicRoot}/rfid/access`) {
        let data = {};
        try {
          data = JSON.parse(msg);
        } catch (e) {
          console.error('Parse error:', e);
        }

        const status = data.status || '-';
        const uid = data.uid || '';

        // ❌ SKIP jika dari kontrol manual
        if (uid === 'MANUAL_CONTROL') {
          console.log('⚠️ Skipping manual control from RFID display');
          return;
        }

        // Auto-detect ESP32 online from RFID data
        markESP32Online();

        const statusElem = $('#last_rfid_status');

        if (status === 'granted') {
          statusElem.removeClass('badge-danger badge-secondary').addClass('badge-success');
          statusElem.html('<i class="fas fa-check-circle"></i> Akses Diterima');
        } else if (status === 'denied') {
          statusElem.removeClass('badge-success badge-secondary').addClass('badge-danger');
          statusElem.html('<i class="fas fa-times-circle"></i> Akses Ditolak');
        }

        $('#last_rfid_time').text(new Date().toLocaleString('id-ID'));

        // Simpan log RFID (hanya dari kartu fisik, bukan manual control)
        sendLog('rfid', {
          uid: uid,
          status: status
        });
      }

      // RFID Info
      if (topic === `${topicRoot}/rfid/info`) {
        let data = {};
        try {
          data = JSON.parse(msg);
        } catch (e) {
          console.error('Parse error:', e);
        }

        const uid = data.uid || '';

        // ❌ SKIP jika dari kontrol manual
        if (uid === 'MANUAL_CONTROL') {
          console.log('⚠️ Skipping manual control from RFID info display');
          return;
        }

        // Auto-detect ESP32 online from RFID data
        markESP32Online();

        if (uid) {
          $('#last_rfid').text(uid);
          $('#last_rfid_time').text(new Date().toLocaleString('id-ID'));
        }
      }
    });

    // Chart.js Configuration
    const ctx = document.getElementById('dhtChart').getContext('2d');
    const dhtChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
            label: 'Suhu (°C)',
            data: [],
            borderColor: 'rgb(255, 193, 7)',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true
          },
          {
            label: 'Kelembapan (%)',
            data: [],
            borderColor: 'rgb(23, 162, 184)',
            backgroundColor: 'rgba(23, 162, 184, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            display: true,
            position: 'top'
          },
          tooltip: {
            mode: 'index',
            intersect: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              display: true,
              color: 'rgba(0, 0, 0, 0.05)'
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        },
        interaction: {
          mode: 'nearest',
          axis: 'x',
          intersect: false
        }
      }
    });

    // Function to mark ESP32 as online when receiving data
    function markESP32Online() {
      const statusBox = $('#esp_status');
      const statusCard = statusBox.closest('.small-box');
      const currentStatus = statusBox.text().trim();

      // Only update if not already showing "Online"
      if (currentStatus !== 'Online') {
        console.log('✅ ESP32 detected online from data');
        statusBox.text('Online');
        statusCard.removeClass('bg-danger bg-warning').addClass('bg-info');
        $('#esp_connection_time').text('Online sejak ' + new Date().toLocaleTimeString('id-ID'));
      }
    }

    function addChartData(type, value) {
      const now = new Date().toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });

      if (dhtChart.data.labels.length > 20) {
        dhtChart.data.labels.shift();
        dhtChart.data.datasets[0].data.shift();
        dhtChart.data.datasets[1].data.shift();
      }

      if (dhtChart.data.labels.length === 0 || dhtChart.data.labels[dhtChart.data.labels.length - 1] !== now) {
        dhtChart.data.labels.push(now);
        dhtChart.data.datasets[0].data.push(type === 'temperature' ? value : null);
        dhtChart.data.datasets[1].data.push(type === 'humidity' ? value : null);
      } else {
        const lastIndex = dhtChart.data.labels.length - 1;
        if (type === 'temperature') {
          dhtChart.data.datasets[0].data[lastIndex] = value;
        } else if (type === 'humidity') {
          dhtChart.data.datasets[1].data[lastIndex] = value;
        }
      }

      dhtChart.update('none');
    }

    function sendLog(type, data) {
      fetch('api/receive_data.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          type,
          data
        })
      });
    }

    // Real-time Clock Update (Waktu Indonesia)
    function updateClock() {
      const now = new Date();

      // Format: DD MMM YYYY, HH:MM:SS
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

    // Update clock immediately and then every second
    updateClock();
    setInterval(updateClock, 1000);
  </script>
</body>

</html>