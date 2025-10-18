<?php
require_once 'config/config.php';

echo "🔧 Updating RFID tables...\n\n";

// Check if 'name' column already exists
$result = $conn->query("SHOW COLUMNS FROM rfid_cards LIKE 'name'");
if ($result->num_rows == 0) {
  // Add 'name' column to rfid_cards table
  $sql = "ALTER TABLE rfid_cards ADD COLUMN name VARCHAR(100) NULL AFTER uid";
  if ($conn->query($sql)) {
    echo "✅ Added 'name' column to rfid_cards table\n";
  } else {
    echo "❌ Error adding 'name' column: " . $conn->error . "\n";
  }
} else {
  echo "ℹ️  'name' column already exists in rfid_cards table\n";
} // Check table structure
echo "\n📋 Current rfid_cards structure:\n";
$result = $conn->query("DESCRIBE rfid_cards");
while ($row = $result->fetch_assoc()) {
  echo "  - {$row['Field']} ({$row['Type']})\n";
}

// Check rfid_logs structure
echo "\n📋 Current rfid_logs structure:\n";
$result = $conn->query("DESCRIBE rfid_logs");
while ($row = $result->fetch_assoc()) {
  echo "  - {$row['Field']} ({$row['Type']})\n";
}

echo "\n✅ Database update completed!\n";
