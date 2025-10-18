<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Get recent door status logs (last 100 records)
$sql = "SELECT id, status, DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i:%s') as updated_at 
        FROM door_status 
        ORDER BY updated_at DESC 
        LIMIT 100";

$result = $conn->query($sql);
$data = [];

if ($result) {
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }
}

echo json_encode([
  'success' => true,
  'data' => $data
]);

$conn->close();
