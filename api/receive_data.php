<?php
// Endpoint untuk menerima log data dari frontend (AJAX)
require_once '../config/config.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['type'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid data']);
  exit;
}
$type = $data['type'];
$payload = $data['data'];
if ($type === 'rfid') {
  // Log RFID
  $uid = isset($payload['uid']) ? $payload['uid'] : null;
  $status = isset($payload['status']) ? $payload['status'] : null;
  if ($uid && $status) {
    $stmt = $conn->prepare('INSERT INTO rfid_logs (uid, status) VALUES (?, ?)');
    $stmt->bind_param('ss', $uid, $status);
    $stmt->execute();
    $stmt->close();
  }
} elseif ($type === 'dht') {
  // Log DHT
  $temperature = isset($payload['temperature']) ? $payload['temperature'] : null;
  $humidity = isset($payload['humidity']) ? $payload['humidity'] : null;
  if ($temperature !== null || $humidity !== null) {
    $stmt = $conn->prepare('INSERT INTO dht_logs (temperature, humidity) VALUES (?, ?)');
    $stmt->bind_param('dd', $temperature, $humidity);
    $stmt->execute();
    $stmt->close();
  }
}
echo json_encode(['success' => true]);
