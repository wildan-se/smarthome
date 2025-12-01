<?php

/**
 * DHT Debug Tool for InfinityFree Hosting
 * Use this to diagnose DHT log issues on production server
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once '../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$debug = [];

// 1. Check database connection
$debug['connection'] = $conn ? 'OK' : 'FAILED';

// 2. Count total DHT records
$result = $conn->query('SELECT COUNT(*) as total FROM dht_logs');
$debug['total_records'] = $result ? $result->fetch_assoc()['total'] : 'QUERY FAILED';

// 3. Get latest 3 records
$result = $conn->query('SELECT id, temperature, humidity, log_time FROM dht_logs ORDER BY log_time DESC LIMIT 3');
$debug['latest_records'] = [];
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $debug['latest_records'][] = $row;
  }
}

// 4. Check NOW() function
$result = $conn->query('SELECT NOW() as now_time');
$debug['mysql_now'] = $result ? $result->fetch_assoc()['now_time'] : 'FAILED';

// 5. Check INTERVAL query
$result = $conn->query('SELECT COUNT(*) as count FROM dht_logs WHERE log_time >= NOW() - INTERVAL 60 MINUTE');
$debug['last_hour_count'] = $result ? $result->fetch_assoc()['count'] : 'INTERVAL QUERY FAILED';

// 6. Server info
$debug['server_info'] = [
  'php_version' => PHP_VERSION,
  'mysql_version' => $conn->server_info,
  'timezone' => date_default_timezone_get(),
  'current_time' => date('Y-m-d H:i:s'),
  'server_time' => $_SERVER['REQUEST_TIME']
];

// 7. Test prepared statement
$stmt = $conn->prepare('SELECT COUNT(*) as count FROM dht_logs WHERE temperature >= ? AND temperature <= ?');
if ($stmt) {
  $min = -100;
  $max = 200;
  $stmt->bind_param('dd', $min, $max);
  $stmt->execute();
  $result = $stmt->get_result();
  $debug['prepared_stmt_test'] = $result->fetch_assoc()['count'];
  $stmt->close();
} else {
  $debug['prepared_stmt_test'] = 'FAILED: ' . $conn->error;
}

// 8. Check table structure
$result = $conn->query('DESCRIBE dht_logs');
$debug['table_structure'] = [];
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $debug['table_structure'][] = $row;
  }
}

echo json_encode($debug, JSON_PRETTY_PRINT);

$conn->close();
