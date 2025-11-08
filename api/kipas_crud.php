<?php
session_start();
require_once '../config/config.php';
header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// $conn sudah tersedia dari config.php
if ($conn->connect_error) {
  echo json_encode(['success' => false, 'error' => 'Database connection failed']);
  exit;
}

// ==================== GET SETTINGS ====================
if ($action === 'get_settings') {
  $sql = "SELECT * FROM kipas_settings WHERE id = 1";
  $result = $conn->query($sql);

  if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
      'success' => true,
      'data' => [
        'threshold_on' => floatval($row['threshold_on']),
        'threshold_off' => floatval($row['threshold_off']),
        'mode' => $row['mode'],
        'updated_at' => $row['updated_at']
      ]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Settings not found']);
  }
}

// ==================== UPDATE SETTINGS ====================
elseif ($action === 'update_settings') {
  $threshold_on = floatval($_POST['threshold_on'] ?? 38);
  $threshold_off = floatval($_POST['threshold_off'] ?? 30);
  $mode = $_POST['mode'] ?? 'auto';
  $user_id = $_SESSION['user_id'];

  // Validasi
  if ($threshold_on <= $threshold_off) {
    echo json_encode(['success' => false, 'error' => 'Suhu ON harus lebih tinggi dari suhu OFF']);
    exit;
  }

  if ($threshold_on < 20 || $threshold_on > 60) {
    echo json_encode(['success' => false, 'error' => 'Suhu ON harus antara 20-60°C']);
    exit;
  }

  if ($threshold_off < 15 || $threshold_off > 50) {
    echo json_encode(['success' => false, 'error' => 'Suhu OFF harus antara 15-50°C']);
    exit;
  }

  if (!in_array($mode, ['auto', 'manual'])) {
    echo json_encode(['success' => false, 'error' => 'Mode tidak valid']);
    exit;
  }

  $stmt = $conn->prepare("UPDATE kipas_settings SET threshold_on = ?, threshold_off = ?, mode = ?, updated_by = ? WHERE id = 1");
  $stmt->bind_param("ddsi", $threshold_on, $threshold_off, $mode, $user_id);

  if ($stmt->execute()) {
    echo json_encode([
      'success' => true,
      'message' => 'Settings berhasil diupdate',
      'data' => [
        'threshold_on' => $threshold_on,
        'threshold_off' => $threshold_off,
        'mode' => $mode
      ]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal update settings']);
  }
  $stmt->close();
}

// ==================== UPDATE MODE ONLY ====================
elseif ($action === 'update_mode') {
  $mode = $_POST['mode'] ?? 'auto';
  $user_id = $_SESSION['user_id'];

  if (!in_array($mode, ['auto', 'manual'])) {
    echo json_encode(['success' => false, 'error' => 'Mode tidak valid']);
    exit;
  }

  $stmt = $conn->prepare("UPDATE kipas_settings SET mode = ?, updated_by = ? WHERE id = 1");
  $stmt->bind_param("si", $mode, $user_id);

  if ($stmt->execute()) {
    echo json_encode([
      'success' => true,
      'message' => 'Mode berhasil diupdate ke ' . strtoupper($mode),
      'data' => ['mode' => $mode]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal update mode']);
  }
  $stmt->close();
}

// ==================== LOG KIPAS STATUS ====================
elseif ($action === 'log_status') {
  $status = $_POST['status'] ?? '';
  $mode = $_POST['mode'] ?? 'auto';
  $temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
  $humidity = isset($_POST['humidity']) ? floatval($_POST['humidity']) : null;
  $trigger = $_POST['trigger'] ?? 'auto';

  if (!in_array($status, ['on', 'off'])) {
    echo json_encode(['success' => false, 'error' => 'Status tidak valid']);
    exit;
  }

  if (!in_array($mode, ['auto', 'manual'])) {
    echo json_encode(['success' => false, 'error' => 'Mode tidak valid']);
    exit;
  }

  $stmt = $conn->prepare("INSERT INTO kipas_logs (status, mode, temperature, humidity, `trigger`) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param("ssdds", $status, $mode, $temperature, $humidity, $trigger);

  if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Log berhasil ditambahkan', 'id' => $conn->insert_id]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal menambahkan log: ' . $conn->error]);
  }
  $stmt->close();
}

// ==================== GET LOGS ====================
elseif ($action === 'get_logs') {
  $limit = intval($_GET['limit'] ?? 50);
  $limit = min($limit, 200); // Max 200

  $sql = "SELECT id, status, mode, temperature, humidity, `trigger`, logged_at FROM kipas_logs ORDER BY logged_at DESC LIMIT ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $limit);
  $stmt->execute();
  $result = $stmt->get_result();

  $logs = [];
  while ($row = $result->fetch_assoc()) {
    $logs[] = [
      'id' => $row['id'],
      'status' => $row['status'],
      'mode' => $row['mode'],
      'temperature' => $row['temperature'] ? floatval($row['temperature']) : null,
      'humidity' => $row['humidity'] ? floatval($row['humidity']) : null,
      'trigger' => $row['trigger'],
      'logged_at' => $row['logged_at']
    ];
  }

  echo json_encode(['success' => true, 'data' => $logs, 'count' => count($logs)]);
  $stmt->close();
}

// ==================== GET LATEST STATUS ====================
elseif ($action === 'get_latest_status') {
  $sql = "SELECT status, mode, temperature, humidity, `trigger`, logged_at FROM kipas_logs ORDER BY logged_at DESC LIMIT 1";
  $result = $conn->query($sql);

  if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
      'success' => true,
      'data' => [
        'status' => $row['status'],
        'mode' => $row['mode'],
        'temperature' => $row['temperature'] ? floatval($row['temperature']) : null,
        'humidity' => $row['humidity'] ? floatval($row['humidity']) : null,
        'trigger' => $row['trigger'],
        'logged_at' => $row['logged_at']
      ]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'No status data']);
  }
}

