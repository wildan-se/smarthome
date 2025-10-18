<?php
// ============================================
// TEMPLATE KONFIGURASI DATABASE
// ============================================
// 
// CARA PENGGUNAAN:
// 1. Copy file ini menjadi 'config.php'
// 2. Edit nilai-nilai di bawah sesuai dengan database Anda
// 3. Jangan commit file 'config.php' ke Git!
//
// Contoh command:
// cp config.example.php config.php
// ============================================

// Konfigurasi koneksi database
$host = 'localhost';           // Database host (biasanya localhost)
$db   = 'smarthome';          // Nama database Anda
$user = 'root';               // Username database (ganti dengan username Anda)
$pass = '';                   // Password database (ganti dengan password Anda)

// Buat koneksi database
$conn = new mysqli($host, $user, $pass, $db);

// Cek koneksi
if ($conn->connect_error) {
  die('Koneksi database gagal: ' . $conn->connect_error);
}

// Set charset untuk menghindari masalah encoding
$conn->set_charset("utf8mb4");

// ============================================
// CATATAN KEAMANAN
// ============================================
// 
// ⚠️ PENTING:
// - Jangan gunakan user 'root' di production
// - Gunakan password yang kuat
// - Batasi privilege database sesuai kebutuhan
// - Pastikan file config.php tidak dapat diakses dari web
//
// ============================================
