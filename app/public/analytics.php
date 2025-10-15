<?php
require_once __DIR__ . '/../src/auth.php';
require_login();
require_once __DIR__ . '/../src/db.php';

// Daily average temperature (last 7 days)
$avgRows = db_query("SELECT DATE(ts) as d, AVG(temperature) as avg_temp, AVG(humidity) as avg_hum FROM sensor_logs GROUP BY DATE(ts) ORDER BY DATE(ts) DESC LIMIT 7")->fetchAll();

// Extreme temps (top 10 highest and lowest)
$highs = db_query('SELECT * FROM sensor_logs WHERE temperature IS NOT NULL ORDER BY temperature DESC LIMIT 10')->fetchAll();
$lows = db_query('SELECT * FROM sensor_logs WHERE temperature IS NOT NULL ORDER BY temperature ASC LIMIT 10')->fetchAll();

?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analytics</title>
</head>

<body>
  <h1>Analitik Sensor</h1>
  <h2>Rata-rata Harian (7 hari terakhir)</h2>
  <table border="1">
    <tr>
      <th>Tanggal</th>
      <th>Avg Suhu (°C)</th>
      <th>Avg Kelembapan (%)</th>
    </tr>
    <?php foreach ($avgRows as $r): ?>
      <tr>
        <td><?php echo htmlspecialchars($r['d']); ?></td>
        <td><?php echo htmlspecialchars(number_format($r['avg_temp'], 2)); ?></td>
        <td><?php echo htmlspecialchars(number_format($r['avg_hum'], 2)); ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Temperatur Tertinggi (Top 10)</h2>
  <table border="1">
    <tr>
      <th>id</th>
      <th>temp</th>
      <th>hum</th>
      <th>ts</th>
    </tr>
    <?php foreach ($highs as $r): ?>
      <tr>
        <td><?php echo $r['id']; ?></td>
        <td><?php echo $r['temperature']; ?></td>
        <td><?php echo $r['humidity']; ?></td>
        <td><?php echo $r['ts']; ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Temperatur Terendah (Top 10)</h2>
  <table border="1">
    <tr>
      <th>id</th>
      <th>temp</th>
      <th>hum</th>
      <th>ts</th>
    </tr>
    <?php foreach ($lows as $r): ?>
      <tr>
        <td><?php echo $r['id']; ?></td>
        <td><?php echo $r['temperature']; ?></td>
        <td><?php echo $r['humidity']; ?></td>
        <td><?php echo $r['ts']; ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <p><a href="/export.php?type=sensor">Export Sensor CSV</a> | <a href="/export.php?type=rfid">Export RFID CSV</a></p>
</body>

</html>