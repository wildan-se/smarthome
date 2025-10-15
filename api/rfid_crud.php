<?php
require_once '../config/config.php';
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

// List semua kartu
if ($action === 'list') {
  $stmt = $conn->prepare('SELECT uid, added_at FROM rfid_cards ORDER BY added_at DESC');
  $stmt->execute();
  $result = $stmt->get_result();
  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }
  echo json_encode(['success' => true, 'data' => $data]);
  exit;
}

// Ambil log akses
if ($action === 'getlogs') {
  $stmt = $conn->prepare('SELECT uid, access_time, status FROM rfid_logs ORDER BY access_time DESC LIMIT 100');
  $stmt->execute();
  $result = $stmt->get_result();
  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }
  echo json_encode(['success' => true, 'data' => $data]);
  exit;
}

// Tambah kartu ke database
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $uid = $_POST['uid'] ?? '';
  if (!$uid) {
    echo json_encode(['success' => false, 'error' => 'UID tidak boleh kosong']);
    exit;
  }

  // Cek duplikat
  $stmt = $conn->prepare('SELECT COUNT(*) as count FROM rfid_cards WHERE uid = ?');
  $stmt->bind_param('s', $uid);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();

  if ($row['count'] > 0) {
    echo json_encode(['success' => false, 'error' => 'UID sudah terdaftar']);
    exit;
  }

  // Tambah kartu baru
  $stmt = $conn->prepare('INSERT INTO rfid_cards (uid, added_at, added_by) VALUES (?, NOW(), 2)');
  $stmt->bind_param('s', $uid);
  if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    echo json_encode([
      'success' => true,
      'message' => 'Kartu berhasil ditambahkan',
      'data' => [
        'id' => $newId,
        'uid' => $uid,
        'added_at' => date('Y-m-d H:i:s')
      ]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal menambahkan kartu: ' . $stmt->error]);
  }
  $stmt->close();
  exit;
}

// Tambah log akses
if ($action === 'log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $uid = $_POST['uid'] ?? '';
  $status = $_POST['status'] ?? '';

  if (!$uid || !$status) {
    echo json_encode(['success' => false, 'error' => 'UID dan status harus diisi']);
    exit;
  }

  $stmt = $conn->prepare('INSERT INTO rfid_logs (uid, access_time, status) VALUES (?, NOW(), ?)');
  $stmt->bind_param('ss', $uid, $status);
  if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Log berhasil ditambahkan']);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal menambahkan log: ' . $stmt->error]);
  }
  $stmt->close();
  exit;
}

// Hapus kartu
if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $uid = $_POST['uid'] ?? '';
  if (!$uid) {
    echo json_encode(['success' => false, 'error' => 'UID tidak boleh kosong']);
    exit;
  }

  $stmt = $conn->prepare('DELETE FROM rfid_cards WHERE uid = ?');
  $stmt->bind_param('s', $uid);
  if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Kartu berhasil dihapus']);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal menghapus kartu: ' . $stmt->error]);
  }
  $stmt->close();
  exit;
}

// Response for invalid request
echo json_encode(['success' => false, 'error' => 'Invalid request']);
