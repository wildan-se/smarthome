<?php
// Script untuk membuat/update tabel config
require_once 'config/config.php';

echo "🔧 Setup Tabel Config...\n\n";

// Cek apakah tabel config sudah ada
$result = $conn->query("SHOW TABLES LIKE 'config'");

if ($result->num_rows > 0) {
  echo "ℹ️  Tabel config sudah ada. Checking structure...\n";

  // Cek struktur
  $columns = $conn->query("DESCRIBE config");
  echo "\n📋 Current structure:\n";
  while ($col = $columns->fetch_assoc()) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
  }
} else {
  echo "📦 Membuat tabel config baru...\n";

  $sql = "CREATE TABLE config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_key VARCHAR(100) UNIQUE NOT NULL,
        config_value TEXT,
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (config_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

  if ($conn->query($sql)) {
    echo "✅ Tabel config berhasil dibuat!\n";
  } else {
    echo "❌ Error: " . $conn->error . "\n";
    exit(1);
  }
}

echo "\n🔄 Inserting/Updating default configurations...\n\n";

// Default configurations
$configs = [
  ['mqtt_broker', 'iotsmarthome.cloud.shiftr.io', 'Alamat MQTT Broker (tanpa wss://)'],
  ['mqtt_username', 'iotsmarthome', 'Username untuk autentikasi MQTT'],
  ['mqtt_password', 'gxBVaUn5Bvf9yfIm', 'Password untuk autentikasi MQTT'],
  ['device_serial', '12345678', 'Serial number device ESP32'],
  ['sensor_interval', '10000', 'Interval pembacaan sensor DHT (milliseconds)'],
  ['door_timeout', '5000', 'Waktu delay auto-close pintu (milliseconds)'],
  ['site_name', 'Smart Home IoT', 'Nama aplikasi yang ditampilkan'],
  ['timezone', 'Asia/Jakarta', 'Timezone untuk sistem'],
  ['mqtt_port', '443', 'Port MQTT (443 untuk WSS, 1883 untuk TCP)'],
  ['mqtt_protocol', 'wss', 'Protokol MQTT (wss atau ws)']
];

$stmt = $conn->prepare("INSERT INTO config (config_key, config_value, description) VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), description = VALUES(description)");

foreach ($configs as $config) {
  $stmt->bind_param('sss', $config[0], $config[1], $config[2]);
  if ($stmt->execute()) {
    echo "✅ [{$config[0]}] = {$config[1]}\n";
  } else {
    echo "❌ Error on {$config[0]}: " . $stmt->error . "\n";
  }
}

$stmt->close();

echo "\n📊 Daftar Konfigurasi Saat Ini:\n";
echo "=" . str_repeat("=", 100) . "\n";
printf("%-20s | %-40s | %-35s\n", "KEY", "VALUE", "DESCRIPTION");
echo "=" . str_repeat("=", 100) . "\n";

$result = $conn->query("SELECT config_key, config_value, description FROM config ORDER BY id");
while ($row = $result->fetch_assoc()) {
  printf(
    "%-20s | %-40s | %-35s\n",
    $row['config_key'],
    substr($row['config_value'], 0, 40),
    substr($row['description'], 0, 35)
  );
}
echo "=" . str_repeat("=", 100) . "\n";

$conn->close();
echo "\n✅ Setup Config Selesai!\n";
