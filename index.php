<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Dashboard Admin IoT Smarthome</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- AdminLTE CSS 3.2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- MQTT.js -->
  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
</head>

<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <!-- Navbar AdminLTE -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a></li>
        <li class="nav-item d-none d-sm-inline-block"><a href="#" class="nav-link">Home</a></li>
      </ul>
      <ul class="navbar-nav ml-auto">
        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </nav>
    <!-- Sidebar AdminLTE -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
      <a href="#" class="brand-link">
        <img src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/img/AdminLTELogo.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">Smarthome IoT</span>
      </a>
      <div class="sidebar">
        <nav class="mt-2">
          <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
            <li class="nav-item"><a href="index.php" class="nav-link active"><i class="nav-icon fas fa-tachometer-alt"></i>
                <p>Dashboard</p>
              </a></li>
            <li class="nav-item"><a href="rfid.php" class="nav-link"><i class="nav-icon fas fa-id-card"></i>
                <p>Manajemen RFID</p>
              </a></li>
            <li class="nav-item"><a href="kontrol.php" class="nav-link"><i class="nav-icon fas fa-door-open"></i>
                <p>Kontrol Pintu</p>
              </a></li>
            <li class="nav-item"><a href="log.php" class="nav-link"><i class="nav-icon fas fa-list"></i>
                <p>Log Akses</p>
              </a></li>
            <li class="nav-item"><a href="export.php" class="nav-link"><i class="nav-icon fas fa-file-export"></i>
                <p>Export Data</p>
              </a></li>
            <li class="nav-item"><a href="logout.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i>
                <p>Logout</p>
              </a></li>
          </ul>
        </nav>
      </div>
    </aside>
    <div class="content-wrapper">
      <section class="content-header">
        <div class="container-fluid">
          <h1>Dashboard Realtime Smarthome</h1>
        </div>
      </section>
      <section class="content">
        <div class="row">
          <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
              <div class="inner">
                <h3 id="esp_status">-</h3>
                <p>Status ESP32</p>
              </div>
              <div class="icon"><i class="fas fa-microchip"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
              <div class="inner">
                <h3 id="door_status">-</h3>
                <p>Status Pintu</p>
              </div>
              <div class="icon"><i class="fas fa-door-open"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
              <div class="inner">
                <h3 id="temperature">-</h3>
                <p>Suhu Ruangan (°C)</p>
              </div>
              <div class="icon"><i class="fas fa-thermometer-half"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
              <div class="inner">
                <h3 id="humidity">-</h3>
                <p>Kelembapan (%)</p>
              </div>
              <div class="icon"><i class="fas fa-tint"></i></div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">Grafik Suhu & Kelembapan</div>
              <div class="card-body">
                <canvas id="dhtChart"></canvas>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card">
              <div class="card-header">RFID Terakhir</div>
              <div class="card-body">
                <p>UID: <span id="last_rfid">-</span></p>
                <p>Waktu: <span id="last_rfid_time">-</span></p>
                <p>Status: <span id="last_rfid_status">-</span></p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
    <!-- Footer AdminLTE -->
    <footer class="main-footer text-center">
      <strong>&copy; <?php echo date('Y'); ?> Smarthome IoT</strong> - Powered by AdminLTE
    </footer>
  </div>
  <script>
    // MQTT.js setup
    const broker = "iotsmarthome.cloud.shiftr.io";
    const mqttUser = "iotsmarthome";
    const mqttPass = "gxBVaUn5Bvf9yfIm";
    const serial = "12345678";
    const topicRoot = `smarthome/${serial}`;
    const client = mqtt.connect(`wss://${broker}`, {
      username: mqttUser,
      password: mqttPass
    });

    client.on('connect', () => {
      document.getElementById('esp_status').innerText = 'Online';
      client.subscribe(`${topicRoot}/#`);
    });
    client.on('offline', () => {
      document.getElementById('esp_status').innerText = 'Offline';
    });
    client.on('message', (topic, message) => {
      if (topic.endsWith('/pintu/status')) {
        document.getElementById('door_status').innerText = message;
      }
      if (topic.endsWith('/dht/temperature')) {
        document.getElementById('temperature').innerText = message;
        addChartData('temperature', parseFloat(message));
        sendLog('dht', {
          temperature: message
        });
      }
      if (topic.endsWith('/dht/humidity')) {
        document.getElementById('humidity').innerText = message;
        addChartData('humidity', parseFloat(message));
        sendLog('dht', {
          humidity: message
        });
      }
      if (topic.endsWith('/rfid/access')) {
        let data = {};
        try {
          data = JSON.parse(message);
        } catch {}
        document.getElementById('last_rfid_status').innerText = data.status || '-';
        document.getElementById('last_rfid_time').innerText = new Date().toLocaleString();
        sendLog('rfid', {
          status: data.status
        });
      }
      if (topic.endsWith('/rfid/info')) {
        let data = {};
        try {
          data = JSON.parse(message);
        } catch {}
        document.getElementById('last_rfid').innerText = data.uid || '-';
        document.getElementById('last_rfid_time').innerText = new Date().toLocaleString();
        sendLog('rfid', {
          uid: data.uid,
          action: data.action,
          result: data.result
        });
      }
    });

    // Chart.js setup
    const ctx = document.getElementById('dhtChart').getContext('2d');
    const dhtChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
            label: 'Suhu (°C)',
            data: [],
            borderColor: 'orange',
            fill: false
          },
          {
            label: 'Kelembapan (%)',
            data: [],
            borderColor: 'blue',
            fill: false
          }
        ]
      },
      options: {
        responsive: true
      }
    });

    function addChartData(type, value) {
      const now = new Date().toLocaleTimeString();
      if (dhtChart.data.labels.length > 20) {
        dhtChart.data.labels.shift();
        dhtChart.data.datasets[0].data.shift();
        dhtChart.data.datasets[1].data.shift();
      }
      dhtChart.data.labels.push(now);
      if (type === 'temperature') dhtChart.data.datasets[0].data.push(value);
      if (type === 'humidity') dhtChart.data.datasets[1].data.push(value);
      dhtChart.update();
    }
    // AJAX log ke backend
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
  </script>
</body>

</html>