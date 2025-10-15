<?php
require_once 'config/config.php';

// Check rfid_cards table
$result = $conn->query("SHOW TABLES LIKE 'rfid_cards'");
if ($result->num_rows == 0) {
  echo "Table rfid_cards does not exist!\n";

  // Create the table
  $sql = "CREATE TABLE rfid_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uid VARCHAR(50) NOT NULL UNIQUE,
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

  if ($conn->query($sql)) {
    echo "Created rfid_cards table successfully\n";
  } else {
    echo "Error creating rfid_cards table: " . $conn->error . "\n";
  }
} else {
  echo "rfid_cards table exists\n";
  // Show table structure
  $result = $conn->query("DESCRIBE rfid_cards");
  while ($row = $result->fetch_assoc()) {
    print_r($row);
  }
}

// Check rfid_logs table
$result = $conn->query("SHOW TABLES LIKE 'rfid_logs'");
if ($result->num_rows == 0) {
  echo "Table rfid_logs does not exist!\n";

  // Create the table
  $sql = "CREATE TABLE rfid_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uid VARCHAR(50) NOT NULL,
        access_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) NOT NULL
    )";

  if ($conn->query($sql)) {
    echo "Created rfid_logs table successfully\n";
  } else {
    echo "Error creating rfid_logs table: " . $conn->error . "\n";
  }
} else {
  echo "rfid_logs table exists\n";
  // Show table structure
  $result = $conn->query("DESCRIBE rfid_logs");
  while ($row = $result->fetch_assoc()) {
    print_r($row);
  }
}

// Show any existing cards
echo "\nExisting RFID cards:\n";
$result = $conn->query("SELECT * FROM rfid_cards");
while ($row = $result->fetch_assoc()) {
  print_r($row);
}
