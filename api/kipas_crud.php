<?php

/**
 * Fan Control API (Merged Version)
 * - Compatible with InfinityFree
 * - Support Dashboard (session) + ESP32 (JSON)
 */

// ✅ Suppress errors - InfinityFree friendly
@error_reporting(0);
@ini_set('display_errors', '0');

// ✅ Output buffering
@ob_start();

session_start();

// ✅ Safe include config
try {
  require_once '../config/config.php';
} catch (Exception $e) {
  @ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => false, 'error' => 'Database configuration error']);
  exit;
}

// ✅ Clear buffer before output
if (ob_get_level() > 0) {
  @ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

// ===== Helper untuk respon JSON versi baru =====
function sendJson($success, $message, $data = null, $httpCode = null)
{
  if ($httpCode !== null) {
    http_response_code($httpCode);
  }
  $res = [
    'success' => $success,
    'message' => $message
  ];
  if ($data !== null) {
    $res['data'] = $data;
  }
  echo json_encode($res);
  exit;
}

// ===== Ambil action =====
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Pastikan koneksi DB
if (!isset($conn) || $conn->connect_error) {
  echo json_encode(['success' => false, 'error' => 'Database connection failed']);
  exit;
}

// ✅ Auto-create kipas_logs table jika tidak ada (InfinityFree compatibility)
$checkTable = $conn->query("SHOW TABLES LIKE 'kipas_logs'");
if ($checkTable && $checkTable->num_rows == 0) {
  $createTable = "CREATE TABLE IF NOT EXISTS kipas_logs (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(10) NOT NULL,
    mode VARCHAR(10) NOT NULL,
    temperature FLOAT DEFAULT NULL,
    humidity FLOAT DEFAULT NULL,
    trigger_source VARCHAR(50) DEFAULT NULL,
    logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logged_at (logged_at)
  ) ENGINE=MyISAM DEFAULT CHARSET=latin1";

  $conn->query($createTable);
}

/**
 * RULE AUTH:
 * - Endpoint dashboard: butuh login (lihat daftar $protectedActions)
 * - Endpoint ESP32/log data: tidak perlu login
 */
$protectedActions = [
  'get_settings',
  'update_settings',
  'update_mode_dashboard', // kalau nanti mau pisah
  'get_logs',
  'get_dht_history'
];

if (in_array($action, $protectedActions)) {
  if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
  }
}

// ==================== 1. GET SETTINGS (Dashboard) ====================
if ($action === 'get_settings') {
  $sql = "SELECT * FROM kipas_settings WHERE id = 1";
  $result = $conn->query($sql);

  if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
      'success' => true,
      'data' => [
        'threshold_on'  => floatval($row['threshold_on']),
        'threshold_off' => floatval($row['threshold_off']),
        'mode'          => $row['mode'],
        'updated_at'    => $row['updated_at']
      ]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Settings not found']);
  }
  exit;
}

