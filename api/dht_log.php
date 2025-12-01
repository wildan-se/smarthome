<?php

/**
 * DHT Sensor Log API
 * Provides temperature and humidity logs with filtering
 */

// ✅ Start session and suppress errors untuk hosting compatibility
error_reporting(0);
ini_set('display_errors', '0');
@ini_set('date.timezone', 'Asia/Jakarta'); // Set timezone explicitly

ob_start();
@session_start();
require_once '../config/config.php';

// Clean buffer
while (ob_get_level()) {
  ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// ✅ Check login (optional - remove if causing issues)
// if (!isset($_SESSION['user_id'])) {
//   echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//   exit;
// }

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
$tempMin = isset($_GET['temp_min']) && $_GET['temp_min'] !== '' ? floatval($_GET['temp_min']) : -100;
$tempMax = isset($_GET['temp_max']) && $_GET['temp_max'] !== '' ? floatval($_GET['temp_max']) : 200;
$humMin = isset($_GET['hum_min']) && $_GET['hum_min'] !== '' ? floatval($_GET['hum_min']) : -100;
$humMax = isset($_GET['hum_max']) && $_GET['hum_max'] !== '' ? floatval($_GET['hum_max']) : 200;

// ✅ Determine limit based on time filter
$limit = 1000; // default limit
if ($timeFilter === 'all') {
  $limit = 10000; // larger limit for all data
} elseif (intval($timeFilter) > 1440) {
  $limit = 5000; // medium limit for multi-day queries
}

// Build WHERE clause for time filter
$timeCondition = "";
$useTimestamp = false;
$timestampLimit = null;

if ($timeFilter !== 'all') {
  $minutes = intval($timeFilter);
  // ✅ Extended max to 30 days (43200 minutes)
  if ($minutes > 0 && $minutes <= 43200) {
    // ✅ Use both NOW() and timestamp fallback for hosting compatibility
    $timeCondition = "AND log_time >= NOW() - INTERVAL $minutes MINUTE";

    // Fallback: calculate timestamp
    $timestampLimit = date('Y-m-d H:i:s', strtotime("-$minutes minutes"));
  } elseif ($minutes > 43200) {
    // If exceeds 30 days, treat as "all"
    $timeCondition = "";
  }
}

// Build SQL query with filters
$sql = "SELECT id, temperature, humidity, DATE_FORMAT(log_time, '%d/%m/%Y %H:%i:%s') as log_time 
        FROM dht_logs 
        WHERE temperature IS NOT NULL 
          AND humidity IS NOT NULL
          AND temperature >= ? 
          AND temperature <= ? 
          AND humidity >= ? 
          AND humidity <= ?
          $timeCondition
        ORDER BY log_time DESC 
        LIMIT $limit";

// ✅ Check if connection is valid
if (!$conn) {
  echo json_encode([
    'success' => false,
    'error' => 'Database connection failed',
    'data' => []
  ]);
  exit;
}

// Prepare statement
$stmt = $conn->prepare($sql);

if (!$stmt) {
  echo json_encode([
    'success' => false,
    'error' => 'Query preparation failed: ' . $conn->error,
    'data' => []
  ]);
  $conn->close();
  exit;
}

$stmt->bind_param("dddd", $tempMin, $tempMax, $humMin, $humMax);

if (!$stmt->execute()) {
  // ✅ Fallback: Try with timestamp instead of NOW() if query fails
  if ($timestampLimit !== null) {
    $stmt->close();

    $sqlFallback = "SELECT id, temperature, humidity, DATE_FORMAT(log_time, '%d/%m/%Y %H:%i:%s') as log_time 
            FROM dht_logs 
            WHERE temperature IS NOT NULL 
              AND humidity IS NOT NULL
              AND temperature >= ? 
              AND temperature <= ? 
              AND humidity >= ? 
              AND humidity <= ?
              AND log_time >= ?
            ORDER BY log_time DESC 
            LIMIT $limit";

    $stmt = $conn->prepare($sqlFallback);
    $stmt->bind_param("dddds", $tempMin, $tempMax, $humMin, $humMax, $timestampLimit);

    if (!$stmt->execute()) {
      echo json_encode([
        'success' => false,
        'error' => 'Query execution failed (fallback): ' . $stmt->error,
        'data' => []
      ]);
      $stmt->close();
      $conn->close();
      exit;
    }
  } else {
    echo json_encode([
      'success' => false,
      'error' => 'Query execution failed: ' . $stmt->error,
      'data' => []
    ]);
    $stmt->close();
    $conn->close();
    exit;
  }
}

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
  case '720':
    $timeLabel = '12 jam terakhir';
    break;
  case '1440':
    $timeLabel = '24 jam terakhir';
    break;
  case '2880':
    $timeLabel = '2 hari terakhir';
    break;
  case '4320':
    $timeLabel = '3 hari terakhir';
    break;
  case '10080':
    $timeLabel = '7 hari terakhir';
    break;
  case '20160':
    $timeLabel = '14 hari terakhir';
    break;
  case '43200':
    $timeLabel = '30 hari terakhir';
    break;
  case 'all':
    $timeLabel = 'semua waktu';
    break;
  default:
    $minutes = intval($timeFilter);
    if ($minutes >= 1440) {
      $days = round($minutes / 1440, 1);
      $timeLabel = "$days hari terakhir";
    } elseif ($minutes >= 60) {
      $hours = round($minutes / 60, 1);
      $timeLabel = "$hours jam terakhir";
    } else {
      $timeLabel = "$minutes menit terakhir";
    }
}

echo json_encode([
  'success' => true,
  'data' => $data,
  'count' => $count,
  'info' => "$count records dari $timeLabel",
  'debug' => [
    'total_rows' => $count,
    'time_filter' => $timeFilter,
    'time_condition' => $timeCondition ?: 'none (all data)',
    'limit' => $limit,
    'temp_range' => "$tempMin - $tempMax",
    'hum_range' => "$humMin - $humMax",
    'query' => str_replace(["\n", "  "], [" ", " "], $sql)
  ]
]);

$stmt->close();
$conn->close();
exit; // ✅ Clean exit
