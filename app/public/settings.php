<?php
require_once __DIR__ . '/../src/auth.php';
require_login();
require_once __DIR__ . '/../src/db.php';
$cfg = require __DIR__ . '/../src/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $broker = trim($_POST['broker'] ?? '');
  $port = intval($_POST['port'] ?? 1883);
  $user = trim($_POST['username'] ?? '');
  $pass = trim($_POST['password'] ?? '');
  $topic = trim($_POST['topic_root'] ?? 'smarthome/12345678');
  db_query('REPLACE INTO settings (k, v) VALUES ("mqtt_host", :h), ("mqtt_port", :p), ("mqtt_user", :u), ("mqtt_pass", :pw), ("topic_root", :t)', ['h' => $broker, 'p' => $port, 'u' => $user, 'pw' => $pass, 't' => $topic]);
  header('Location: /settings.php');
  exit;
}

$row = db_query('SELECT k, v FROM settings')->fetchAll();
$s = [];
foreach ($row as $r) $s[$r['k']] = $r['v'];

?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pengaturan MQTT</title>
</head>

<body>
  <h1>Pengaturan MQTT & Device</h1>
  <form method="post">
    <div><label>Broker</label><input name="broker" value="<?php echo htmlspecialchars($s['mqtt_host'] ?? $cfg['mqtt']['host']); ?>"></div>
    <div><label>Port</label><input name="port" value="<?php echo htmlspecialchars($s['mqtt_port'] ?? $cfg['mqtt']['port']); ?>"></div>
    <div><label>Username</label><input name="username" value="<?php echo htmlspecialchars($s['mqtt_user'] ?? $cfg['mqtt']['username']); ?>"></div>
    <div><label>Password</label><input name="password" value="<?php echo htmlspecialchars($s['mqtt_pass'] ?? $cfg['mqtt']['password']); ?>"></div>
    <div><label>Topic Root</label><input name="topic_root" value="<?php echo htmlspecialchars($s['topic_root'] ?? $cfg['mqtt']['topic_root']); ?>"></div>
    <button type="submit">Simpan</button>
  </form>
</body>

</html>