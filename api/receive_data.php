<?php
// Endpoint untuk menerima log data dari frontend (AJAX)

// ✅ InfinityFree compatibility
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

require_once '../config/config.php';

// Clean buffer
while (ob_get_level() > 1) {
  ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// ✅ InfinityFree compatible: Try php://input first, fallback to $_POST
$rawInput = @file_get_contents('php://input');
$data = null;

if ($rawInput !== false && !empty($rawInput)) {
  // Coba decode JSON dari body request
  $jsonData = json_decode($rawInput, true);
  if ($jsonData && is_array($jsonData)) {
    $data = $jsonData;
  }
}

// Jika JSON decode gagal atau empty, gunakan $_POST
if (!$data && !empty($_POST)) {
  $data = $_POST;
}

if (!$data || !isset($data['type'])) {
  http_response_code(400);
  ob_end_clean();
  echo json_encode([
    'error' => 'Invalid data',
    'debug' => [
      'raw_input' => $rawInput ? substr($rawInput, 0, 200) : 'blocked',
      'raw_input_length' => $rawInput ? strlen($rawInput) : 0,
      'json_decode_result' => isset($jsonData) ? 'success' : 'failed',
      'post' => !empty($_POST) ? 'available' : 'empty',
      'post_keys' => !empty($_POST) ? array_keys($_POST) : [],
      'method' => $_SERVER['REQUEST_METHOD'],
      'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
    ]
  ]);
  exit;
}
$type = $data['type'];

// ✅ Parse payload - support both JSON string and array
$payload = [];
if (isset($data['data'])) {
  if (is_string($data['data'])) {
    // Data is JSON string - decode it
    $payload = json_decode($data['data'], true);
    if (!$payload) $payload = [];
  } elseif (is_array($data['data'])) {
    // Data is already array
    $payload = $data['data'];
  }
}

if ($type === 'rfid') {
  // Log RFID
  $uid = isset($payload['uid']) ? $payload['uid'] : null;
  $status = isset($payload['status']) ? $payload['status'] : null;

  // ✅ Debug logging
  error_log("RFID received: uid=$uid, status=$status");

  // ❌ SKIP jika dari kontrol manual - tidak boleh masuk ke rfid_logs
  if ($uid === 'MANUAL_CONTROL') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Manual control tidak dicatat di RFID logs']);
    exit;
  }

  if ($uid && $status) {
    // ✅ Cek apakah kartu terdaftar
    $check = $conn->prepare('SELECT name FROM rfid_cards WHERE uid = ?');
    $check->bind_param('s', $uid);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
      // Kartu TERDAFTAR
      $card = $result->fetch_assoc();
      $check->close();

      // Insert log
      $stmt = $conn->prepare('INSERT INTO rfid_logs (uid, status) VALUES (?, ?)');
      $stmt->bind_param('ss', $uid, $status);

      if ($stmt->execute()) {
        $insertId = $stmt->insert_id;
        error_log("RFID log inserted: id=$insertId, uid=$uid, status=$status");
        $stmt->close();
        ob_end_clean();
        echo json_encode([
          'success' => true,
          'message' => 'RFID access logged',
          'data' => [
            'uid' => $uid,
            'name' => $card['name'],
            'status' => $status
          ]
        ]);
      } else {
        error_log("RFID log insert FAILED: " . $stmt->error);
        $stmt->close();
        ob_end_clean();
        echo json_encode([
          'success' => false,
          'error' => 'Failed to insert log: ' . $stmt->error
        ]);
      }
    } else {
      // Kartu TIDAK TERDAFTAR - skip logging
      $check->close();
      error_log("RFID log skipped: Card not registered (uid=$uid)");
      ob_end_clean();
      echo json_encode([
        'success' => false,
        'skipped' => true,
        'message' => 'Card not registered',
        'data' => [
          'uid' => $uid,
          'status' => $status
        ]
      ]);
    }
  } else {
    ob_end_clean();
    echo json_encode([
      'success' => false,
      'error' => 'UID and status required'
    ]);
  }
  exit;
} elseif ($type === 'dht') {
  // Log DHT
  $temperature = isset($payload['temperature']) ? floatval($payload['temperature']) : null;
  $humidity = isset($payload['humidity']) ? floatval($payload['humidity']) : null;

  // ✅ FIX: Validasi lebih realistis - allow 0°C, allow up to 80°C for sensors
  // Valid range: -50°C to 80°C for temperature, 0% to 100% for humidity
  if (
    $temperature !== null && $humidity !== null &&
    !is_nan($temperature) && !is_nan($humidity) &&
    $temperature >= -50 && $temperature <= 80 &&
    $humidity >= 0 && $humidity <= 100
  ) {
    // ✅ InfinityFree fix: Explicitly set log_time instead of relying on DEFAULT
    $stmt = $conn->prepare('INSERT INTO dht_logs (temperature, humidity, log_time) VALUES (?, ?, NOW())');

    if (!$stmt) {
      ob_end_clean();
      echo json_encode([
        'success' => false,
        'error' => 'Prepare failed: ' . $conn->error,
        'received' => [
          'temp' => $temperature,
          'hum' => $humidity
        ]
      ]);
      exit;
    }

    $stmt->bind_param('dd', $temperature, $humidity);

    if ($stmt->execute()) {
      $insertId = $stmt->insert_id;
      $stmt->close();
      ob_end_clean();
      echo json_encode([
        'success' => true,
        'message' => 'DHT data saved',
        'id' => $insertId,
        'data' => [
          'temperature' => $temperature,
          'humidity' => $humidity
        ]
      ]);
    } else {
      $error = $stmt->error;
      $stmt->close();
      ob_end_clean();
      echo json_encode([
        'success' => false,
        'error' => 'Execute failed: ' . $error,
        'received' => [
          'temp' => $temperature,
          'hum' => $humidity
        ]
      ]);
    }
  } else {
    ob_end_clean();
    echo json_encode([
      'success' => false,
      'error' => 'Invalid DHT values - out of range',
      'received' => [
        'temp' => $temperature,
        'hum' => $humidity
      ],
      'valid_range' => [
        'temp' => '-50 to 80°C',
        'hum' => '0 to 100%'
      ]
    ]);
  }
  exit;
} elseif ($type === 'door') {
  // ✅ InfinityFree FIX: Door data dari form-encoded langsung di $_POST
  $status = isset($_POST['status']) ? $_POST['status'] : (isset($payload['status']) ? $payload['status'] : null);
  $source = isset($_POST['source']) ? $_POST['source'] : (isset($payload['source']) ? $payload['source'] : 'unknown');

  // ✅ Debug logging
  error_log("Door status received: status=$status, source=$source (method: " . $_SERVER['REQUEST_METHOD'] . ")");

  // ✅ Validasi: Status harus 'terbuka' atau 'tertutup'
  if ($status && in_array($status, ['terbuka', 'tertutup'])) {
    // Cek status terakhir untuk menghindari duplikasi
    $check = $conn->query("SELECT status FROM door_status ORDER BY updated_at DESC LIMIT 1");
    $last_status = $check->num_rows > 0 ? $check->fetch_assoc()['status'] : null;

    // Hanya simpan jika status berubah
    if ($last_status !== $status) {
      // ✅ Cek apakah kolom source ada
      $columnCheck = $conn->query("SHOW COLUMNS FROM door_status LIKE 'source'");

      if ($columnCheck && $columnCheck->num_rows > 0) {
        // Kolom source ADA
        $stmt = $conn->prepare('INSERT INTO door_status (status, source) VALUES (?, ?)');
        $stmt->bind_param('ss', $status, $source);
      } else {
        // Kolom source TIDAK ADA (fallback)
        $stmt = $conn->prepare('INSERT INTO door_status (status) VALUES (?)');
        $stmt->bind_param('s', $status);
      }

      if ($stmt->execute()) {
        error_log("Door status inserted: id=" . $stmt->insert_id);
        $stmt->close();
        ob_end_clean();
        echo json_encode([
          'success' => true,
          'message' => 'Door status logged',
          'status' => $status,
          'source' => $source,
          'id' => $conn->insert_id
        ]);
      } else {
        error_log("Door status insert failed: " . $stmt->error);
        $stmt->close();
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Insert failed: ' . $stmt->error]);
      }
    } else {
      ob_end_clean();
      echo json_encode(['success' => true, 'message' => 'Status unchanged', 'status' => $status]);
    }
  } else {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid door status', 'status' => $status]);
  }
  exit;
}

// ✅ Clean exit with buffer flush
ob_end_clean();
echo json_encode(['success' => true]);
exit;
