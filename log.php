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
  <title>Log Akses & Sensor - Smart Home IoT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/custom.css">
  <style>
    /* Scrollable table with sticky header */
    .table-responsive.scrollable-table {
      max-height: 500px;
      overflow-y: auto;
      border: 1px solid #dee2e6;
      border-radius: 0.25rem;
    }

    .table-responsive.scrollable-table::-webkit-scrollbar {
      width: 8px;
    }

    .table-responsive.scrollable-table::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .table-responsive.scrollable-table::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 10px;
    }

    .table-responsive.scrollable-table::-webkit-scrollbar-thumb:hover {
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
          <a class="nav-link" href="#" onclick="refreshAllLogs()">
            <i class="fas fa-sync-alt"></i> Refresh
          </a>
        </li>
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
              <a href="kontrol.php" class="nav-link">
                <i class="nav-icon fas fa-door-open"></i>
                <p>Kontrol Pintu</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="log.php" class="nav-link active">
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
                <i class="fas fa-history text-primary"></i> Log Akses & Sensor
              </h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Log</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <section class="content">
        <div class="container-fluid">

          <!-- Statistics Row -->
          <div class="row">
            <div class="col-lg-3 col-md-6 col-12">
              <div class="info-box shadow-sm hover-shadow fadeIn" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                <span class="info-box-icon" style="background: rgba(255,255,255,0.2);">
                  <i class="fas fa-id-card"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text" style="color: rgba(255,255,255,0.9);">Total Akses RFID</span>
                  <span class="info-box-number" id="totalRfidLogs">-</span>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
              <div class="info-box shadow-sm hover-shadow fadeIn" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <span class="info-box-icon" style="background: rgba(255,255,255,0.2);">
                  <i class="fas fa-thermometer-half"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text" style="color: rgba(255,255,255,0.9);">Suhu Rata-rata</span>
                  <span class="info-box-number" id="avgTemp">-</span>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
              <div class="info-box shadow-sm hover-shadow fadeIn" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <span class="info-box-icon" style="background: rgba(255,255,255,0.2);">
                  <i class="fas fa-tint"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text" style="color: rgba(255,255,255,0.9);">Kelembapan Rata-rata</span>
                  <span class="info-box-number" id="avgHum">-</span>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
              <div class="info-box shadow-sm hover-shadow fadeIn" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                <span class="info-box-icon" style="background: rgba(255,255,255,0.2);">
                  <i class="fas fa-door-open"></i>
                </span>
                <div class="info-box-content">
                  <span class="info-box-text" style="color: rgba(255,255,255,0.9);">Perubahan Pintu</span>
                  <span class="info-box-number" id="doorChanges">-</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Log RFID Access -->
          <div class="card card-primary card-outline shadow-sm hover-shadow fadeIn mt-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-id-card"></i> Log Akses RFID
              </h3>
              <div class="card-tools">
                <span class="badge badge-info mr-2" id="rfidCount">0 records</span>
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                  <i class="fas fa-minus"></i>
                </button>
              </div>
            </div>
            <div class="card-body">
              <!-- Filter Section -->
              <div class="card card-secondary card-outline mb-3">
                <div class="card-body">
                  <div class="row align-items-end">
                    <div class="col-md-5">
                      <div class="form-group mb-0">
                        <label class="d-block"><i class="fas fa-filter"></i> Filter Status:</label>
                        <select class="form-control p-1" id="filterRfidStatus">
                          <option value="">Semua Status</option>
                          <option value="granted">✅ Akses Diterima</option>
                          <option value="denied">❌ Akses Ditolak</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-5">
                      <div class="form-group mb-0">
                        <label class="d-block"><i class="fas fa-search"></i> Cari UID/Nama:</label>
                        <input type="text" class="form-control" id="searchRfid" placeholder="Ketik untuk mencari...">
                      </div>
                    </div>
                    <div class="col-md-2">
                      <div class="form-group mb-0">
                        <button class="btn btn-primary btn-block shadow-sm" onclick="loadRFIDLog()">
                          <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="table-responsive scrollable-table">
                <table class="table table-hover table-striped modern-table mb-0" id="tableRFIDLog">
                  <thead class="thead-sticky">
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

          <!-- Log DHT Sensor -->
          <div class="card card-success card-outline shadow-sm hover-shadow fadeIn mt-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-thermometer-half"></i> Log Suhu & Kelembapan
              </h3>
              <div class="card-tools">
                <span class="badge badge-info mr-2" id="dhtCount">0 records</span>
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                  <i class="fas fa-minus"></i>
                </button>
              </div>
            </div>
            <div class="card-body">
              <!-- Filter Section -->
              <div class="card card-secondary card-outline mb-3">
                <div class="card-body">
                  <div class="row align-items-end">
                    <div class="col-md-3">
                      <div class="form-group mb-0">
                        <label class="d-block"><i class="fas fa-clock"></i> Rentang Waktu:</label>
                        <select class="form-control p-1" id="filterDHTTime">
                          <option value="30">🕐 30 Menit Terakhir</option>
                          <option value="60" selected>🕑 1 Jam Terakhir</option>
                          <option value="180">🕒 3 Jam Terakhir</option>
                          <option value="360">🕕 6 Jam Terakhir</option>
                          <option value="1440">📅 24 Jam Terakhir</option>
                          <option value="10080">📆 7 Hari Terakhir</option>
                          <option value="all">📊 Semua Data</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-2">
                      <div class="form-group mb-0">
                        <label class="d-block"><i class="fas fa-temperature-low"></i> Suhu Min:</label>
                        <input type="number" class="form-control" id="filterTempMin" placeholder="0">
                      </div>
                    </div>
                    <div class="col-md-2">
                      <div class="form-group mb-0">
                        <label class="d-block"><i class="fas fa-temperature-high"></i> Suhu Max:</label>
                        <input type="number" class="form-control" id="filterTempMax" placeholder="50">
                      </div>
                    </div>
                    <div class="col-md-2">
                      <div class="form-group mb-0">
                        <label class="d-block"><i class="fas fa-tint"></i> Lembap Min:</label>
                        <input type="number" class="form-control" id="filterHumMin" placeholder="0">
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group mb-0">
                        <button class="btn btn-primary btn-block shadow-sm" onclick="loadDHTLog()">
                          <i class="fas fa-sync-alt"></i> Terapkan Filter
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="table-responsive scrollable-table">
                <table class="table table-hover table-striped modern-table mb-0" id="tableDHTLog">
                  <thead class="thead-sticky">
                    <tr>
                      <th width="50" class="text-center">#</th>
                      <th><i class="far fa-clock"></i> Waktu</th>
                      <th width="180"><i class="fas fa-thermometer-half"></i> Suhu (°C)</th>
                      <th width="180"><i class="fas fa-tint"></i> Kelembapan (%)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="4" class="text-center text-muted">
                        <i class="fas fa-spinner fa-spin"></i> Memuat data...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="card-footer bg-light">
                <small class="text-muted">
                  <i class="fas fa-info-circle"></i>
                  Menampilkan: <span id="dhtInfo" class="font-weight-bold">-</span>
                </small>
              </div>
            </div>
          </div>

          <!-- Log Door Status -->
          <div class="card card-warning card-outline shadow-sm hover-shadow fadeIn mt-4 mb-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-door-open"></i> Log Status Pintu
              </h3>
              <div class="card-tools">
                <span class="badge badge-info mr-2" id="doorCount">0 records</span>
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                  <i class="fas fa-minus"></i>
                </button>
              </div>
            </div>
            <div class="card-body">
              <!-- Filter Section -->
              <div class="card card-secondary card-outline mb-3">
                <div class="card-body">
                  <div class="row align-items-end">
                    <div class="col-md-8">
                      <div class="form-group mb-0">
                        <label class="d-block"><i class="fas fa-filter"></i> Filter Status Pintu:</label>
                        <select class="form-control p-1" id="filterDoorStatus">
                          <option value="">Semua Status</option>
                          <option value="terbuka">🔓 Terbuka</option>
                          <option value="tertutup">🔒 Tertutup</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group mb-0">
                        <button class="btn btn-primary btn-block shadow-sm" onclick="loadDoorLog()">
                          <i class="fas fa-sync-alt"></i> Terapkan Filter
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="table-responsive scrollable-table">
                <table class="table table-hover table-striped modern-table mb-0" id="tableDoorLog">
                  <thead class="thead-sticky">
                    <tr>
                      <th width="50" class="text-center">#</th>
                      <th><i class="far fa-clock"></i> Waktu</th>
                      <th width="200" class="text-center"><i class="fas fa-door-open"></i> Status Pintu</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="3" class="text-center text-muted">
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
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <script>
    let rfidData = [];
    let dhtData = [];
    let doorData = [];

    function updateStatistics() {
      // RFID Stats
      $('#totalRfidLogs').text(rfidData.length);

      // DHT Stats
      if (dhtData.length > 0) {
        // ✅ Filter out NaN/invalid values
        const validData = dhtData.filter(d => {
          const temp = parseFloat(d.temperature);
          const hum = parseFloat(d.humidity);
          return !isNaN(temp) && !isNaN(hum) && temp > 0 && hum > 0;
        });

        if (validData.length > 0) {
          const avgTemp = validData.reduce((sum, d) => sum + parseFloat(d.temperature), 0) / validData.length;
          const avgHum = validData.reduce((sum, d) => sum + parseFloat(d.humidity), 0) / validData.length;
          $('#avgTemp').text(avgTemp.toFixed(1) + '°C');
          $('#avgHum').text(avgHum.toFixed(1) + '%');
        } else {
          $('#avgTemp').text('-');
          $('#avgHum').text('-');
        }
      } else {
        $('#avgTemp').text('-');
        $('#avgHum').text('-');
      }

      // Door Stats
      $('#doorChanges').text(doorData.length);
    }

    function loadRFIDLog() {
      $.get('api/rfid_crud.php?action=getlogs', function(res) {
        let rows = '';
        if (res.success && res.data && res.data.length > 0) {
          rfidData = res.data;

          // Apply filters
          let filteredData = rfidData;
          const statusFilter = $('#filterRfidStatus').val();
          const searchText = $('#searchRfid').val().toLowerCase();

          if (statusFilter) {
            filteredData = filteredData.filter(d => d.status === statusFilter);
          }

          if (searchText) {
            filteredData = filteredData.filter(d =>
              d.uid.toLowerCase().includes(searchText) ||
              (d.name && d.name.toLowerCase().includes(searchText))
            );
          }

          filteredData.forEach((l, index) => {
            const name = l.name || '<em class="text-muted">Tidak terdaftar</em>';
            const statusClass = l.status === 'granted' ? 'success' : 'danger';
            const statusIcon = l.status === 'granted' ? 'check-circle' : 'times-circle';
            const statusText = l.status === 'granted' ? 'Akses Diterima' : 'Akses Ditolak';

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
          $('#rfidCount').text(filteredData.length + ' / ' + rfidData.length + ' records');
        } else {
          rfidData = [];
          rows = '<tr><td colspan="5" class="text-center text-muted"><i class="fas fa-info-circle"></i> Belum ada log akses RFID</td></tr>';
          $('#rfidCount').text('0 records');
        }
        $('#tableRFIDLog tbody').html(rows);
        updateStatistics();
      }, 'json').fail(function() {
        $('#tableRFIDLog tbody').html('<tr><td colspan="5" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data</td></tr>');
      });
    }

    function loadDHTLog() {
      // Get filter values
      const timeFilter = $('#filterDHTTime').val();
      const tempMin = $('#filterTempMin').val();
      const tempMax = $('#filterTempMax').val();
      const humMin = $('#filterHumMin').val();
      const humMax = $('#filterHumMax').val();

      // Build query parameters
      const params = new URLSearchParams({
        time: timeFilter,
        temp_min: tempMin || '0',
        temp_max: tempMax || '100',
        hum_min: humMin || '0',
        hum_max: humMax || '100'
      });

      $.get('api/dht_log.php?' + params.toString(), function(res) {
        let rows = '';
        if (res.success && res.data && res.data.length > 0) {
          dhtData = res.data;

          dhtData.forEach((l, index) => {
            const temp = parseFloat(l.temperature);
            const hum = parseFloat(l.humidity);

            // ✅ Skip jika NaN atau invalid
            if (isNaN(temp) || isNaN(hum) || temp <= 0 || hum <= 0) {
              return; // Skip row ini
            }

            const tempClass = temp > 30 ? 'text-danger' : (temp < 20 ? 'text-primary' : 'text-success');
            const humClass = hum > 70 ? 'text-info' : 'text-muted';

            rows += `
              <tr class="fadeIn">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td><i class="far fa-clock text-muted"></i> ${l.log_time}</td>
                <td class="${tempClass}">
                  <i class="fas fa-thermometer-half"></i> <strong>${temp.toFixed(1)}°C</strong>
                </td>
                <td class="${humClass}">
                  <i class="fas fa-tint"></i> <strong>${hum.toFixed(1)}%</strong>
                </td>
              </tr>
            `;
          });

          // Update count and info
          $('#dhtCount').text(res.count + ' records');
          $('#dhtInfo').text(res.info);
        } else {
          dhtData = [];
          rows = '<tr><td colspan="4" class="text-center text-muted"><i class="fas fa-info-circle"></i> Belum ada log suhu & kelembapan</td></tr>';
          $('#dhtCount').text('0 records');
          $('#dhtInfo').text('Tidak ada data');
        }
        $('#tableDHTLog tbody').html(rows);
        updateStatistics();
      }, 'json').fail(function() {
        $('#tableDHTLog tbody').html('<tr><td colspan="4" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data</td></tr>');
      });
    }

    function loadDoorLog() {
      $.get('api/door_log.php', function(res) {
        let rows = '';
        if (res.success && res.data && res.data.length > 0) {
          doorData = res.data;

          // Apply filter
          let filteredData = doorData;
          const statusFilter = $('#filterDoorStatus').val();

          if (statusFilter) {
            filteredData = filteredData.filter(d => d.status === statusFilter);
          }

          filteredData.forEach((l, index) => {
            const statusClass = l.status === 'terbuka' ? 'status-terbuka' : 'status-tertutup';
            const statusIcon = l.status === 'terbuka' ? 'door-open' : 'door-closed';
            const statusText = l.status.toUpperCase();

            const badge = l.status === 'terbuka' ? 'success' : 'secondary';
            rows += `
              <tr class="fadeIn">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td><i class="far fa-clock text-muted"></i> ${l.updated_at}</td>
                <td class="text-center">
                  <span class="badge badge-${badge}">
                    <i class="fas fa-${statusIcon}"></i> ${statusText}
                  </span>
                </td>
              </tr>
            `;
          });
          $('#doorCount').text(filteredData.length + ' / ' + doorData.length + ' records');
        } else {
          doorData = [];
          rows = '<tr><td colspan="3" class="text-center text-muted"><i class="fas fa-info-circle"></i> Belum ada log status pintu</td></tr>';
          $('#doorCount').text('0 records');
        }
        $('#tableDoorLog tbody').html(rows);
        updateStatistics();
      }, 'json').fail(function() {
        $('#tableDoorLog tbody').html('<tr><td colspan="3" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data</td></tr>');
      });
    }

    function refreshAllLogs() {
      loadRFIDLog();
      loadDHTLog();
      loadDoorLog();
    }

    $(function() {
      // Load all logs on page load
      refreshAllLogs();

      // Auto-refresh every 30 seconds
      setInterval(refreshAllLogs, 30000);

      // Filter events
      $('#searchRfid').on('keyup', function() {
        loadRFIDLog();
      });

      // DHT Time filter change event
      $('#filterDHTTime').on('change', function() {
        loadDHTLog();
      });
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