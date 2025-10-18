<?php

/**
 * Auto-Cleanup Old Logs Script
 * Menghapus data log yang lebih dari 30 hari untuk optimasi database
 * 
 * Usage:
 * 1. Manual: php cleanup_old_logs.php
 * 2. Cron Job (Linux): 0 2 * * * /usr/bin/php /path/to/cleanup_old_logs.php
 * 3. Task Scheduler (Windows): Run daily at 2:00 AM
 */

require_once 'config/config.php';

// Configuration
$RETENTION_DAYS = 30; // Keep data for 30 days
$DRY_RUN = false; // Set to true for testing (won't delete)

echo "========================================\n";
echo "Smart Home Log Cleanup Script\n";
echo "========================================\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "Retention: $RETENTION_DAYS days\n";
echo "Mode: " . ($DRY_RUN ? "DRY RUN (No deletion)" : "LIVE") . "\n";
echo "========================================\n\n";

// Function to cleanup table
function cleanupTable($conn, $tableName, $dateColumn, $retentionDays, $dryRun)
{
  echo "📋 Processing table: $tableName\n";

  // Check if table exists
  $checkTableSql = "SHOW TABLES LIKE '$tableName'";
  $result = $conn->query($checkTableSql);
  if ($result->num_rows == 0) {
    echo "   ⚠️  Table does not exist, skipping...\n\n";
    return;
  }

  // Count total records
  $countSql = "SELECT COUNT(*) as total FROM $tableName";
  $result = $conn->query($countSql);
  $totalRecords = $result->fetch_assoc()['total'];
  echo "   Total records: " . number_format($totalRecords) . "\n";

  // Count old records
  $oldCountSql = "SELECT COUNT(*) as old_count 
                    FROM $tableName 
                    WHERE $dateColumn < DATE_SUB(NOW(), INTERVAL $retentionDays DAY)";
  $result = $conn->query($oldCountSql);
  $oldRecords = $result->fetch_assoc()['old_count'];
  echo "   Old records (>$retentionDays days): " . number_format($oldRecords) . "\n";

  if ($oldRecords > 0) {
    if (!$dryRun) {
      // Delete old records
      $deleteSql = "DELETE FROM $tableName 
                          WHERE $dateColumn < DATE_SUB(NOW(), INTERVAL $retentionDays DAY)";

      if ($conn->query($deleteSql)) {
        $affected = $conn->affected_rows;
        echo "   ✅ Deleted: " . number_format($affected) . " records\n";

        // Optimize table after deletion
        $conn->query("OPTIMIZE TABLE $tableName");
        echo "   ✅ Table optimized\n";
      } else {
        echo "   ❌ Error: " . $conn->error . "\n";
      }
    } else {
      echo "   ⚠️  DRY RUN: Would delete " . number_format($oldRecords) . " records\n";
    }
  } else {
    echo "   ✅ No old records to delete\n";
  }

  // Show remaining records
  $result = $conn->query($countSql);
  $remainingRecords = $result->fetch_assoc()['total'];
  echo "   Remaining records: " . number_format($remainingRecords) . "\n";

  // Calculate database size
  $sizeSql = "SELECT 
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '$tableName'";
  $result = $conn->query($sizeSql);
  if ($result && $result->num_rows > 0) {
    $sizeMb = $result->fetch_assoc()['size_mb'];
    echo "   Table size: {$sizeMb} MB\n";
  }

  echo "\n";
}

// Cleanup each table
echo "🗑️  Starting cleanup process...\n\n";

// 1. DHT Logs
cleanupTable($conn, 'dht_logs', 'log_time', $RETENTION_DAYS, $DRY_RUN);

// 2. Door Logs
cleanupTable($conn, 'door_logs', 'log_time', $RETENTION_DAYS, $DRY_RUN);

// 3. RFID Logs
cleanupTable($conn, 'rfid_logs', 'access_time', $RETENTION_DAYS, $DRY_RUN);

// Summary
echo "========================================\n";
echo "Cleanup completed!\n";
echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

// Calculate total database size
$dbSizeSql = "SELECT 
                ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
              FROM information_schema.TABLES 
              WHERE TABLE_SCHEMA = DATABASE()";
$result = $conn->query($dbSizeSql);
if ($result && $result->num_rows > 0) {
  $totalSizeMb = $result->fetch_assoc()['size_mb'];
  echo "\n📊 Total Database Size: {$totalSizeMb} MB\n";
}

$conn->close();

// If running from web (for testing)
if (php_sapi_name() !== 'cli') {
  echo "\n<br><a href='log.php'>← Back to Logs</a>";
}
