<?php
// Konfigurasi koneksi database
$host = 'localhost';
$db   = 'smarthome';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die('Koneksi database gagal: ' . $conn->connect_error);
}
