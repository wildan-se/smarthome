<?php
date_default_timezone_set('Asia/Jakarta');
// Konfigurasi koneksi database
$host = 'localhost';
$db   = 'smarthome';
$user = 'root';
$pass = '';
$port = 3307; // Laragon MySQL port

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
  die('Koneksi database gagal: ' . $conn->connect_error);
}
$conn->query("SET time_zone = '+07:00'");
if ($conn->error) {
  // Jika server tidak mengizinkan set timezone (jarang terjadi), 
  // kita biarkan error log tapi jangan matikan program
  error_log("Gagal set timezone MySQL: " . $conn->error);
}
