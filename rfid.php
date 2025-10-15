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
  <title>Manajemen Kartu RFID</title>
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
        <h1>Manajemen Kartu RFID</h1>
      </section>
      <section class="content">
        <div class="card">
          <div class="card-header">Tambah Kartu Baru</div>
          <div class="card-body">
            <form id="formAddRFID" class="form-inline mb-3">
              <input type="text" name="uid" class="form-control mr-2" placeholder="UID Kartu RFID" required>
              <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Tambah</button>
            </form>
            <div id="addResult"></div>
          </div>
        </div>
        <div class="card mt-3">
          <div class="card-header">Daftar Kartu Terdaftar</div>
          <div class="card-body">
            <table class="table table-bordered" id="tableRFID">
              <thead>
                <tr>
                  <th>UID</th>
                  <th>Ditambah</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <div class="card mt-3">
          <div class="card-header">Riwayat Akses Kartu</div>
          <div class="card-body">
            <table class="table table-bordered" id="tableLog">
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
      </section>
    </div>
  </div>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    $('#formAddRFID').submit(function(e) {
      e.preventDefault();
      const uid = $(this).find('[name=uid]').val();
      client.publish(`${topicRoot}/rfid/register`, uid);
      $('#addResult').html('<span class="text-info">Perintah tambah kartu dikirim ke ESP32.</span>');
    });

    // Subscribe MQTT untuk sinkronisasi kartu dan log
    client.on('connect', function() {
      client.subscribe(`${topicRoot}/rfid/info`);
      client.subscribe(`${topicRoot}/rfid/access`);
    });
    client.on('message', function(topic, message) {
      if (topic.endsWith('/rfid/info')) {
        let data = {};
        try {
          data = JSON.parse(message);
        } catch {}
        if (data.action === 'add' && data.result === 'ok' && data.uid) {
          // Tambah kartu ke database
          $.post('api/rfid_crud.php?action=add', {
            uid: data.uid
          }, function(res) {
            if (res.success) {
              $('#addResult').html('<span class="text-success">Kartu berhasil ditambah ke database.</span>');
              loadRFID();
            } else {
              $('#addResult').html('<span class="text-danger">' + (res.error || 'Gagal tambah kartu') + '</span>');
            }
          }, 'json');
        }
        if (data.action === 'remove' && data.result === 'ok' && data.uid) {
          // Hapus kartu dari database
          $.post('api/rfid_crud.php?action=remove', {
            uid: data.uid
          }, function(res) {
            if (res.success) {
              $('#addResult').html('<span class="text-success">Kartu berhasil dihapus dari database.</span>');
              loadRFID();
            }
          }, 'json');
        }
      }
      if (topic.endsWith('/rfid/access')) {
        let data = {};
        try {
          data = JSON.parse(message);
        } catch {}
        // Log akses kartu
        if (data.uid && data.status) {
          $.post('api/rfid_crud.php?action=log', {
            uid: data.uid,
            status: data.status
          }, function(res) {
            if (res.success) loadLog();
          }, 'json');
        }
      }
    });

    function loadRFID() {
      $.get('api/rfid_crud.php?action=list', function(data) {
        let rows = '';
        data.forEach(r => {
          rows += `<tr><td>${r.uid}</td><td>${r.added_at}</td><td><button class='btn btn-danger btn-sm' onclick='removeRFID("${r.uid}")'><i class='fas fa-trash'></i> Hapus</button></td></tr>`;
        });
        $('#tableRFID tbody').html(rows);
      }, 'json');
    }

    function removeRFID(uid) {
      client.publish(`${topicRoot}/rfid/remove`, uid);
      $('#addResult').html('<span class="text-warning">Perintah hapus kartu dikirim ke ESP32.</span>');
    }

    function loadLog() {
      $.get('api/rfid_crud.php?action=log', function(data) {
        let rows = '';
        data.forEach(l => {
          rows += `<tr><td>${l.uid}</td><td>${l.access_time}</td><td>${l.status}</td></tr>`;
        });
        $('#tableLog tbody').html(rows);
      }, 'json');
    }
    $(function() {
      loadRFID();
      loadLog();
    });
  </script>
</body>

</html>