// ==================== 2. UPDATE SETTINGS (Dashboard, form-data) ====================
if ($action === 'update_settings') {
  $threshold_on  = floatval($_POST['threshold_on'] ?? 38);
  $threshold_off = floatval($_POST['threshold_off'] ?? 30);
  $mode          = $_POST['mode'] ?? 'auto';
  $user_id       = $_SESSION['user_id'];

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

  $stmt = $conn->prepare("UPDATE kipas_settings SET threshold_on = ?, threshold_off = ?, mode = ?, updated_by = ?, updated_at = NOW() WHERE id = 1");
  $stmt->bind_param("ddsi", $threshold_on, $threshold_off, $mode, $user_id);

  if ($stmt->execute()) {
    echo json_encode([
      'success' => true,
      'message' => 'Settings berhasil diupdate',
      'data' => [
        'threshold_on'  => $threshold_on,
        'threshold_off' => $threshold_off,
        'mode'          => $mode
      ]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal update settings']);
  }
  $stmt->close();
  exit;
}

// ==================== 3. UPDATE MODE (Gabungan: JSON (ESP32) + form-data (Dashboard)) ====================
if ($action === 'update_mode') {
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
  $rawBody     = file_get_contents('php://input');
  $isJson      = stripos($contentType, 'application/json') !== false && !empty($rawBody);

  // ---- 3A. Mode via JSON (ESP32 / public) ----
  if ($isJson) {
    try {
      $input = json_decode($rawBody, true);
      if (!is_array($input)) {
        throw new Exception("Invalid JSON");
      }

      $mode = $input['mode'] ?? '';

      if (!in_array($mode, ['auto', 'manual'])) {
        throw new Exception("Invalid mode: " . $mode);
      }

      // Pastikan row id=1 ada
      $check = $conn->query("SELECT id FROM kipas_settings WHERE id = 1");
      if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE kipas_settings SET mode = ?, updated_at = NOW() WHERE id = 1");
      } else {
        $stmt = $conn->prepare("INSERT INTO kipas_settings (id, mode, threshold_on, threshold_off, updated_at) VALUES (1, ?, 38.0, 30.0, NOW())");
      }

      $stmt->bind_param("s", $mode);

      if ($stmt->execute()) {
        sendJson(true, "Mode updated to $mode");
      } else {
        throw new Exception("Failed to update mode: " . $stmt->error);
      }
    } catch (Exception $e) {
      sendJson(false, $e->getMessage(), null, 500);
    }
  }

  // ---- 3B. Mode via form-data (Dashboard, butuh login) ----
  if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
  }

  $mode    = $_POST['mode'] ?? 'auto';
  $user_id = $_SESSION['user_id'];

  if (!in_array($mode, ['auto', 'manual'])) {
    echo json_encode(['success' => false, 'error' => 'Mode tidak valid']);
    exit;
  }

  $stmt = $conn->prepare("UPDATE kipas_settings SET mode = ?, updated_by = ?, updated_at = NOW() WHERE id = 1");
  $stmt->bind_param("si", $mode, $user_id);

  if ($stmt->execute()) {
    echo json_encode([
      'success' => true,
      'message' => 'Mode berhasil diupdate ke ' . strtoupper($mode),
      'data'    => ['mode' => $mode]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal update mode']);
  }
  $stmt->close();
  $conn->close();
  exit;
}

// ==================== 4. UPDATE THRESHOLD (JSON, ala kode baru) ====================
if ($action === 'update_threshold' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
      throw new Exception("Invalid JSON");
    }

    $on  = isset($input['temp_on']) ? floatval($input['temp_on']) : null;
    $off = isset($input['temp_off']) ? floatval($input['temp_off']) : null;

    if ($on === null || $off === null) {
      throw new Exception("Threshold values required");
    }

    // Optional: validasi range bisa pakai aturan yang sama seperti dashboard
    if ($on <= $off) {
      throw new Exception("temp_on must be greater than temp_off");
    }

    // Cek row id=1
    $check = $conn->query("SELECT id FROM kipas_settings WHERE id = 1");
    if ($check && $check->num_rows > 0) {
      $stmt = $conn->prepare("UPDATE kipas_settings SET threshold_on = ?, threshold_off = ?, updated_at = NOW() WHERE id = 1");
    } else {
      $stmt = $conn->prepare("INSERT INTO kipas_settings (id, threshold_on, threshold_off, mode, updated_at) VALUES (1, ?, ?, 'manual', NOW())");
    }

    $stmt->bind_param("dd", $on, $off);

    if ($stmt->execute()) {
      sendJson(true, "Threshold updated", [
        'threshold_on'  => $on,
        'threshold_off' => $off
      ]);
    } else {
      throw new Exception("Failed to update threshold: " . $stmt->error);
    }
  } catch (Exception $e) {
    sendJson(false, $e->getMessage(), null, 500);
  }
}

