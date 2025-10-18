<?php
header('Content-Type: application/json');
require_once 'config/config.php';

// Get latest door status from database
$query = "SELECT status, updated_at FROM door_status ORDER BY updated_at DESC LIMIT 1";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
  $data = mysqli_fetch_assoc($result);
  echo json_encode([
    'success' => true,
    'status' => $data['status'],
    'updated_at' => $data['updated_at']
  ]);
} else {
  echo json_encode([
    'success' => false,
    'status' => 'tertutup',
    'message' => 'No data available'
  ]);
}

mysqli_close($conn);
