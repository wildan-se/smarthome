<?php
// Simple MQTT publish using php sockets is non-trivial for the full protocol.
// For now we'll implement a thin wrapper that uses mosquitto_pub if available on system.
function mqtt_publish($topic, $message, $retain = true, $qos = 1)
{
  $cfg = require __DIR__ . '/config.php';
  $host = $cfg['mqtt']['host'];
  $port = $cfg['mqtt']['port'];
  $user = $cfg['mqtt']['username'];
  $pass = $cfg['mqtt']['password'];

  // try using system mosquitto_pub if installed
  $cmd = sprintf(
    'mosquitto_pub -h %s -p %s -t %s -m %s -u %s -P %s -r %d -q %d',
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($topic),
    escapeshellarg($message),
    escapeshellarg($user),
    escapeshellarg($pass),
    $retain ? 1 : 0,
    $qos
  );
  @exec($cmd, $out, $ret);
  return $ret === 0;
}
