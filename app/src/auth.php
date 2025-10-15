<?php
require_once __DIR__ . '/db.php';

function auth_start()
{
  if (session_status() === PHP_SESSION_NONE) {
    $c = require __DIR__ . '/config.php';
    session_name($c['session']['name']);
    session_start();
    // session timeout
    if (!empty($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $c['session']['timeout'])) {
      session_unset();
      session_destroy();
      session_start();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
  }
}

function is_logged_in()
{
  auth_start();
  return !empty($_SESSION['user_id']);
}

function require_login()
{
  if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
  }
}

function login($username, $password)
{
  $row = db_query('SELECT id, username, password_hash FROM users WHERE username = :u', ['u' => $username])->fetch();
  if (!$row) return false;
  if (password_verify($password, $row['password_hash'])) {
    auth_start();
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $row['username'];
    return true;
  }
  return false;
}

function logout()
{
  auth_start();
  session_unset();
  session_destroy();
}
