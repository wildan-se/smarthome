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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="assets/css/custom.css">
  <style>
    /* Enhanced Alert Styling for Delete Card */
    .alert {
      border-radius: 8px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
      padding: 18px 22px;
      margin-bottom: 20px;
      position: relative;
      animation: slideInDown 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      transition: all 0.3s ease;
    }

    .alert:hover {
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
      transform: translateY(-2px);
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

    .alert .close {
      font-size: 1.8rem;
      font-weight: 300;
      opacity: 0.4;
      transition: all 0.2s ease;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .alert .close:hover {
      opacity: 1;
      transform: scale(1.1);
    }

    .alert code {
      font-family: 'Courier New', monospace;
      font-weight: 700;
      letter-spacing: 1px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .alert .fa-2x {
      font-size: 2.2rem;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
      animation: iconPulse 2s ease-in-out infinite;
    }

    @keyframes iconPulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.05);
      }
    }

    .alert small {
      font-size: 0.85rem;
      display: block;
      margin-top: 6px;
      line-height: 1.4;
    }

    .alert strong {
      display: block;
      margin-bottom: 4px;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    /* Success Alert Enhancement */
    .alert-success {
      background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
      border-color: #28a745;
    }

    /* Warning Alert Enhancement */
    .alert-warning {
      background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
      border-color: #ffc107;
    }

    /* Danger Alert Enhancement */
    .alert-danger {
      background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
      border-color: #dc3545;
    }

    /* Info Alert Enhancement */
    .alert-info {
      background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
      border-color: #17a2b8;
    }

    /* Spinner Animation */
    .fa-spin {
      animation: fa-spin 1s infinite linear;
    }

    @keyframes fa-spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    /* Responsive adjustments */
    @media (max-width: 576px) {
      .alert {
        padding: 14px 16px;
      }

      .alert .fa-2x {
        font-size: 1.6rem;
      }

      .alert code {
        font-size: 0.9rem !important;
        padding: 1px 6px !important;
      }

      .alert strong {
        font-size: 1rem !important;
      }

      .alert .d-flex {
        flex-direction: column;
        text-align: center;
      }

      .alert .me-3 {
        margin-right: 0 !important;
        margin-bottom: 10px;
      }
    }

    /* Modal Delete Confirmation Styling */
    #deleteCardModal .modal-content,
    #deleteDbOnlyModal .modal-content {
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
      animation: modalPopIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    @keyframes modalPopIn {
      0% {
        opacity: 0;
        transform: scale(0.7) translateY(-20px);
      }

      50% {
        transform: scale(1.05) translateY(0);
      }

      100% {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }

    #deleteCardModal.fade .modal-dialog,
    #deleteDbOnlyModal.fade .modal-dialog {
      transition: transform 0.3s ease-out;
    }

    #deleteCardModal .modal-header,
    #deleteDbOnlyModal .modal-header {
      position: relative;
      overflow: hidden;
    }

    #deleteCardModal .modal-header::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
      transform: rotate(45deg);
      animation: shimmer 3s infinite;
    }

    #deleteDbOnlyModal .modal-header::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.15), transparent);
      transform: rotate(45deg);
      animation: shimmer 3s infinite;
    }

    @keyframes shimmer {
      0% {
        transform: translateX(-100%) translateY(-100%) rotate(45deg);
      }

      100% {
        transform: translateX(100%) translateY(100%) rotate(45deg);
      }
    }

    #deleteCardModal .modal-body,
    #deleteDbOnlyModal .modal-body {
      animation: fadeInUp 0.5s ease-out 0.1s both;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    #deleteCardModal .btn,
    #deleteDbOnlyModal .btn {
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    #deleteCardModal .btn::before,
    #deleteDbOnlyModal .btn::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }

    #deleteCardModal .btn:hover::before,
    #deleteDbOnlyModal .btn:hover::before {
      width: 300px;
      height: 300px;
    }

    #deleteCardModal .btn:hover,
    #deleteDbOnlyModal .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    }

    #deleteCardModal .btn-danger:hover {
      background-color: #c82333;
      border-color: #bd2130;
      box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
    }

    #deleteDbOnlyModal .btn-warning:hover {
      background-color: #e0a800;
      border-color: #d39e00;
      box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4);
    }

    #deleteCardModal .bg-light,
    #deleteDbOnlyModal .bg-light {
      transition: all 0.3s ease;
    }

    #deleteCardModal .bg-light:hover,
    #deleteDbOnlyModal .bg-light:hover {
      background-color: #f8f9fa !important;
      transform: scale(1.02);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    #deleteCardModal .fa-id-card,
    #deleteDbOnlyModal .fa-database {
      animation: iconBounce 2s ease-in-out infinite;
    }

    @keyframes iconBounce {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.1);
      }
    }

    /* Modal backdrop animation */
    .modal.fade .modal-dialog {
      transform: scale(0.7);
      opacity: 0;
    }

    .modal.show .modal-dialog {
      transform: scale(1);
      opacity: 1;
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
          <!-- Result / status messages will be injected here -->
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

    <!-- Modal Konfirmasi Hapus Kartu -->
    <div class="modal fade" id="deleteCardModal" tabindex="-1" role="dialog" aria-labelledby="deleteCardModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; padding: 20px; position: relative;">
            <h5 class="modal-title" id="deleteCardModalLabel" style="font-weight: 600; z-index: 1;">
              <i class="fas fa-trash-alt me-2"></i> Hapus Kartu RFID
            </h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.9; z-index: 1;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" style="padding: 30px 25px;">
            <div class="text-center mb-3">
              <div style="width: 70px; height: 70px; margin: 0 auto 15px; background: linear-gradient(135deg, #ffe5e5 0%, #ffcccc 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #dc3545;"></i>
              </div>
            </div>
            <p class="text-center mb-3" style="font-size: 1.05rem; font-weight: 500;">Apakah Anda yakin ingin menghapus kartu ini?</p>
            <div class="bg-light p-3 rounded mb-3" style="border: 2px dashed #dc3545; background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%) !important;">
              <div class="d-flex align-items-center">
                <i class="fas fa-id-card fa-2x text-danger me-3"></i>
                <div>
                  <small class="text-muted d-block mb-1">UID Kartu:</small>
                  <code id="deleteCardUID" style="font-size: 1.15rem; color: #dc3545; font-weight: 700; background: rgba(220,53,69,0.1); padding: 4px 12px; border-radius: 6px; letter-spacing: 1px;">-</code>
                </div>
              </div>
            </div>
            <div class="alert alert-danger mb-0" style="background: rgba(220,53,69,0.1); border: 1px solid rgba(220,53,69,0.3); border-radius: 8px;">
              <small>
                <i class="fas fa-info-circle me-1"></i>
                <strong>Perhatian:</strong> Kartu akan dihapus dari ESP32 dan database secara permanen.
              </small>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 20px; background: #f8f9fa;">
            <button type="button" class="btn btn-secondary btn-lg" data-dismiss="modal" style="border-radius: 8px; padding: 10px 24px; font-weight: 500;">
              <i class="fas fa-times me-2"></i> Batal
            </button>
            <button type="button" class="btn btn-danger btn-lg" id="confirmDeleteBtn" style="border-radius: 8px; padding: 10px 24px; font-weight: 500;">
              <i class="fas fa-trash-alt me-2"></i> Ya, Hapus
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Konfirmasi Hapus dari Database Saja (ESP32 Offline) -->
    <div class="modal fade" id="deleteDbOnlyModal" tabindex="-1" role="dialog" aria-labelledby="deleteDbOnlyModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden;">
          <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); color: #212529; border: none; padding: 20px; position: relative;">
            <h5 class="modal-title" id="deleteDbOnlyModalLabel" style="font-weight: 600; z-index: 1;">
              <i class="fas fa-exclamation-triangle me-2"></i> ESP32 Tidak Merespons
            </h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #212529; opacity: 0.9; z-index: 1;">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" style="padding: 30px 25px;">
            <div class="text-center mb-3">
              <div style="width: 70px; height: 70px; margin: 0 auto 15px; background: linear-gradient(135deg, #fff9e5 0%, #ffecb3 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);">
                <i class="fas fa-wifi-slash" style="font-size: 2rem; color: #ffc107;"></i>
              </div>
            </div>
            <p class="text-center mb-3" style="font-size: 1.05rem; font-weight: 500;">ESP32 tidak merespons dalam waktu yang ditentukan.</p>
            <div class="bg-light p-3 rounded mb-3" style="border: 2px dashed #ffc107; background: linear-gradient(135deg, #fffef5 0%, #fff9e5 100%) !important;">
              <div class="d-flex align-items-center">
                <i class="fas fa-database fa-2x text-warning me-3"></i>
                <div>
                  <small class="text-muted d-block mb-1">UID Kartu:</small>
                  <code id="deleteDbOnlyUID" style="font-size: 1.15rem; color: #ff9800; font-weight: 700; background: rgba(255,193,7,0.1); padding: 4px 12px; border-radius: 6px; letter-spacing: 1px;">-</code>
                </div>
              </div>
            </div>
            <div class="alert alert-warning mb-0" style="background: rgba(255,193,7,0.1); border: 1px solid rgba(255,193,7,0.3); border-radius: 8px;">
              <small>
                <i class="fas fa-question-circle me-1"></i>
                <strong>Lanjutkan?</strong> Kartu akan dihapus dari database saja (ESP32 sedang offline).
              </small>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 20px; background: #f8f9fa;">
            <button type="button" class="btn btn-secondary btn-lg" data-dismiss="modal" style="border-radius: 8px; padding: 10px 24px; font-weight: 500;">
              <i class="fas fa-times me-2"></i> Batal
            </button>
            <button type="button" class="btn btn-warning btn-lg" id="confirmDeleteDbOnlyBtn" style="border-radius: 8px; padding: 10px 24px; font-weight: 500; color: #212529;">
              <i class="fas fa-database me-2"></i> Hapus dari Database
            </button>
          </div>
        </div>
      </div>
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
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

      Swal.fire({
        title: 'Menunggu Konfirmasi',
        html: `Mengirim kartu <code><strong>${uid}</strong></code><br><strong>${name}</strong><br>ke ESP32...`,
        icon: 'info',
        confirmButtonColor: '#17a2b8',
        confirmButtonText: '<i class="fas fa-check"></i> OK',
        timer: 3000
      });

      $('#formAddRFID')[0].reset();
    });

    // Subscribe MQTT untuk log akses
    client.on('connect', function() {
      client.subscribe(`${topicRoot}/rfid/info`);
      client.subscribe(`${topicRoot}/rfid/access`);
      console.log('✅ MQTT Connected - Subscribed to RFID topics');
    });

    client.on('message', function(topic, message) {
      const messageStr = message.toString();
      console.log('📨 MQTT Message received:', {
        topic: topic,
        message: messageStr
      });

      // Update lastScannedUID dari rfid/info untuk fallback
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

          // ✅ BLACKLIST: Tandai kartu ini untuk di-skip di riwayat selama 3 detik
          localStorage.setItem('lastAddedUID', data.uid);
          localStorage.setItem('lastAddTime', Date.now().toString());
          console.log('✅ Blacklist set for:', data.uid, '- Will skip in access log for 3 seconds');

          // Hapus blacklist setelah 3 detik
          setTimeout(function() {
            const currentUID = localStorage.getItem('lastAddedUID');
            if (currentUID === data.uid) {
              localStorage.removeItem('lastAddedUID');
              localStorage.removeItem('lastAddTime');
              console.log('🧹 Blacklist cleared for:', data.uid);
            }
          }, 3000);

          // Tambah kartu ke database dengan nama
          $.post('api/rfid_crud.php?action=add', {
            uid: data.uid,
            name: name
          }, function(res) {
            if (res.success) {
              $('#addResult').html('<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Kartu <strong>' + data.uid + '</strong> (' + name + ') berhasil ditambahkan!<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
              $('#formAddRFID')[0].reset();
              loadRFID();
              // Auto-hide setelah 5 detik
              setTimeout(function() {
                $('#addResult .alert').fadeOut(function() {
                  $(this).remove();
                });
              }, 5000);
            } else {
              $('#addResult').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle"></i> ' + (res.error || 'Gagal tambah kartu') + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
              // Auto-hide setelah 8 detik untuk error
              setTimeout(function() {
                $('#addResult .alert').fadeOut(function() {
                  $(this).remove();
                });
              }, 8000);
            }
          }, 'json');
        }

        if (data.action === 'remove' && data.result === 'ok' && data.uid) {
          // Hapus kartu dari database
          $.post('api/rfid_crud.php?action=remove', {
            uid: data.uid
          }, function(res) {
            if (res.success) {
              $('#addResult').html('<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Kartu <strong>' + data.uid + '</strong> berhasil dihapus dari ESP32 dan database!<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
              loadRFID();
              setTimeout(function() {
                $('#addResult .alert').fadeOut(function() {
                  $(this).remove();
                });
              }, 5000);
            } else {
              $('#addResult').html('<div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> Kartu dihapus dari ESP32, tapi gagal hapus dari database: ' + (res.error || 'Unknown error') + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
            }
          }, 'json').fail(function() {
            $('#addResult').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle"></i> Error: Gagal menghubungi server untuk hapus dari database<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
          });
        }

        // Handle jika ESP32 tidak menemukan kartu saat hapus
        if (data.action === 'remove' && data.result === 'not_found' && data.uid) {
          $('#addResult').html('<div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> Kartu tidak ditemukan di ESP32, menghapus dari database saja...<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');

          // Tetap hapus dari database
          $.post('api/rfid_crud.php?action=remove', {
            uid: data.uid
          }, function(res) {
            if (res.success) {
              $('#addResult').html('<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Kartu berhasil dihapus dari database!<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
              loadRFID();
              setTimeout(function() {
                $('#addResult .alert').fadeOut(function() {
                  $(this).remove();
                });
              }, 5000);
            }
          }, 'json');
        }
      }

      if (topic.endsWith('/rfid/access')) {
        let data = {};
        try {
          data = JSON.parse(messageStr);
          console.log('🔔 Parsed /rfid/access:', data);
        } catch (e) {
          console.error('❌ Parse error /rfid/access:', e);
          return;
        }

        // Ambil UID dari data atau gunakan lastScannedUID sebagai fallback
        const uid = data.uid || lastScannedUID || 'unknown';

        // ❌ SKIP jika dari kontrol manual - tidak dicatat di riwayat
        if (uid === 'MANUAL_CONTROL') {
          console.log('⚠️ Skipping manual control from RFID access log');
          return;
        }

        // ❌ SKIP jika kartu baru saja ditambahkan (dalam 3 detik terakhir)
        const pendingUID = localStorage.getItem('lastAddedUID');
        const addTime = localStorage.getItem('lastAddTime');
        if (pendingUID && pendingUID === uid && addTime) {
          const timeDiff = Date.now() - parseInt(addTime);
          if (timeDiff < 3000) { // 3 detik blacklist
            console.log('⚠️ BLOCKED: Newly added card from access log:', uid, 'Time diff:', timeDiff, 'ms');
            return;
          } else {
            console.log('⏰ Time expired for blacklist:', uid, 'Time diff:', timeDiff, 'ms');
            // Clear marker setelah expired
            localStorage.removeItem('lastAddedUID');
            localStorage.removeItem('lastAddTime');
          }
        }

        // Log akses kartu (hanya untuk kartu fisik)
        if (data.status) {
          $.post('api/rfid_crud.php?action=log', {
            uid: uid,
            status: data.status
          }, function(res) {
            if (res.success) {
              // ✅ Reload log dengan delay kecil untuk sinkronisasi database
              setTimeout(function() {
                console.log('🔄 Refreshing RFID access log...');
                loadLog();
              }, 500);

              // Reset lastScannedUID setelah digunakan
              if (!data.uid) lastScannedUID = '';
            }
          }, 'json').fail(function(xhr) {
            console.error('❌ Failed to log RFID access:', xhr.responseText);
          });
        }
      }
    });

    function loadRFID() {
      $.get('api/rfid_crud.php?action=list', function(res) {
        let rows = '';
        if (res.success && res.data) {
          res.data.forEach((card, index) => {
            rows += `
              <tr class="fadeIn">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td><code class="code-badge">${card.uid}</code></td>
                <td><i class="fas fa-user text-muted"></i> ${card.name || '<em class="text-muted">Tidak ada nama</em>'}</td>
                <td><i class="far fa-calendar-alt text-muted"></i> ${card.added_at}</td>
                <td><i class="fas fa-user-shield text-muted"></i> ${card.added_by_name || 'System'}</td>
                <td class="text-center">
                  <div class="btn-group" role="group" aria-label="Aksi">
                    <button class="btn btn-sm btn-danger" onclick="removeRFID('${card.uid}')" title="Hapus di ESP32 & DB">
                      <i class="fas fa-trash-alt"></i> Hapus
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="removeFromDBOnly('${card.uid}')" title="Hapus hanya di Database">
                      <i class="fas fa-database"></i> DB
                    </button>
                  </div>
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
      // Show SweetAlert2 confirmation with options:
      // - Confirm: Hapus di ESP32 + Database
      // - Deny: Hapus hanya di Database
      // - Cancel: batalkan
      Swal.fire({
        title: 'Hapus Kartu',
        html: `Hapus kartu dengan UID: <code><strong>${uid}</strong></code>?`,
        icon: 'warning',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-microchip"></i> Hapus di ESP32 & Database',
        denyButtonText: '<i class="fas fa-database"></i> Hapus hanya di Database',
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        confirmButtonColor: '#dc3545',
        denyButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        focusCancel: true
      }).then((result) => {
        if (result.isConfirmed) {
          // Publish remove command to ESP32
          client.publish(`${topicRoot}/rfid/remove`, uid);

          // Also remove from database immediately to keep UI in sync
          $('#addResult').html('<div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-spinner fa-spin"></i> Menghapus kartu dari ESP32 dan database...<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
          $.post('api/rfid_crud.php?action=remove', {
            uid: uid
          }, function(res) {
            if (res.success) {
              $('#addResult').html('<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Kartu berhasil dihapus (ESP32 & DB).<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
              loadRFID();
              loadLog(); // Refresh log juga
              Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Kartu dihapus dari ESP32 dan database.',
                timer: 2000
              });
              // Auto-hide alert setelah 5 detik
              setTimeout(function() {
                $('#addResult .alert').fadeOut(function() {
                  $(this).remove();
                });
              }, 5000);
            } else {
              $('#addResult').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle"></i> Error: ' + (res.error || 'Gagal menghapus') + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
              Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: res.error || 'Gagal menghapus kartu dari database.'
              });
            }
          }, 'json').fail(function() {
            $('#addResult').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle"></i> Error: Gagal menghubungi server<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
            Swal.fire({
              icon: 'error',
              title: 'Gagal',
              text: 'Gagal menghubungi server untuk menghapus dari database.'
            });
          });
        } else if (result.isDenied) {
          // Remove only from DB
          Swal.fire({
            title: 'Hapus dari Database saja?',
            text: 'Kartu akan tetap tersimpan di ESP32. Lanjutkan?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus dari DB',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d'
          }).then((sub) => {
            if (sub.isConfirmed) {
              $('#addResult').html('<div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-spinner fa-spin"></i> Menghapus dari database...<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
              $.post('api/rfid_crud.php?action=remove', {
                uid: uid
              }, function(res) {
                if (res.success) {
                  $('#addResult').html('<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Kartu dihapus dari database<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
                  loadRFID();
                  loadLog(); // Refresh log juga
                  Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Kartu dihapus dari database.',
                    timer: 2000
                  });
                  // Auto-hide alert setelah 5 detik
                  setTimeout(function() {
                    $('#addResult .alert').fadeOut(function() {
                      $(this).remove();
                    });
                  }, 5000);
                } else {
                  $('#addResult').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle"></i> Error: ' + (res.error || 'Gagal menghapus') + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
                  Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: res.error || 'Gagal menghapus kartu dari database.'
                  });
                }
              }, 'json').fail(function() {
                $('#addResult').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle"></i> Error: Gagal menghubungi server<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
                Swal.fire({
                  icon: 'error',
                  title: 'Gagal',
                  text: 'Gagal menghubungi server.'
                });
              });
            }
          });
        }
        // else: Cancelled, do nothing
      });
    }

    function removeFromDBOnly(uid) {
      Swal.fire({
        title: 'Hapus dari Database saja?',
        html: `Kartu <code><strong>${uid}</strong></code> akan dihapus dari database tetapi tetap tersimpan di ESP32. Lanjutkan?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus dari DB',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ffc107'
      }).then((result) => {
        if (!result.isConfirmed) return;

        $('#addResult').html('<div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-spinner fa-spin"></i> Menghapus dari database...<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
        $.post('api/rfid_crud.php?action=remove', {
          uid: uid
        }, function(res) {
          if (res.success) {
            $('#addResult').html('<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Kartu <strong>' + uid + '</strong> berhasil dihapus dari database!<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
            loadRFID();
            loadLog();
            Swal.fire({
              icon: 'success',
              title: 'Berhasil',
              text: 'Kartu dihapus dari database.'
            });
            // Auto-hide alert setelah 5 detik
            setTimeout(function() {
              $('#addResult .alert').fadeOut(function() {
                $(this).remove();
              });
            }, 5000);
          } else {
            $('#addResult').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle"></i> Gagal menghapus: ' + (res.error || 'Unknown error') + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
            Swal.fire({
              icon: 'error',
              title: 'Gagal',
              text: res.error || 'Gagal menghapus dari database.'
            });
          }
        }, 'json').fail(function() {
          $('#addResult').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle"></i> Error: Gagal menghubungi server<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
          Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: 'Gagal menghubungi server.'
          });
        });
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
      }, 'json').fail(function(xhr, status, error) {
        console.error('❌ Failed to load RFID logs:', error);
        $('#tableLog tbody').html('<tr><td colspan="5" class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i><br>Gagal memuat riwayat akses</td></tr>');
      });
    }

    $(function() {
      loadRFID();
      loadLog();

      // ✅ Auto-refresh log setiap 10 detik (lebih sering untuk sinkronisasi real-time)
      setInterval(function() {
        console.log('⏰ Auto-refreshing RFID logs...');
        loadLog();
      }, 10000);
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