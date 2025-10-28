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

-- Tabel Konfigurasi Kipas Otomatis
CREATE TABLE fan_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mode ENUM('auto', 'manual') DEFAULT 'auto',
  threshold_temp_on FLOAT DEFAULT 34.0,
  threshold_temp_off FLOAT DEFAULT 28.0,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by INT,
  FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Tabel Log Kipas (mode auto)
CREATE TABLE fan_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  status ENUM('on', 'off') NOT NULL,
  temperature FLOAT,
  humidity FLOAT,
  triggered_by ENUM('auto', 'manual') DEFAULT 'auto',
  logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default fan config
INSERT INTO fan_config (mode, threshold_temp_on, threshold_temp_off) 
VALUES ('auto', 34.0, 28.0);
