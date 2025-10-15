-- Struktur Database Smarthome IoT

-- Tabel User Admin
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100),
  role VARCHAR(20) DEFAULT 'admin',
  reset_token VARCHAR(255),
  last_login DATETIME
);

-- Tabel Kartu RFID
CREATE TABLE rfid_cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uid VARCHAR(32) NOT NULL UNIQUE,
  added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  added_by INT,
  FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Tabel Log Akses RFID
CREATE TABLE rfid_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uid VARCHAR(32) NOT NULL,
  access_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('granted','denied') NOT NULL,
  FOREIGN KEY (uid) REFERENCES rfid_cards(uid)
);

-- Tabel Log Suhu & Kelembapan
CREATE TABLE dht_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  temperature FLOAT,
  humidity FLOAT,
  log_time DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Status Pintu
CREATE TABLE door_status (
  id INT AUTO_INCREMENT PRIMARY KEY,
  status ENUM('terbuka','tertutup') NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Konfigurasi MQTT & Device
CREATE TABLE config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  broker VARCHAR(100),
  mqtt_username VARCHAR(50),
  mqtt_password VARCHAR(100),
  topic_root VARCHAR(100),
  device_serial VARCHAR(20),
  sensor_interval INT DEFAULT 10
);