// ==================== 5. LOG KIPAS STATUS (Gabungan JSON + form-data) ====================
if ($action === 'log_status') {
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
  $rawBody     = file_get_contents('php://input');
  $isJson      = stripos($contentType, 'application/json') !== false && !empty($rawBody);

  // Default nilai
  $status      = '';
  $mode        = 'auto';
  $temperature = null;
  $humidity    = null;
  $trigger     = 'auto';

  if ($isJson) {
    // ---- 5A. JSON (ESP32 / public) ----
    try {
      $input = json_decode($rawBody, true);
      if (!is_array($input)) {
        throw new Exception("Invalid JSON");
      }

      $status  = $input['status']  ?? '';
      $trigger = $input['trigger'] ?? 'manual'; // auto/manual

      if (!in_array($status, ['on', 'off'])) {
        throw new Exception("Invalid status");
      }

      // Hindari log duplikat berturut-turut
      $lastLog = $conn->query("SELECT status FROM kipas_logs ORDER BY logged_at DESC LIMIT 1");
      if ($lastLog && $lastLog->num_rows > 0) {
        $rowLast = $lastLog->fetch_assoc();
        if ($rowLast['status'] === $status) {
          sendJson(true, "Status unchanged, log skipped");
        }
      }

      $stmt = $conn->prepare("INSERT INTO kipas_logs (status, mode, temperature, humidity, trigger_source, logged_at) VALUES (?, ?, NULL, NULL, ?, NOW())");
      // Mode bisa diambil dari setting
      $modeRow = $conn->query("SELECT mode FROM kipas_settings WHERE id = 1");
      $mode    = ($modeRow && $modeRow->num_rows > 0) ? $modeRow->fetch_assoc()['mode'] : 'manual';

      $stmt->bind_param("sss", $status, $mode, $trigger);

      if ($stmt->execute()) {
        sendJson(true, "Fan status logged: $status");
      } else {
        throw new Exception("Failed to log status");
      }
    } catch (Exception $e) {
      sendJson(false, $e->getMessage(), null, 500);
    }
  } else {
    // ---- 5B. Form-data (Dashboard/ESP32 lama) ----
    $status      = $_POST['status'] ?? '';
    $mode        = $_POST['mode'] ?? 'auto';
    $temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
    $humidity    = isset($_POST['humidity']) ? floatval($_POST['humidity']) : null;
    $trigger     = $_POST['trigger'] ?? 'auto';

    if (!in_array($status, ['on', 'off'])) {
      echo json_encode(['success' => false, 'error' => 'Status tidak valid']);
      exit;
    }

    if (!in_array($mode, ['auto', 'manual'])) {
      echo json_encode(['success' => false, 'error' => 'Mode tidak valid']);
      exit;
    }

    $stmt = $conn->prepare("INSERT INTO kipas_logs (status, mode, temperature, humidity, trigger_source, logged_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssdds", $status, $mode, $temperature, $humidity, $trigger);

    if ($stmt->execute()) {
      echo json_encode([
        'success' => true,
        'message' => 'Log berhasil ditambahkan',
        'id'      => $conn->insert_id
      ]);
    } else {
      echo json_encode(['success' => false, 'error' => 'Gagal menambahkan log: ' . $conn->error]);
    }
    $stmt->close();
    exit;
  }
}

// ==================== 6. GET LOGS (Dashboard) ====================
if ($action === 'get_logs') {
  $limit = intval($_GET['limit'] ?? 50);
  $limit = min($limit, 200); // Max 200

  $sql  = "SELECT id, status, mode, temperature, humidity, trigger_source, logged_at FROM kipas_logs ORDER BY logged_at DESC LIMIT ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $limit);
  $stmt->execute();
  $result = $stmt->get_result();

  $logs = [];
  while ($row = $result->fetch_assoc()) {
    $logs[] = [
      'id'          => $row['id'],
      'status'      => $row['status'],
      'mode'        => $row['mode'],
      'temperature' => $row['temperature'] ? floatval($row['temperature']) : null,
      'humidity'    => $row['humidity'] ? floatval($row['humidity']) : null,
      'trigger'     => $row['trigger'],
      'logged_at'   => $row['logged_at']
    ];
  }

  echo json_encode(['success' => true, 'data' => $logs, 'count' => count($logs)]);
  $stmt->close();
  exit;
}

