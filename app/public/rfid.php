<?php
require_once __DIR__ . '/../src/auth.php';
require_login();
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/mqtt_publish.php';
$cfg = require __DIR__ . '/../src/config.php';
$topicRoot = $cfg['mqtt']['topic_root'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_POST['action']) && $_POST['action'] === 'add') {
    $uid = strtoupper(trim($_POST['uid']));
    if ($uid) {
      db_query('INSERT IGNORE INTO rfid_cards (uid) VALUES (:u)', ['u' => $uid]);
      // publish register command
      mqtt_publish($topicRoot . '/rfid/register', $uid);
      header('Location: /rfid.php');
      exit;
    }
  }
  if (!empty($_POST['action']) && $_POST['action'] === 'remove') {
    $uid = strtoupper(trim($_POST['uid']));
    if ($uid) {
      db_query('DELETE FROM rfid_cards WHERE uid = :u', ['u' => $uid]);
      mqtt_publish($topicRoot . '/rfid/remove', $uid);
      header('Location: /rfid.php');
      exit;
    }
  }
}

$cards = db_query('SELECT * FROM rfid_cards ORDER BY created_at DESC')->fetchAll();
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manajemen RFID</title>
  <link rel="stylesheet" href="/scss/adminlte.css">
  <link rel="stylesheet" href="/scss/adminlte.min.css">
</head>

<body>
  <div class="container">
    <h1>Manajemen Kartu RFID</h1>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div><label>UID Kartu</label><input name="uid"></div>
      <button type="submit">Tambah & Kirim ke ESP32</button>
    </form>

    <h2>Daftar Kartu</h2>
    <table class="table">
      <thead>
        <tr>
          <th>UID</th>
          <th>Label</th>
          <th>Tindakan</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cards as $c): ?>
          <tr>
            <td><?php echo htmlspecialchars($c['uid']); ?></td>
            <td><?php echo htmlspecialchars($c['label']); ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="uid" value="<?php echo htmlspecialchars($c['uid']); ?>">
                <button type="submit">Hapus & Kirim ke ESP32</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>

</html>