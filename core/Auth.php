<?php

/**
 * Authentication Class
 * Handle user authentication and session
 */
class Auth
{
  /**
   * Check if user is logged in
   * Redirect to login if not authenticated
   */
  public static function check()
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    if (!isset($_SESSION['user_id'])) {
      header('Location: login.php');
      exit;
    }
  }

  /**
   * Check if user is logged in (return boolean)
   */
  public static function isLoggedIn()
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    return isset($_SESSION['user_id']);
  }

  /**
   * Get current user ID
   */
  public static function getUserId()
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    return $_SESSION['user_id'] ?? null;
  }

  /**
   * Get current username
   */
  public static function getUsername()
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    return $_SESSION['username'] ?? 'Guest';
  }

  /**
   * Login user
   */
  public static function login($userId, $username)
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
  }

  /**
   * Logout user
   */
  public static function logout()
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    session_unset();
    session_destroy();
  }
}