// ==================== 7. GET LATEST STATUS (Versi gabungan baru) ====================
if ($action === 'get_latest_status') {
  try {
    // Ambil log terakhir (hapus backtick dari trigger)
    $sqlLog  = "SELECT status, mode, temperature, humidity, trigger_source, logged_at FROM kipas_logs ORDER BY logged_at DESC LIMIT 1";
    $resLog  = $conn->query($sqlLog);

    if (!$resLog) {
      // Jika query gagal, coba tanpa kolom trigger_source
      $sqlLog  = "SELECT status, mode, temperature, humidity, logged_at FROM kipas_logs ORDER BY logged_at DESC LIMIT 1";
      $resLog  = $conn->query($sqlLog);
    }

    $logData = [
      'status'      => 'off',
      'mode'        => 'manual',
      'temperature' => null,
      'humidity'    => null,
      'trigger'     => null,
      'logged_at'   => null
    ];

    if ($resLog && $resLog->num_rows > 0) {
      $rowLog              = $resLog->fetch_assoc();
      $logData['status']   = $rowLog['status'];
      $logData['mode']     = $rowLog['mode'];
      $logData['temperature'] = isset($rowLog['temperature']) && $rowLog['temperature'] ? floatval($rowLog['temperature']) : null;
      $logData['humidity']    = isset($rowLog['humidity']) && $rowLog['humidity'] ? floatval($rowLog['humidity']) : null;
      $logData['trigger']     = isset($rowLog['trigger_source']) ? $rowLog['trigger_source'] : (isset($rowLog['trigger']) ? $rowLog['trigger'] : null);
      $logData['logged_at']   = $rowLog['logged_at'];
    }

    // Ambil mode & threshold dari kipas_settings
    $sqlConfig = "SELECT mode, threshold_on, threshold_off FROM kipas_settings WHERE id = 1";
    $resConfig = $conn->query($sqlConfig);

    $config = [
      'mode'          => $logData['mode'], // fallback
      'threshold_on'  => 38.0,
      'threshold_off' => 30.0
    ];

    if ($resConfig && $resConfig->num_rows > 0) {
      $rowConfig             = $resConfig->fetch_assoc();
      $config['mode']        = $rowConfig['mode'];
      $config['threshold_on']  = floatval($rowConfig['threshold_on']);
      $config['threshold_off'] = floatval($rowConfig['threshold_off']);
    } else {
      // Jika belum ada row, buat default
      $conn->query("INSERT INTO kipas_settings (id, mode, threshold_on, threshold_off, updated_at) VALUES (1, 'manual', 38.0, 30.0, NOW())");
    }

    // Gabungkan
    sendJson(true, 'Data retrieved', [
      'status'        => $logData['status'],
      'mode'          => $config['mode'],
      'threshold_on'  => $config['threshold_on'],
      'threshold_off' => $config['threshold_off'],
      'temperature'   => $logData['temperature'],
      'humidity'      => $logData['humidity'],
      'trigger'       => $logData['trigger'],
      'logged_at'     => $logData['logged_at']
    ]);
  } catch (Exception $e) {
    sendJson(false, $e->getMessage(), null, 500);
  }
}

// ==================== 8. LOG DHT DATA (ESP32) ====================
if ($action === 'log_dht') {
  $temperature = floatval($_POST['temperature'] ?? 0);
  $humidity    = floatval($_POST['humidity'] ?? 0);

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
  exit;
}

// ==================== 9. GET LATEST DHT (Umum) ====================
if ($action === 'get_latest_dht') {
  $sql    = "SELECT * FROM dht_logs ORDER BY logged_at DESC LIMIT 1";
  $result = $conn->query($sql);

  if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
      'success' => true,
      'data' => [
        'temperature' => floatval($row['temperature']),
        'humidity'    => floatval($row['humidity']),
        'logged_at'   => $row['logged_at']
      ]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'No DHT data']);
  }
  exit;
}

// ==================== 10. GET DHT HISTORY (Dashboard) ====================
if ($action === 'get_dht_history') {
  $limit = intval($_GET['limit'] ?? 100);
  $limit = min($limit, 500);

  $sql  = "SELECT * FROM dht_logs ORDER BY logged_at DESC LIMIT ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $limit);
  $stmt->execute();
  $result = $stmt->get_result();

  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = [
      'temperature' => floatval($row['temperature']),
      'humidity'    => floatval($row['humidity']),
      'logged_at'   => $row['logged_at']
    ];
  }

  echo json_encode(['success' => true, 'data' => $data]);
  $stmt->close();
  exit;
}

// ==================== DEFAULT: Invalid action ====================
echo json_encode(['success' => false, 'error' => 'Invalid action']);
$conn->close();
exit;
