<?php
require_once __DIR__ . '/../src/auth.php';
require_login();
$config = require __DIR__ . '/../src/config.php';
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($config['app']['name']); ?> - Dashboard</title>
  <link rel="stylesheet" href="/scss/adminlte.css">
  <link rel="stylesheet" href="/scss/adminlte.min.css">
</head>

<body>
  <div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" href="#" role="button">Dashboard</a>
        </li>
      </ul>
      <ul class="navbar-nav ml-auto">
        <li class="nav-item">
          <a class="nav-link" href="/logout.php">Logout</a>
        </li>
      </ul>
    </nav>

    <div class="content-wrapper">
      <section class="content-header">
        <div class="container-fluid">
          <h1>Dashboard Realtime</h1>
        </div>
      </section>

      <section class="content">
        <div class="container-fluid">
          <div class="row">
            <div class="col-md-4">
              <div class="card card-primary">
                <div class="card-header">
                  <h3 class="card-title">Status Perangkat</h3>
                </div>
                <div class="card-body">
                  <p>ESP32: <span id="esp-status">-</span></p>
                  <p>Pintu: <span id="door-status">-</span></p>
                  <p>RFID Terakhir: <span id="rfid-last">-</span></p>
                  <p>Akses: <span id="rfid-access">-</span></p>
                </div>
              </div>
            </div>

            <div class="col-md-8">
              <div class="card card-info">
                <div class="card-header">
                  <h3 class="card-title">Suhu & Kelembapan</h3>
                </div>
                <div class="card-body">
                  <canvas id="chartTemp" height="100"></canvas>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>

  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // MQTT over WebSocket connection
    const mqttConfig = <?php echo json_encode($config['mqtt']); ?>;
    const clientId = 'webclient-' + Math.random().toString(16).substr(2, 8);
    const host = mqttConfig.host;
    const port = mqttConfig.port;
    const username = mqttConfig.username;
    const password = mqttConfig.password;
    const topicRoot = mqttConfig.topic_root || 'smarthome/12345678';

    // Use secure websocket (wss) for shiftr.io. shiftr usually accepts WSS on port 443.
    const wsUrl = 'wss://' + host + ':443';
    const client = mqtt.connect(wsUrl, {
      clientId,
      username,
      password,
      protocol: 'wss'
    });

    client.on('connect', function() {
      console.log('MQTT connected');
      document.getElementById('esp-status').innerText = 'online';
      client.subscribe(topicRoot + '/#');
    });

    client.on('message', function(topic, message) {
      const payload = message.toString();
      console.log('msg', topic, payload);
      if (topic.endsWith('/dht/temperature')) {
        document.getElementById('chartTemp') && updateChart(parseFloat(payload), null);
      }
      if (topic.endsWith('/dht/humidity')) {
        document.getElementById('chartTemp') && updateChart(null, parseFloat(payload));
      }
      if (topic.endsWith('/pintu/status')) {
        document.getElementById('door-status').innerText = payload;
      }
      if (topic.endsWith('/rfid/access')) {
        try {
          const j = JSON.parse(payload);
          document.getElementById('rfid-access').innerText = j.status;
        } catch (e) {
          document.getElementById('rfid-access').innerText = payload;
        }
        document.getElementById('rfid-last').innerText = new Date().toLocaleString();
      }
    });

    client.on('close', function() {
      document.getElementById('esp-status').innerText = 'offline';
    });

    // Chart.js minimal
    let tempData = [];
    let humData = [];
    const ctx = document.getElementById('chartTemp').getContext('2d');
    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
          label: 'Suhu (°C)',
          data: [],
          borderColor: 'red',
          fill: false
        }, {
          label: 'Kelembapan (%)',
          data: [],
          borderColor: 'blue',
          fill: false
        }]
      },
      options: {
        responsive: true,
        scales: {
          x: {
            display: true
          }
        }
      }
    });

    function updateChart(t, h) {
      const now = new Date().toLocaleTimeString();
      chart.data.labels.push(now);
      if (t !== null) chart.data.datasets[0].data.push(t);
      else chart.data.datasets[0].data.push(null);
      if (h !== null) chart.data.datasets[1].data.push(h);
      else chart.data.datasets[1].data.push(null);
      if (chart.data.labels.length > 30) {
        chart.data.labels.shift();
        chart.data.datasets.forEach(ds => ds.data.shift());
      }
      chart.update();
    }
  </script>
</body>

</html>