<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Kontrol Pintu Servo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-arrow-left"></i> Dashboard</a></li>
      </ul>
    </nav>
    <div class="content-wrapper p-4">
      <section class="content-header">
        <h1>Kontrol Pintu Servo</h1>
      </section>
      <section class="content">
        <div class="card mb-3">
          <div class="card-body">
            <button class="btn btn-success mr-2" onclick="bukaPintu()"><i class="fas fa-door-open"></i> Buka Pintu (90°)</button>
            <button class="btn btn-danger" onclick="tutupPintu()"><i class="fas fa-door-closed"></i> Tutup Pintu (0°)</button>
            <div id="servoResult" class="mt-3"></div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">Slider Manual Servo</div>
          <div class="card-body">
            <label for="servoSlider">Posisi Servo (<span id="sliderValue" style="font-weight:bold;color:#007bff;">0</span>°)</label>
            <input type="range" min="0" max="180" value="0" id="servoSlider" class="form-range" style="width:100%;">
            <button class="btn btn-primary mt-2" onclick="setServo()"><i class="fas fa-sliders-h"></i> Set Posisi</button>
          </div>
        </div>
      </section>
    </div>
  </div>
  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
  <script>
    const broker = "iotsmarthome.cloud.shiftr.io";
    const mqttUser = "iotsmarthome";
    const mqttPass = "gxBVaUn5Bvf9yfIm";
    const serial = "12345678";
    const topicRoot = `smarthome/${serial}`;
    const client = mqtt.connect(`wss://${broker}`, {
      username: mqttUser,
      password: mqttPass
    });

    function bukaPintu() {
      client.publish(`${topicRoot}/servo`, '90');
      document.getElementById('servoResult').innerHTML = '<span class="text-success">Perintah buka pintu dikirim ke ESP32.</span>';
    }

    function tutupPintu() {
      client.publish(`${topicRoot}/servo`, '0');
      document.getElementById('servoResult').innerHTML = '<span class="text-danger">Perintah tutup pintu dikirim ke ESP32.</span>';
    }
    // Slider manual
    const slider = document.getElementById('servoSlider');
    const sliderValue = document.getElementById('sliderValue');
    slider.addEventListener('input', function() {
      sliderValue.textContent = slider.value;
    });

    function setServo() {
      client.publish(`${topicRoot}/servo`, slider.value);
      document.getElementById('servoResult').innerHTML = `<span class='text-info'>Posisi servo diatur ke ${slider.value}°.</span>`;
    }
  </script>
</body>

</html>