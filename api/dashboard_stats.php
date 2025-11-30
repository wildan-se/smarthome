<?php
require_once '../config/config.php';
header('Content-Type: application/json');

// Helper function untuk hitung total
function getCount($conn, $table)
{
  $result = $conn->query("SELECT COUNT(*) as total FROM $table");
  return $result->fetch_assoc()['total'];
}

// 1. Total Kartu
$totalCards = getCount($conn, 'rfid_cards');

// 2. Total Log Hari Ini
$today = date('Y-m-d');
$resLog = $conn->query("SELECT COUNT(*) as total FROM rfid_logs WHERE DATE(access_time) = '$today'");
$totalLogs = $resLog->fetch_assoc()['total'];

// 3. Status Pintu Terakhir
$resDoor = $conn->query("SELECT status FROM door_status ORDER BY updated_at DESC LIMIT 1");
$doorStatus = ($resDoor->num_rows > 0) ? $resDoor->fetch_assoc()['status'] : 'Unknown';

// 4. Suhu Terakhir
$resDHT = $conn->query("SELECT temperature, humidity FROM dht_logs ORDER BY log_time DESC LIMIT 1");
$dht = ($resDHT->num_rows > 0) ? $resDHT->fetch_assoc() : ['temperature' => 0, 'humidity' => 0];

echo json_encode([
  'cards' => $totalCards,
  'logs_today' => $totalLogs,
  'door' => $doorStatus,
  'temp' => $dht['temperature'],
  'hum' => $dht['humidity']
]);
