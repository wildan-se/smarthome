<?php

/**
 * RFID Management Page
 * Manage RFID cards and view access logs
 */

session_start();

// Include core files
require_once 'core/Auth.php';
require_once 'config/config.php';
require_once 'config/config_helper.php';

// Check authentication
Auth::check();

// Load MQTT config
$mqttCredentials = getMqttCredentials();
$mqttBroker = getConfig('mqtt_broker');
$deviceSerial = getDeviceSerial();
$mqttProtocol = getConfig('mqtt_protocol', 'wss');

// Prepare MQTT config for JavaScript
$mqttConfig = [
  'broker' => $mqttBroker,
  'username' => $mqttCredentials['username'],
  'password' => $mqttCredentials['password'],
  'serial' => $deviceSerial,
  'protocol' => $mqttProtocol
];

// Page configuration
$pageTitle = 'Manajemen RFID';
$activePage = 'rfid';
$pageCSS = [];
$pageJS = ['assets/js/pages/rfid.js'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <?php
  require_once 'components/layout/head.php';
  renderHead($pageTitle, $pageCSS);
  ?>
  <style>
    /* Enhanced Alert Styling */
    .alert {
      border-radius: 8px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
      animation: slideInDown 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .code-badge {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 4px 10px;
      border-radius: 6px;
      font-weight: bold;
      letter-spacing: 1px;
      font-family: 'Courier New', monospace;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }

    .badge.pulse {
      animation: pulse 2s infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.05);
      }
    }
  </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
  <div class="wrapper">

    <?php
    require_once 'components/layout/header.php';
    renderHeader();

    require_once 'components/layout/sidebar.php';
    renderSidebar($activePage);
    ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">

      <!-- Content Header -->
      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1><i class="fas fa-id-card"></i> Manajemen Kartu RFID</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Manajemen RFID</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <!-- Main content -->
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

          <!-- Result Messages -->
          <div id="addResult"></div>

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
                      <input type="text" name="uid" id="inputUID" class="form-control"
                        placeholder="Contoh: A61F1905" required>
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
                      <input type="text" name="name" id="inputName" class="form-control"
                        placeholder="Nama pengguna" required>
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
            </div>
          </div>

          <!-- Daftar Kartu Terdaftar -->
          <div class="card card-success card-outline shadow-sm hover-shadow fadeIn mt-4">
            <div class="card-header">
              <h3 class="card-title">
                <i class="fas fa-id-card"></i> Daftar Kartu Terdaftar
              </h3>
              <div class="card-tools">
                <span class="badge badge-success" id="cardCount">
                  <i class="fas fa-spinner fa-spin"></i> Loading...
                </span>
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
                  <i class="fas fa-sync"></i>
                </button>
                <span class="badge badge-warning">20 Terakhir</span>
              </div>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover table-striped modern-table" id="tableLog">
                  <thead>
                    <tr>
                      <th width="50" class="text-center">#</th>
                      <th><i class="fas fa-fingerprint"></i> UID Kartu</th>
                      <th><i class="fas fa-user"></i> Nama</th>
                      <th><i class="far fa-clock"></i> Waktu Akses</th>
                      <th width="150" class="text-center"><i class="fas fa-info-circle"></i> Status</th>
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

    <?php
    require_once 'components/layout/footer.php';
    renderFooter($pageJS, $mqttConfig);
    ?>

  </div>
</body>

</html>