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

  // ❌ SKIP jika dari kontrol manual - tidak boleh masuk ke rfid_logs
  if ($uid === 'MANUAL_CONTROL') {
    echo json_encode(['success' => false, 'message' => 'Manual control tidak dicatat di RFID logs']);
    exit;
  }

  if ($uid && $status) {
    $stmt = $conn->prepare('INSERT INTO rfid_logs (uid, status) VALUES (?, ?)');
    $stmt->bind_param('ss', $uid, $status);
    $stmt->execute();
    $stmt->close();
  }
} elseif ($type === 'dht') {
  // Log DHT
  $temperature = isset($payload['temperature']) ? floatval($payload['temperature']) : 0;
  $humidity = isset($payload['humidity']) ? floatval($payload['humidity']) : 0;

  // ✅ Validasi: Hanya simpan jika nilai valid (> 0 dan bukan NaN)
  if ($temperature > 0 && $temperature < 100 && $humidity > 0 && $humidity <= 100 && !is_nan($temperature) && !is_nan($humidity)) {
    $stmt = $conn->prepare('INSERT INTO dht_logs (temperature, humidity) VALUES (?, ?)');
    $stmt->bind_param('dd', $temperature, $humidity);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'DHT data saved']);
  } else {
    echo json_encode(['success' => false, 'error' => 'Invalid DHT values', 'temp' => $temperature, 'hum' => $humidity]);
  }
  exit;
} elseif ($type === 'door') {
  // Log Door Status
  $status = isset($payload['status']) ? $payload['status'] : null;

  // ✅ Validasi: Status harus 'terbuka' atau 'tertutup'
  if ($status && in_array($status, ['terbuka', 'tertutup'])) {
    // Cek status terakhir untuk menghindari duplikasi
    $check = $conn->query("SELECT status FROM door_status ORDER BY updated_at DESC LIMIT 1");
    $last_status = $check->num_rows > 0 ? $check->fetch_assoc()['status'] : null;

    // Hanya simpan jika status berubah
    if ($last_status !== $status) {
      $stmt = $conn->prepare('INSERT INTO door_status (status) VALUES (?)');
      $stmt->bind_param('s', $status);
      $stmt->execute();
      $stmt->close();
      echo json_encode(['success' => true, 'message' => 'Door status logged', 'status' => $status]);
    } else {
      echo json_encode(['success' => true, 'message' => 'Status unchanged', 'status' => $status]);
    }
  } else {
    echo json_encode(['success' => false, 'error' => 'Invalid door status', 'status' => $status]);
  }
  exit;
}
echo json_encode(['success' => true]);
