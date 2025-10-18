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
  <title>Manajemen Kartu RFID - Smart Home IoT</title>
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
              <a href="rfid.php" class="nav-link active">
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
                <i class="fas fa-id-card text-primary"></i> Manajemen Kartu RFID
              </h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">RFID</li>
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
            Tambahkan kartu RFID baru dengan memasukkan UID dan nama pemilik. Sistem akan sinkronisasi dengan ESP32 secara otomatis.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <!-- Form Tambah Kartu -->
          <div class="card card-primary card-outline shadow-sm hover-shadow fadeIn">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-plus-circle"></i> Tambah Kartu RFID Baru
              </h3>
            </div>
            <div class="card-body">
              <form id="formAddRFID">
                <div class="row">
                  <div class="col-md-5">
                    <div class="form-group">
                      <label for="inputUID">
                        <i class="fas fa-fingerprint"></i> UID Kartu RFID
                        <span class="text-danger">*</span>
                      </label>
                      <input
                        type="text"
                        name="uid"
                        id="inputUID"
                        class="form-control"
                        placeholder="Contoh: A61F1905"
                        required>
                      <small class="form-text text-muted">
                        <i class="fas fa-info-circle"></i> Masukkan UID dari kartu RFID (8 karakter)
                      </small>
                    </div>
                  </div>
                  <div class="col-md-5">
                    <div class="form-group">
                      <label for="inputName">
                        <i class="fas fa-user"></i> Nama Pemilik Kartu
                        <span class="text-danger">*</span>
                      </label>
                      <input
                        type="text"
                        name="name"
                        id="inputName"
                        class="form-control"

                        required>
                      <small class="form-text text-muted">
                        <i class="fas fa-info-circle"></i> Nama pengguna yang akan menggunakan kartu
                      </small>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-success btn-block btn-lg shadow-sm">
                      <i class="fas fa-plus-circle"></i> Tambah
                    </button>
                  </div>
                </div>
              </form>
              <div id="addResult" class="mt-3"></div>
            </div>
          </div>

          <!-- Daftar Kartu Terdaftar -->
          <div class="card card-success card-outline shadow-sm hover-shadow fadeIn mt-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-id-card"></i> Daftar Kartu Terdaftar
              </h3>
              <div class="card-tools">
                <span class="badge badge-success" id="cardCount">0 Kartu</span>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover table-striped modern-table" id="tableRFID">
                  <thead>
                    <tr>
                      <th width="50" class="text-center">#</th>
                      <th><i class="fas fa-fingerprint"></i> UID Kartu</th>
                      <th><i class="fas fa-user"></i> Nama Pemilik</th>
                      <th><i class="far fa-calendar-alt"></i> Ditambahkan</th>
                      <th><i class="fas fa-user-shield"></i> Admin</th>
                      <th width="200" class="text-center"><i class="fas fa-cog"></i> Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="6" class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Memuat data...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Riwayat Akses Kartu -->
          <div class="card card-warning card-outline shadow-sm hover-shadow fadeIn mt-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-history"></i> Riwayat Akses Kartu Terbaru
              </h3>
              <div class="card-tools">
                <button type="button" class="btn btn-tool" onclick="loadLog()">
                  <i class="fas fa-sync-alt"></i>
                </button>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover table-striped modern-table" id="tableLog">
                  <thead>
                    <tr>
                      <th width="50" class="text-center">#</th>
                      <th><i class="fas fa-fingerprint"></i> UID Kartu</th>
                      <th><i class="fas fa-user"></i> Nama Pengguna</th>
                      <th><i class="far fa-clock"></i> Waktu Akses</th>
                      <th width="150" class="text-center"><i class="fas fa-check-circle"></i> Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="5" class="text-center text-muted">
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
    // MQTT Configuration from Database
    const broker = "<?php echo $mqttBroker; ?>";
    const mqttUser = "<?php echo $mqttCredentials['username']; ?>";
    const mqttPass = "<?php echo $mqttCredentials['password']; ?>";
    const serial = "<?php echo $deviceSerial; ?>";
    const topicRoot = `smarthome/${serial}`;
    const mqttProtocol = "<?php echo $mqttProtocol; ?>";

    const client = mqtt.connect(`${mqttProtocol}://${broker}`, {
      username: mqttUser,
      password: mqttPass,
      clientId: 'rfid-' + Math.random().toString(16).substr(2, 8)
    });

    let lastScannedUID = ''; // Menyimpan UID terakhir yang di-scan

    $('#formAddRFID').submit(function(e) {
      e.preventDefault();
      const uid = $(this).find('[name=uid]').val();
      const name = $(this).find('[name=name]').val();

      // Simpan nama ke localStorage untuk digunakan saat ESP32 konfirmasi
      localStorage.setItem('pendingCard_' + uid, name);

      // Kirim perintah register ke ESP32
      client.publish(`${topicRoot}/rfid/register`, uid);

      $('#addResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Perintah tambah kartu dikirim ke ESP32, menunggu konfirmasi...</div>');
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

        // Simpan UID terakhir untuk fallback
        if (data.uid) {
          lastScannedUID = data.uid;
        }

        if (data.action === 'add' && data.result === 'ok' && data.uid) {
          // Ambil nama dari localStorage
          const name = localStorage.getItem('pendingCard_' + data.uid) || '';
          localStorage.removeItem('pendingCard_' + data.uid);

          // Tambah kartu ke database dengan nama
          $.post('api/rfid_crud.php?action=add', {
            uid: data.uid,
            name: name
          }, function(res) {
            if (res.success) {
              $('#addResult').html('<div class="alert alert-success"><i class="fas fa-check-circle"></i> Kartu <strong>' + data.uid + '</strong> (' + name + ') berhasil ditambahkan!</div>');
              $('#formAddRFID')[0].reset();
              loadRFID();
            } else {
              $('#addResult').html('<div class="alert alert-danger"><i class="fas fa-times-circle"></i> ' + (res.error || 'Gagal tambah kartu') + '</div>');
            }
          }, 'json');
        }

        if (data.action === 'remove' && data.result === 'ok' && data.uid) {
          // Hapus kartu dari database
          $.post('api/rfid_crud.php?action=remove', {
            uid: data.uid
          }, function(res) {
            if (res.success) {
              $('#addResult').html('<div class="alert alert-success"><i class="fas fa-check-circle"></i> Kartu <strong>' + data.uid + '</strong> berhasil dihapus dari ESP32 dan database!</div>');
              loadRFID();
            } else {
              $('#addResult').html('<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Kartu dihapus dari ESP32, tapi gagal hapus dari database: ' + (res.error || 'Unknown error') + '</div>');
            }
          }, 'json').fail(function() {
            $('#addResult').html('<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Error: Gagal menghubungi server untuk hapus dari database</div>');
          });
        }

        // Handle jika ESP32 tidak menemukan kartu saat hapus
        if (data.action === 'remove' && data.result === 'not_found' && data.uid) {
          $('#addResult').html('<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Kartu tidak ditemukan di ESP32, menghapus dari database saja...</div>');

          // Tetap hapus dari database
          $.post('api/rfid_crud.php?action=remove', {
            uid: data.uid
          }, function(res) {
            if (res.success) {
              $('#addResult').html('<div class="alert alert-success"><i class="fas fa-check-circle"></i> Kartu berhasil dihapus dari database!</div>');
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

        // Ambil UID dari data atau gunakan lastScannedUID sebagai fallback
        const uid = data.uid || lastScannedUID || 'unknown';

        // ❌ SKIP jika dari kontrol manual - tidak dicatat di riwayat
        if (uid === 'MANUAL_CONTROL') {
          console.log('⚠️ Skipping manual control from RFID access log');
          return;
        }

        // Log akses kartu (hanya untuk kartu fisik)
        if (data.status) {
          $.post('api/rfid_crud.php?action=log', {
            uid: uid,
            status: data.status
          }, function(res) {
            if (res.success) {
              loadLog();
              // Reset lastScannedUID setelah digunakan
              if (!data.uid) lastScannedUID = '';
            }
          }, 'json');
        }
      }
    });

    function loadRFID() {
      $.get('api/rfid_crud.php?action=list', function(res) {
        let rows = '';
        if (res.success && res.data) {
          res.data.forEach((r, index) => {
            const name = r.name || '<em class="text-muted">Tidak ada nama</em>';
            const addedBy = r.added_by_name || 'System';
            rows += `
              <tr class="fadeIn">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td><code class="code-badge">${r.uid}</code></td>
                <td><i class="fas fa-user text-muted"></i> ${name}</td>
                <td><i class="far fa-calendar text-muted"></i> ${r.added_at}</td>
                <td><span class="badge badge-info"><i class="fas fa-user-shield"></i> ${addedBy}</span></td>
                <td class="text-center">
                  <button class='btn btn-danger btn-sm shadow-sm' onclick='removeRFID("${r.uid}")' title='Hapus dari ESP32 dan Database'>
                    <i class='fas fa-trash-alt'></i> Hapus
                  </button>
                  <button class='btn btn-outline-secondary btn-sm ml-1' onclick='removeFromDBOnly("${r.uid}")' title='Hapus dari Database saja (ESP32 offline)'>
                    <i class='fas fa-database'></i> DB Only
                  </button>
                </td>
              </tr>
            `;
          });
          $('#cardCount').html(`<i class="fas fa-id-card"></i> ${res.data.length} Kartu`);
        } else {
          $('#cardCount').html('<i class="fas fa-id-card"></i> 0 Kartu');
        }
        $('#tableRFID tbody').html(rows || '<tr><td colspan="6" class="text-center text-muted"><i class="fas fa-inbox"></i><br>Belum ada kartu terdaftar</td></tr>');
      }, 'json');
    }

    function removeRFID(uid) {
      if (!confirm('Apakah Anda yakin ingin menghapus kartu dengan UID: ' + uid + '?')) {
        return;
      }

      // Kirim perintah hapus ke ESP32
      client.publish(`${topicRoot}/rfid/remove`, uid);
      $('#addResult').html('<div class="alert alert-warning"><i class="fas fa-spinner fa-spin"></i> Perintah hapus kartu dikirim ke ESP32, menunggu konfirmasi...</div>');

      // Set timeout fallback: jika 5 detik tidak ada respons dari ESP32, hapus langsung dari database
      setTimeout(function() {
        if ($('#addResult').html().includes('menunggu konfirmasi')) {
          // Masih menunggu, berarti ESP32 tidak merespons
          if (confirm('ESP32 tidak merespons. Hapus kartu dari database saja?')) {
            $.post('api/rfid_crud.php?action=remove', {
              uid: uid
            }, function(res) {
              if (res.success) {
                $('#addResult').html('<div class="alert alert-success"><i class="fas fa-check-circle"></i> Kartu berhasil dihapus dari database (ESP32 offline/tidak merespons)</div>');
                loadRFID();
              } else {
                $('#addResult').html('<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Gagal menghapus: ' + (res.error || 'Unknown error') + '</div>');
              }
            }, 'json');
          } else {
            $('#addResult').html('');
          }
        }
      }, 5000); // Timeout 5 detik
    }

    function removeFromDBOnly(uid) {
      if (!confirm('Hapus kartu ' + uid + ' dari DATABASE saja?\n\n⚠️ Kartu masih akan tersimpan di ESP32.\nGunakan fungsi ini hanya jika ESP32 offline.')) {
        return;
      }

      $.post('api/rfid_crud.php?action=remove', {
        uid: uid
      }, function(res) {
        if (res.success) {
          $('#addResult').html('<div class="alert alert-success"><i class="fas fa-check-circle"></i> Kartu <strong>' + uid + '</strong> berhasil dihapus dari database!</div>');
          loadRFID();
        } else {
          $('#addResult').html('<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Gagal menghapus: ' + (res.error || 'Unknown error') + '</div>');
        }
      }, 'json').fail(function() {
        $('#addResult').html('<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Error: Gagal menghubungi server</div>');
      });
    }

    function loadLog() {
      $.get('api/rfid_crud.php?action=getlogs', function(res) {
        let rows = '';
        if (res.success && res.data) {
          // Batasi 20 log terbaru saja
          const recentLogs = res.data.slice(0, 20);
          recentLogs.forEach((l, index) => {
            const name = l.name || '<em class="text-muted">Tidak terdaftar</em>';
            const statusClass = l.status === 'granted' ? 'success' : 'danger';
            const statusIcon = l.status === 'granted' ? 'check-circle' : 'times-circle';
            const statusText = l.status === 'granted' ? 'Diterima' : 'Ditolak';

            rows += `
              <tr class="fadeIn">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td><code class="code-badge">${l.uid}</code></td>
                <td><i class="fas fa-user text-muted"></i> ${name}</td>
                <td><i class="far fa-clock text-muted"></i> ${l.access_time}</td>
                <td class="text-center">
                  <span class="badge badge-${statusClass} pulse">
                    <i class="fas fa-${statusIcon}"></i> ${statusText}
                  </span>
                </td>
              </tr>
            `;
          });
        }
        $('#tableLog tbody').html(rows || '<tr><td colspan="5" class="text-center text-muted"><i class="fas fa-inbox"></i><br>Belum ada riwayat akses</td></tr>');
      }, 'json');
    }

    $(function() {
      loadRFID();
      loadLog();
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