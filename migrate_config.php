<?php
// Script untuk migrate tabel config ke struktur baru
require_once 'config/config.php';

echo "🔧 Migrasi Tabel Config...\n\n";

// Cek struktur lama
$result = $conn->query("DESCRIBE config");
$columns = [];
while ($col = $result->fetch_assoc()) {
  $columns[] = $col['Field'];
}

echo "📋 Struktur lama:\n";
foreach ($columns as $col) {
  echo "  - $col\n";
}

// Cek apakah sudah struktur baru (ada config_key)
if (!in_array('config_key', $columns)) {
  echo "\n🔄 Mengubah struktur ke format baru...\n";

  // Backup data lama
  echo "📦 Backup data lama...\n";
  $oldData = $conn->query("SELECT * FROM config LIMIT 1");
  $old = $oldData->fetch_assoc();

  if ($old) {
    echo "✅ Data lama:\n";
    foreach ($old as $key => $value) {
      if ($key != 'id') {
        echo "  - $key = $value\n";
      }
    }
  }

  // Drop tabel lama
  echo "\n⚠️  Dropping old table...\n";
  $conn->query("DROP TABLE IF EXISTS config_backup");
  $conn->query("RENAME TABLE config TO config_backup");
  echo "✅ Old table renamed to config_backup\n";

  // Buat tabel baru
  echo "\n📦 Creating new config table...\n";
  $sql = "CREATE TABLE config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_key VARCHAR(100) UNIQUE NOT NULL,
        config_value TEXT,
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (config_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

  if ($conn->query($sql)) {
    echo "✅ Tabel config baru berhasil dibuat!\n";
  } else {
    echo "❌ Error: " . $conn->error . "\n";
    exit(1);
  }

  // Migrate data dari backup
  if ($old) {
    echo "\n🔄 Migrating old data...\n";

    $migrations = [
      ['broker', $old['broker'] ?? 'iotsmarthome.cloud.shiftr.io', 'Alamat MQTT Broker'],
      ['mqtt_username', $old['mqtt_username'] ?? 'iotsmarthome', 'Username MQTT'],
      ['mqtt_password', $old['mqtt_password'] ?? 'gxBVaUn5Bvf9yfIm', 'Password MQTT'],
      ['device_serial', $old['device_serial'] ?? '12345678', 'Serial Number ESP32'],
      ['sensor_interval', $old['sensor_interval'] ?? '10000', 'Interval Sensor (ms)'],
    ];

    $stmt = $conn->prepare("INSERT INTO config (config_key, config_value, description) VALUES (?, ?, ?)");

    foreach ($migrations as $m) {
      $stmt->bind_param('sss', $m[0], $m[1], $m[2]);
      if ($stmt->execute()) {
        echo "✅ Migrated: {$m[0]} = {$m[1]}\n";
      }
    }
    $stmt->close();
  }
}

// Insert/Update default configs
echo "\n🔄 Inserting/Updating default configurations...\n";

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
                        ON DUPLICATE KEY UPDATE description = VALUES(description)");

foreach ($configs as $config) {
  $stmt->bind_param('sss', $config[0], $config[1], $config[2]);
  if ($stmt->execute()) {
    echo "✅ {$config[0]}\n";
  }
}

$stmt->close();

echo "\n📊 Konfigurasi Final:\n";
echo "=" . str_repeat("=", 100) . "\n";
printf("%-20s | %-40s | %-35s\n", "KEY", "VALUE", "DESCRIPTION");
echo "=" . str_repeat("=", 100) . "\n";

$result = $conn->query("SELECT config_key, config_value, description, updated_at FROM config ORDER BY id");
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
echo "\n✅ Migrasi Config Selesai!\n";
echo "\nℹ️  Tabel lama tersimpan di 'config_backup' (bisa dihapus jika sudah aman)\n";
