<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_logs';

// ✅ AUTO-CREATE column 'source' if not exists (for old database)
$checkColumn = $conn->query("SHOW COLUMNS FROM door_status LIKE 'source'");
if ($checkColumn && $checkColumn->num_rows == 0) {
  $conn->query("ALTER TABLE door_status ADD COLUMN source VARCHAR(20) DEFAULT 'manual' AFTER status");
  error_log("Door log: Added 'source' column to door_status table");
}

// ✅ AUTO-CLEANUP: Delete old records (keep last 7 days only)
// Run cleanup every time to prevent database bloat
$cleanupResult = $conn->query("DELETE FROM door_status WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($cleanupResult) {
  $deletedRows = $conn->affected_rows;
  if ($deletedRows > 0) {
    error_log("Door log cleanup: Deleted $deletedRows old records");
  }
}

// ==================== GET LOGS ====================
if ($action === 'get_logs') {
  // ✅ Support pagination
  $page = intval($_GET['page'] ?? 1);
  $perPage = intval($_GET['per_page'] ?? 100);
  $perPage = min($perPage, 500); // Max 500 per page
  $offset = ($page - 1) * $perPage;

  // ✅ Support date filter
  $dateFrom = $_GET['date_from'] ?? null;
  $dateTo = $_GET['date_to'] ?? null;

  // Build WHERE clause
  $whereConditions = [];
  $params = [];
  $types = '';

  if ($dateFrom) {
    $whereConditions[] = "updated_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
  }

  if ($dateTo) {
    $whereConditions[] = "updated_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
  }

  $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

  // Get total count
  $countSql = "SELECT COUNT(*) as total FROM door_status $whereClause";
  if (!empty($params)) {
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
  } else {
    $totalRecords = $conn->query($countSql)->fetch_assoc()['total'];
  }

  // Get paginated data
  $sql = "SELECT id, status, source, DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i:%s') as timestamp, updated_at as raw_time
          FROM door_status 
          $whereClause
          ORDER BY updated_at DESC 
          LIMIT ? OFFSET ?";

  $stmt = $conn->prepare($sql);

  // Bind parameters
  $params[] = $perPage;
  $params[] = $offset;
  $types .= 'ii';

  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();

  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = [
      'id' => $row['id'],
      'status' => $row['status'],
      'source' => $row['source'] ?? 'unknown',
      'timestamp' => $row['timestamp']
    ];
  }

  echo json_encode([
    'success' => true,
    'data' => $data,
    'count' => count($data),
    'total' => intval($totalRecords),
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => ceil($totalRecords / $perPage)
  ]);
  $stmt->close();
}

// ==================== GET LATEST STATUS ====================
elseif ($action === 'get_latest') {
  $sql = "SELECT status, source, updated_at as timestamp 
          FROM door_status 
          ORDER BY updated_at DESC 
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
