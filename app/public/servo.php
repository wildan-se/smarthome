<?php
require_once __DIR__ . '/../src/auth.php';
require_login();
require_once __DIR__ . '/../src/mqtt_publish.php';
$cfg = require __DIR__ . '/../src/config.php';
$topicRoot = $cfg['mqtt']['topic_root'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_POST['action']) && $_POST['action'] === 'open') {
    mqtt_publish($topicRoot . '/servo', '90');
  }
  if (!empty($_POST['action']) && $_POST['action'] === 'close') {
    mqtt_publish($topicRoot . '/servo', '0');
  }
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kontrol Servo</title>
</head>

<body>
  <h1>Kontrol Pintu (Servo)</h1>
  <form method="post"><button name="action" value="open">Buka Pintu (90°)</button> <button name="action" value="close">Tutup Pintu (0°)</button></form>
</body>

</html>