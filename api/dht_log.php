<?php
require_once '../config/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'filter';

// Get latest single record
if ($action === 'latest') {
  $sql = "SELECT temperature, humidity, log_time 
          FROM dht_logs 
          ORDER BY log_time DESC 
          LIMIT 1";

  $result = $conn->query($sql);

  if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode([
      'success' => true,
      'data' => $data
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'data' => null,
      'message' => 'No data available'
    ]);
  }

  $conn->close();
  exit;
}

// Get filter parameters
$timeFilter = $_GET['time'] ?? '60'; // default 1 hour
$tempMin = isset($_GET['temp_min']) && $_GET['temp_min'] !== '' ? floatval($_GET['temp_min']) : 0;
$tempMax = isset($_GET['temp_max']) && $_GET['temp_max'] !== '' ? floatval($_GET['temp_max']) : 100;
$humMin = isset($_GET['hum_min']) && $_GET['hum_min'] !== '' ? floatval($_GET['hum_min']) : 0;
$humMax = isset($_GET['hum_max']) && $_GET['hum_max'] !== '' ? floatval($_GET['hum_max']) : 100;

// Build WHERE clause for time filter
$timeCondition = "";
if ($timeFilter !== 'all') {
  $minutes = intval($timeFilter);
  if ($minutes > 0 && $minutes <= 43200) { // max 30 days
    $timeCondition = "AND log_time >= NOW() - INTERVAL $minutes MINUTE";
  }
}

// Build SQL query with filters
$sql = "SELECT id, temperature, humidity, DATE_FORMAT(log_time, '%d/%m/%Y %H:%i:%s') as log_time 
        FROM dht_logs 
        WHERE temperature >= ? 
          AND temperature <= ? 
          AND humidity >= ? 
          AND humidity <= ?
          $timeCondition
        ORDER BY log_time DESC 
        LIMIT 1000";

// Prepare statement
$stmt = $conn->prepare($sql);
$stmt->bind_param("dddd", $tempMin, $tempMax, $humMin, $humMax);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }
}

// Calculate statistics
$count = count($data);
$timeLabel = '';
switch ($timeFilter) {
  case '30':
    $timeLabel = '30 menit terakhir';
    break;
  case '60':
    $timeLabel = '1 jam terakhir';
    break;
  case '180':
    $timeLabel = '3 jam terakhir';
    break;
  case '360':
    $timeLabel = '6 jam terakhir';
    break;
  case '1440':
    $timeLabel = '24 jam terakhir';
    break;
  case '10080':
    $timeLabel = '7 hari terakhir';
    break;
  case 'all':
    $timeLabel = 'semua waktu';
    break;
  default:
    $timeLabel = "$timeFilter menit terakhir";
}

echo json_encode([
  'success' => true,
  'data' => $data,
  'count' => $count,
  'info' => "$count records dari $timeLabel"
]);

$stmt->close();
$conn->close();
