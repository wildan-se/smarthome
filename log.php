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
  <title>Log Akses & Sensor</title>
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
        <h1>Log Akses & Sensor</h1>
      </section>
      <section class="content">
        <div class="card mb-3">
          <div class="card-header">Log RFID</div>
          <div class="card-body">
            <table class="table table-bordered" id="tableRFIDLog">
              <thead>
                <tr>
                  <th>UID</th>
                  <th>Waktu</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <div class="card mb-3">
          <div class="card-header">Log Suhu & Kelembapan</div>
          <div class="card-body">
            <table class="table table-bordered" id="tableDHTLog">
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Suhu (°C)</th>
                  <th>Kelembapan (%)</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <div class="card mb-3">
          <div class="card-header">Log Status Pintu</div>
          <div class="card-body">
            <table class="table table-bordered" id="tableDoorLog">
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    function loadRFIDLog() {
      $.get('api/rfid_crud.php?action=log', function(data) {
        let rows = '';
        data.forEach(l => {
          rows += `<tr><td>${l.uid}</td><td>${l.access_time}</td><td>${l.status}</td></tr>`;
        });
        $('#tableRFIDLog tbody').html(rows);
      }, 'json');
    }

    function loadDHTLog() {
      $.get('api/dht_log.php', function(data) {
        let rows = '';
        data.forEach(l => {
          rows += `<tr><td>${l.log_time}</td><td>${l.temperature}</td><td>${l.humidity}</td></tr>`;
        });
        $('#tableDHTLog tbody').html(rows);
      }, 'json');
    }

    function loadDoorLog() {
      $.get('api/door_log.php', function(data) {
        let rows = '';
        data.forEach(l => {
          rows += `<tr><td>${l.updated_at}</td><td>${l.status}</td></tr>`;
        });
        $('#tableDoorLog tbody').html(rows);
      }, 'json');
    }
    $(function() {
      loadRFIDLog();
      loadDHTLog();
      loadDoorLog();
    });
  </script>
</body>

</html>