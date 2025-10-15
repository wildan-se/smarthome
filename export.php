<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Export Data Smarthome</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-arrow-left"></i> Dashboard</a></li>
      </ul>
    </nav>
    <div class="content-wrapper p-4">
      <section class="content-header">
        <h1>Export Data Smarthome</h1>
      </section>
      <section class="content">
        <div class="card">
          <div class="card-body">
            <a href="api/export_excel.php" class="btn btn-success"><i class="fas fa-file-excel"></i> Export ke Excel</a>
            <a href="api/export_pdf.php" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Export ke PDF</a>
          </div>
        </div>
      </section>
    </div>
  </div>
</body>

</html>