<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_logs';

// ==================== GET LOGS ====================
if ($action === 'get_logs') {
  $limit = intval($_GET['limit'] ?? 20);
  $limit = min($limit, 100); // Max 100

  $sql = "SELECT id, status, source, DATE_FORMAT(timestamp, '%d/%m/%Y %H:%i:%s') as timestamp 
          FROM door_status 
          ORDER BY timestamp DESC 
          LIMIT ?";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $limit);
  $stmt->execute();
  $result = $stmt->get_result();

  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = [
      'id' => $row['id'],
      'status' => $row['status'],
      'source' => $row['source'],
      'timestamp' => $row['timestamp']
    ];
  }

  echo json_encode([
    'success' => true,
    'data' => $data,
    'count' => count($data)
  ]);
  $stmt->close();
}

// ==================== GET LATEST STATUS ====================
elseif ($action === 'get_latest') {
  $sql = "SELECT status, source, timestamp 
          FROM door_status 
          ORDER BY timestamp DESC 
          LIMIT 1";

  $result = $conn->query($sql);

  if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
      'success' => true,
      'data' => [
        'status' => $row['status'],
        'source' => $row['source'],
        'timestamp' => $row['timestamp']
      ]
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'error' => 'No status data available'
    ]);
  }
}

// ==================== LOG DOOR STATUS ====================
elseif ($action === 'log') {
  if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
  }

  $status = $_POST['status'] ?? '';
  $source = $_POST['source'] ?? 'manual';

  if (!in_array($status, ['terbuka', 'tertutup'])) {
    echo json_encode(['success' => false, 'error' => 'Status tidak valid']);
    exit;
  }

  if (!in_array($source, ['manual', 'rfid', 'auto'])) {
    echo json_encode(['success' => false, 'error' => 'Source tidak valid']);
    exit;
  }

  $stmt = $conn->prepare("INSERT INTO door_status (status, source) VALUES (?, ?)");
  $stmt->bind_param("ss", $status, $source);

  if ($stmt->execute()) {
    echo json_encode([
      'success' => true,
      'message' => 'Status pintu berhasil dicatat',
      'id' => $conn->insert_id
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'error' => 'Gagal mencatat status pintu'
    ]);
  }
  $stmt->close();
} else {
  echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();
