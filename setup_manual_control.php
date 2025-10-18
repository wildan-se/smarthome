<?php
// Script untuk menambahkan kartu virtual "Kontrol Manual" ke database
require_once 'config/config.php';

echo "🔧 Menambahkan Kartu Virtual untuk Kontrol Manual...\n\n";

// Cek apakah sudah ada
$check = $conn->query("SELECT * FROM rfid_cards WHERE uid = 'MANUAL_CONTROL'");

if ($check->num_rows > 0) {
  echo "✅ Kartu 'MANUAL_CONTROL' sudah ada di database.\n";
  $card = $check->fetch_assoc();
  echo "   UID: " . $card['uid'] . "\n";
  echo "   Nama: " . ($card['name'] ?? 'NULL') . "\n\n";

  // Update jika perlu
  $user = $conn->query("SELECT id FROM users LIMIT 1")->fetch_assoc();
  $user_id = $user['id'];

  $update = $conn->query("UPDATE rfid_cards SET 
        name = '🎛️ Kontrol Manual Admin',
        added_by = $user_id
        WHERE uid = 'MANUAL_CONTROL'");
  if ($update) {
    echo "✅ Data kartu diperbarui.\n";
  }
} else {
  // Tambahkan kartu baru - gunakan user pertama yang ada
  $user = $conn->query("SELECT id FROM users LIMIT 1")->fetch_assoc();
  $user_id = $user['id'];

  $stmt = $conn->prepare("INSERT INTO rfid_cards (uid, name, added_by) VALUES (?, ?, ?)");
  $uid = 'MANUAL_CONTROL';
  $name = '🎛️ Kontrol Manual Admin';
  $stmt->bind_param('ssi', $uid, $name, $user_id);
  if ($stmt->execute()) {
    echo "✅ Kartu 'MANUAL_CONTROL' berhasil ditambahkan!\n";
    echo "   UID: MANUAL_CONTROL\n";
    echo "   Nama: 🎛️ Kontrol Manual Admin\n\n";
  } else {
    echo "❌ Error: " . $stmt->error . "\n";
  }
  $stmt->close();
}

echo "\n📊 Daftar semua kartu RFID:\n";
echo "=" . str_repeat("=", 80) . "\n";
printf("%-20s | %-40s | %-15s\n", "UID", "NAMA", "DITAMBAHKAN");
echo "=" . str_repeat("=", 80) . "\n";
$result = $conn->query("SELECT uid, name, added_at FROM rfid_cards ORDER BY added_at DESC");
while ($row = $result->fetch_assoc()) {
  printf(
    "%-20s | %-40s | %s\n",
    $row['uid'],
    $row['name'] ?? '(Tidak ada nama)',
    $row['added_at']
  );
}
echo "=" . str_repeat("=", 80) . "\n";

$conn->close();
echo "\n✅ Selesai!\n";
