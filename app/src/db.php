<?php
$config = require __DIR__ . '/config.php';

function get_pdo()
{
  static $pdo = null;
  if ($pdo) return $pdo;
  $c = require __DIR__ . '/config.php';
  $db = $c['db'];
  $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']);
  $pdo = new PDO($dsn, $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

function db_query($sql, $params = [])
{
  $stmt = get_pdo()->prepare($sql);
  $stmt->execute($params);
  return $stmt;
}
