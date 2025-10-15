<?php
require_once 'config/config.php';

// Check users table
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows == 0) {
  echo "Table users does not exist!\n";

  // Create the table
  $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

  if ($conn->query($sql)) {
    echo "Created users table successfully\n";

    // Add default admin user
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (id, username, password) VALUES (1, 'admin', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $password_hash);
    if ($stmt->execute()) {
      echo "Created default admin user\n";
    } else {
      echo "Error creating default user: " . $conn->error . "\n";
    }
  } else {
    echo "Error creating users table: " . $conn->error . "\n";
  }
} else {
  echo "users table exists\n";
  // Show table structure
  $result = $conn->query("DESCRIBE users");
  while ($row = $result->fetch_assoc()) {
    print_r($row);
  }

  // Show existing users
  echo "\nExisting users:\n";
  $result = $conn->query("SELECT id, username, created_at FROM users");
  while ($row = $result->fetch_assoc()) {
    print_r($row);
  }
}
