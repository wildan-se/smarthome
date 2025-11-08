<?php

/**
 * Database Connection Class
 * Singleton pattern untuk koneksi database
 */
class Database
{
  private static $instance = null;
  private $conn;

  private function __construct()
  {
    require_once __DIR__ . '/../config/config.php';
    global $conn;
    $this->conn = $conn;
  }

  public static function getInstance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function getConnection()
  {
    return $this->conn;
  }

  public function query($sql)
  {
    return $this->conn->query($sql);
  }

  public function prepare($sql)
  {
    return $this->conn->prepare($sql);
  }

  public function escape($string)
  {
    return $this->conn->real_escape_string($string);
  }

  public function getLastInsertId()
  {
    return $this->conn->insert_id;
  }

  public function getAffectedRows()
  {
    return $this->conn->affected_rows;
  }
}
