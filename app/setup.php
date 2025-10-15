<?php
// Run this once from CLI or browser to create required tables and seed admin
require __DIR__ . '/src/db.php';

try {
  $pdo = get_pdo();
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS rfid_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uid VARCHAR(64) NOT NULL UNIQUE,
        label VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS rfid_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uid VARCHAR(64) NOT NULL,
        status ENUM('granted','denied') NOT NULL,
        ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS sensor_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        temperature DECIMAL(6,2) NULL,
        humidity DECIMAL(6,2) NULL,
        ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS door_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status VARCHAR(50) NOT NULL,
        ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        k VARCHAR(100) PRIMARY KEY,
        v TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // seed admin if not exists
  $stmt = db_query('SELECT COUNT(*) as c FROM users');
  $cnt = $stmt->fetch()['c'];
  if ($cnt == 0) {
    $password = password_hash('admin123', PASSWORD_BCRYPT);
    db_query('INSERT INTO users (username, password_hash) VALUES (:u, :p)', ['u' => 'admin', 'p' => $password]);
    echo "Admin user 'admin' created with password 'admin123'\n";
  } else {
    echo "Users table already has data. Skipping admin seed.\n";
  }

  echo "Setup finished.\n";
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
