<?php
// api/config_crud.php
// API untuk CRUD operations pada system configuration

header('Content-Type: application/json');
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
  exit;
}

require_once '../config/config.php';
require_once '../config/config_helper.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
  case 'list':
    // Get all configurations with metadata
    handleList();
    break;

  case 'get':
    // Get single config by key
    $key = $_GET['key'] ?? '';
    handleGet($key);
    break;

  case 'update':
    // Update configuration value
    handleUpdate();
    break;

  case 'test_mqtt':
    // Test MQTT connection
    handleTestMqtt();
    break;

  case 'reset':
    // Reset to default values
    handleReset();
    break;

  default:
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/**
 * List all configurations
 */
function handleList()
{
  try {
    $configs = getAllConfigWithMeta();

    echo json_encode([
      'status' => 'success',
      'data' => $configs
    ]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
      'status' => 'error',
      'message' => $e->getMessage()
    ]);
  }
}

/**
 * Get single configuration
 */
function handleGet($key)
{
  if (empty($key)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Config key required']);
    return;
  }

  try {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM config WHERE config_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      echo json_encode([
        'status' => 'success',
        'data' => $row
      ]);
    } else {
      http_response_code(404);
      echo json_encode([
        'status' => 'error',
        'message' => 'Config not found'
      ]);
    }
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
      'status' => 'error',
      'message' => $e->getMessage()
    ]);
  }
}

/**
 * Update configuration
 */
function handleUpdate()
{
  // Ambil data dari POST
  $updates = json_decode(file_get_contents('php://input'), true);

  if (!$updates || !is_array($updates)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data format']);
    return;
  }

  try {
    global $conn;
    $conn->begin_transaction();

    $updated = [];
    $failed = [];

    foreach ($updates as $key => $value) {
      // Validasi key exists
      $check = $conn->prepare("SELECT id FROM config WHERE config_key = ?");
      $check->bind_param('s', $key);
      $check->execute();

      if ($check->get_result()->num_rows === 0) {
        $failed[$key] = 'Config key not found';
        continue;
      }

      // Validasi value berdasarkan key
      $validated = validateConfigValue($key, $value);
      if ($validated['error']) {
        $failed[$key] = $validated['error'];
        continue;
      }

      // Update
      $stmt = $conn->prepare("UPDATE config SET config_value = ?, updated_at = CURRENT_TIMESTAMP WHERE config_key = ?");
      $stmt->bind_param('ss', $value, $key);

      if ($stmt->execute()) {
        $updated[$key] = $value;
      } else {
        $failed[$key] = 'Update failed';
      }
    }

    $conn->commit();

    echo json_encode([
      'status' => 'success',
      'message' => 'Configuration updated',
      'updated' => $updated,
      'failed' => $failed
    ]);
  } catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
      'status' => 'error',
      'message' => $e->getMessage()
    ]);
  }
}

/**
 * Test MQTT connection
 */
function handleTestMqtt()
{
  $data = json_decode(file_get_contents('php://input'), true);

  $broker = $data['broker'] ?? '';
  $username = $data['username'] ?? '';
  $password = $data['password'] ?? '';
  $port = $data['port'] ?? '443';
  $protocol = $data['protocol'] ?? 'wss';

  if (empty($broker) || empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'message' => 'Broker, username, and password required'
    ]);
    return;
  }

  // Untuk WebSocket (wss/ws), kita tidak bisa test dari PHP
  // Return success jika format valid
  $brokerUrl = "{$protocol}://{$broker}";

  // Validasi format URL
  if (!filter_var($broker, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    echo json_encode([
      'status' => 'error',
      'message' => 'Invalid broker hostname format'
    ]);
    return;
  }

  // Validasi port
  if (!is_numeric($port) || $port < 1 || $port > 65535) {
    echo json_encode([
      'status' => 'error',
      'message' => 'Invalid port number'
    ]);
    return;
  }

  echo json_encode([
    'status' => 'success',
    'message' => 'MQTT configuration format is valid',
    'note' => 'Actual connection test will be performed by the client'
  ]);
}

/**
 * Reset configuration to defaults
 */
function handleReset()
{
  $defaults = [
    'mqtt_broker' => 'iotsmarthome.cloud.shiftr.io',
    'mqtt_username' => 'iotsmarthome',
    'mqtt_password' => 'gxBVaUn5Bvf9yfIm',
    'mqtt_port' => '443',
    'mqtt_protocol' => 'wss',
    'device_serial' => '12345678',
    'sensor_interval' => '10000',
    'door_timeout' => '5000',
    'site_name' => 'Smart Home IoT',
    'timezone' => 'Asia/Jakarta'
  ];

  try {
    global $conn;
    $conn->begin_transaction();

    foreach ($defaults as $key => $value) {
      $stmt = $conn->prepare("UPDATE config SET config_value = ?, updated_at = CURRENT_TIMESTAMP WHERE config_key = ?");
      $stmt->bind_param('ss', $value, $key);
      $stmt->execute();
    }

    $conn->commit();

    echo json_encode([
      'status' => 'success',
      'message' => 'Configuration reset to defaults',
      'defaults' => $defaults
    ]);
  } catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
      'status' => 'error',
      'message' => $e->getMessage()
    ]);
  }
}

/**
 * Validate config value based on key
 */
function validateConfigValue($key, $value)
{
  $error = null;

  switch ($key) {
    case 'mqtt_port':
      if (!is_numeric($value) || $value < 1 || $value > 65535) {
        $error = 'Port must be between 1 and 65535';
      }
      break;

    case 'mqtt_protocol':
      if (!in_array($value, ['wss', 'ws', 'mqtt', 'mqtts'])) {
        $error = 'Protocol must be wss, ws, mqtt, or mqtts';
      }
      break;

    case 'sensor_interval':
    case 'door_timeout':
      if (!is_numeric($value) || $value < 100) {
        $error = 'Interval must be at least 100ms';
      }
      break;

    case 'mqtt_broker':
      if (!filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $error = 'Invalid broker hostname';
      }
      break;

    case 'device_serial':
      if (strlen($value) < 4) {
        $error = 'Device serial must be at least 4 characters';
      }
      break;
  }

  return ['error' => $error];
}
