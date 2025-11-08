<?php
require_once '../config/config.php';
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

// List semua kartu dengan informasi admin
if ($action === 'list') {
  $sql = 'SELECT r.uid, r.name, r.added_at, COALESCE(u.username, "System") as added_by_name 
          FROM rfid_cards r 
          LEFT JOIN users u ON r.added_by = u.id 
          ORDER BY r.added_at DESC';
  $result = $conn->query($sql);
  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }
  echo json_encode(['success' => true, 'data' => $data]);
  exit;
}

// Ambil log akses dengan nama pengguna (semua riwayat, tidak hanya 1 per UID)
if ($action === 'getlogs') {
  // Parameter limit dari query string (default: 20, enforce 1..20)
  // Pastikan API hanya mengembalikan maksimal 20 record untuk efisiensi
  $limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 20) : 20;

  // ❌ Exclude MANUAL_CONTROL dari tampilan log RFID
  // ✅ LEFT JOIN agar tetap tampil meskipun kartu sudah dihapus
  // ✅ Tampilkan riwayat akses sesuai limit, urutkan dari yang terbaru
  $sql = 'SELECT 
            l.uid, 
            l.access_time, 
            l.status, 
            COALESCE(c.name, "Kartu Terhapus") as name 
          FROM rfid_logs l 
          LEFT JOIN rfid_cards c ON l.uid = c.uid 
          WHERE l.uid != "MANUAL_CONTROL"
          ORDER BY l.access_time DESC 
          LIMIT ' . $limit;
  $result = $conn->query($sql);
  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }
  echo json_encode(['success' => true, 'data' => $data, 'count' => count($data)]);
  exit;
}

// Tambah kartu ke database
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  session_start();
  $uid = $_POST['uid'] ?? '';
  $name = $_POST['name'] ?? '';
  $added_by = $_SESSION['user_id'] ?? null;

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

  // Tambah kartu baru dengan added_by dari session
  $stmt = $conn->prepare('INSERT INTO rfid_cards (uid, name, added_at, added_by) VALUES (?, ?, NOW(), ?)');
  $stmt->bind_param('ssi', $uid, $name, $added_by);
  if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    echo json_encode([
      'success' => true,
      'message' => 'Kartu berhasil ditambahkan',
      'data' => [
        'id' => $newId,
        'uid' => $uid,
        'name' => $name,
        'added_at' => date('Y-m-d H:i:s')
      ]
    ]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Gagal menambahkan kartu: ' . $stmt->error]);
  }
  $stmt->close();
  exit;
}

// Tambah log akses (dengan pengecekan duplikat)
if ($action === 'log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $uid = $_POST['uid'] ?? '';
  $status = $_POST['status'] ?? '';

  if (!$uid || !$status) {
    echo json_encode(['success' => false, 'error' => 'UID dan status harus diisi']);
    exit;
  }

  // ✅ Validasi: Hanya log untuk kartu yang TERDAFTAR
  $stmt = $conn->prepare('SELECT COUNT(*) as count FROM rfid_cards WHERE uid = ?');
  $stmt->bind_param('s', $uid);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();

  if ($row['count'] == 0) {
    // Kartu tidak terdaftar, skip logging
    echo json_encode(['success' => true, 'message' => 'Card not registered, log skipped', 'skipped' => true]);
    exit;
  }

  // ✅ Cek apakah ada log yang sama dalam 2 detik terakhir (untuk hindari duplikat)
  $stmt = $conn->prepare('SELECT COUNT(*) as count 
                           FROM rfid_logs 
                           WHERE uid = ? 
                           AND status = ? 
                           AND access_time >= DATE_SUB(NOW(), INTERVAL 2 SECOND)');
  $stmt->bind_param('ss', $uid, $status);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();

  if ($row['count'] > 0) {
    // Log duplikat dalam 2 detik terakhir, skip
    echo json_encode(['success' => true, 'message' => 'Log skipped (duplicate within 2 seconds)', 'skipped' => true]);
    exit;
  }

  // Tambah log baru
  $stmt = $conn->prepare('INSERT INTO rfid_logs (uid, access_time, status) VALUES (?, NOW(), ?)');
  if (!$stmt) {
    error_log("RFID Log Failed - Prepare error: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit;
  }

  $stmt->bind_param('ss', $uid, $status);
  if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Log berhasil ditambahkan']);
  } else {
    error_log("RFID Log Failed - Execute error: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Gagal menambahkan log: ' . $stmt->error]);
  }
  $stmt->close();
  exit;
}

// Hapus kartu
if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $uid = $_POST['uid'] ?? '';

  // Log untuk debugging
  error_log("RFID Remove Request - UID: " . $uid);

  if (!$uid) {
    echo json_encode(['success' => false, 'error' => 'UID tidak boleh kosong']);
    exit;
  }

  // Mulai transaction untuk memastikan atomicity
  $conn->begin_transaction();

  try {
    // Hapus log terlebih dahulu (foreign key constraint)
    $stmt1 = $conn->prepare('DELETE FROM rfid_logs WHERE uid = ?');
    if (!$stmt1) {
      throw new Exception('Database error: ' . $conn->error);
    }
    $stmt1->bind_param('s', $uid);
    $stmt1->execute();
    $logs_deleted = $stmt1->affected_rows;
    $stmt1->close();

    // Kemudian hapus kartu
    $stmt2 = $conn->prepare('DELETE FROM rfid_cards WHERE uid = ?');
    if (!$stmt2) {
      throw new Exception('Database error: ' . $conn->error);
    }
    $stmt2->bind_param('s', $uid);
    $stmt2->execute();
    $cards_deleted = $stmt2->affected_rows;
    $stmt2->close();

    // Commit transaction
    $conn->commit();

    error_log("RFID Remove Success - Cards: $cards_deleted, Logs: $logs_deleted");
    echo json_encode([
      'success' => true,
      'message' => 'Kartu berhasil dihapus',
      'deleted' => [
        'cards' => $cards_deleted,
        'logs' => $logs_deleted
      ]
    ]);
  } catch (Exception $e) {
    // Rollback jika ada error
    $conn->rollback();
    error_log("RFID Remove Failed - Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Gagal menghapus kartu: ' . $e->getMessage()]);
  }
  exit;
}

// Response for invalid request
echo json_encode(['success' => false, 'error' => 'Invalid request']);