// ==================== LOG DHT DATA ====================
elseif ($action === 'log_dht') {
  $temperature = floatval($_POST['temperature'] ?? 0);
  $humidity = floatval($_POST['humidity'] ?? 0);

  if ($temperature < -40 || $temperature > 80) {
    echo json_encode(['success' => false, 'error' => 'Suhu tidak valid']);
    exit;
  }

  if ($humidity < 0 || $humidity > 100) {
    echo json_encode(['success' => false, 'error' => 'Kelembapan tidak valid']);
    exit;
  }

  $stmt = $conn->prepare("INSERT INTO dht_logs (temperature, humidity) VALUES (?, ?)");
  $stmt->bind_param("dd", $temperature, $humidity);

  if ($stmt->execute()) {
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal log DHT']);
  }
  $stmt->close();
}

// ==================== GET LATEST DHT ====================
elseif ($action === 'get_latest_dht') {
  $sql = "SELECT * FROM dht_logs ORDER BY logged_at DESC LIMIT 1";
  $result = $conn->query($sql);

  if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
      'success' => true,
      'data' => [
        'temperature' => floatval($row['temperature']),
        'humidity' => floatval($row['humidity']),
        'logged_at' => $row['logged_at']
      ]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'No DHT data']);
  }
}

// ==================== GET DHT HISTORY ====================
elseif ($action === 'get_dht_history') {
  $limit = intval($_GET['limit'] ?? 100);
  $limit = min($limit, 500);

  $sql = "SELECT * FROM dht_logs ORDER BY logged_at DESC LIMIT ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $limit);
  $stmt->execute();
  $result = $stmt->get_result();

  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = [
      'temperature' => floatval($row['temperature']),
      'humidity' => floatval($row['humidity']),
      'logged_at' => $row['logged_at']
    ];
  }

  echo json_encode(['success' => true, 'data' => $data]);
  $stmt->close();
} else {
  echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();
