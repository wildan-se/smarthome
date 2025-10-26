<?php
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
