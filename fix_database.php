<?php
require_once 'config/config.php';

// Drop the foreign key constraint
$conn->query("ALTER TABLE rfid_cards DROP FOREIGN KEY rfid_cards_ibfk_1");
$conn->query("ALTER TABLE rfid_cards MODIFY added_by INT NULL");

// Create admin user if it doesn't exist
$password_hash = password_hash('admin123', PASSWORD_DEFAULT);
$conn->query("INSERT IGNORE INTO users (id, username, password, role) VALUES (1, 'admin', '$password_hash', 'admin')");

// Add back the foreign key with ON DELETE SET NULL
$conn->query("ALTER TABLE rfid_cards ADD CONSTRAINT rfid_cards_ibfk_1 FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL");

// Check users
$result = $conn->query("SELECT * FROM users");
echo "Users in database:\n";
while ($row = $result->fetch_assoc()) {
  print_r($row);
}

// Check RFID cards
$result = $conn->query("SELECT * FROM rfid_cards");
echo "\nRFID cards in database:\n";
while ($row = $result->fetch_assoc()) {
  print_r($row);
}
