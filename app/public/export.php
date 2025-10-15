<?php
require_once __DIR__ . '/../src/auth.php';
require_login();
require_once __DIR__ . '/../src/db.php';

$type = $_GET['type'] ?? 'sensor';
if ($type === 'sensor') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="sensor_logs.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['id', 'temperature', 'humidity', 'ts']);
  $rows = db_query('SELECT * FROM sensor_logs ORDER BY ts DESC')->fetchAll();
  foreach ($rows as $r) fputcsv($out, [$r['id'], $r['temperature'], $r['humidity'], $r['ts']]);
  fclose($out);
  exit;
} elseif ($type === 'rfid') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="rfid_logs.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['id', 'uid', 'status', 'ts']);
  $rows = db_query('SELECT * FROM rfid_logs ORDER BY ts DESC')->fetchAll();
  foreach ($rows as $r) fputcsv($out, [$r['id'], $r['uid'], $r['status'], $r['ts']]);
  fclose($out);
  exit;
}

http_response_code(400);
echo "Unknown export type";
