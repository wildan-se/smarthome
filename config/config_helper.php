<?php
// config/config_helper.php
// Helper functions untuk manage system configuration

// Jika tidak ada koneksi yang di-pass, gunakan dari config.php
require_once __DIR__ . '/config.php';

/**
 * Load all configurations from database
 * @return array Associative array of config_key => config_value
 */
function loadConfig($connection = null)
{
  global $conn;
  $db = $connection ?? $conn;

  $configs = [];
  $result = $db->query("SELECT config_key, config_value FROM config");

  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $configs[$row['config_key']] = $row['config_value'];
    }
  }

  return $configs;
}

/**
 * Get single config value by key
 * @param string $key Config key
 * @param mixed $default Default value if not found
 * @return mixed Config value or default
 */
function getConfig($key, $default = null, $connection = null)
{
  global $conn;
  $db = $connection ?? $conn;

  $stmt = $db->prepare("SELECT config_value FROM config WHERE config_key = ?");
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($row = $result->fetch_assoc()) {
    return $row['config_value'];
  }

  return $default;
}

/**
 * Set/Update config value
 * @param string $key Config key
 * @param string $value Config value
 * @param string $description Optional description
 * @return bool Success status
 */
function setConfig($key, $value, $description = '', $connection = null)
{
  global $conn;
  $db = $connection ?? $conn;

  if ($description) {
    $stmt = $db->prepare("INSERT INTO config (config_key, config_value, description) VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), description = VALUES(description)");
    $stmt->bind_param('sss', $key, $value, $description);
  } else {
    $stmt = $db->prepare("INSERT INTO config (config_key, config_value) VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
    $stmt->bind_param('ss', $key, $value);
  }

  return $stmt->execute();
}

/**
 * Get MQTT broker connection string
 * @return string Full MQTT broker URL
 */
function getMqttBrokerUrl($connection = null)
{
  $protocol = getConfig('mqtt_protocol', 'wss', $connection);
  $broker = getConfig('mqtt_broker', 'iotsmarthome.cloud.shiftr.io', $connection);
  $port = getConfig('mqtt_port', '443', $connection);

  return "{$protocol}://{$broker}";
}

/**
 * Get MQTT credentials
 * @return array ['username' => ..., 'password' => ...]
 */
function getMqttCredentials($connection = null)
{
  return [
    'username' => getConfig('mqtt_username', 'iotsmarthome', $connection),
    'password' => getConfig('mqtt_password', 'gxBVaUn5Bvf9yfIm', $connection)
  ];
}

/**
 * Get device serial number
 * @return string Device serial
 */
function getDeviceSerial($connection = null)
{
  return getConfig('device_serial', '12345678', $connection);
}

/**
 * Get MQTT topic root
 * @return string Topic root path
 */
function getMqttTopicRoot($connection = null)
{
  $serial = getDeviceSerial($connection);
  return "smarthome/{$serial}";
}

/**
 * Get all config with metadata for admin panel
 * @return array Array of config objects
 */
function getAllConfigWithMeta($connection = null)
{
  global $conn;
  $db = $connection ?? $conn;

  $configs = [];
  $result = $db->query("SELECT * FROM config ORDER BY id");

  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $configs[] = $row;
    }
  }

  return $configs;
}
