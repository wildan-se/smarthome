<?php
session_start();
require_once 'config/config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $stmt = $conn->prepare('SELECT id, password FROM users WHERE username = ?');
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $hash);
    $stmt->fetch();
    if (password_verify($password, $hash)) {
      $_SESSION['user_id'] = $id;
      header('Location: index.php');
      exit;
    } else {
      $error = 'Password salah.';
    }
  } else {
    $error = 'Username tidak ditemukan.';
  }
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">


<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Login Admin Smarthome</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- AdminLTE CSS CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body.login-page {
      background: linear-gradient(135deg, #007bff 0%, #00c6ff 100%);
      min-height: 100vh;
    }

    .login-box {
      margin-top: 6vh;
    }

    .login-logo img {
      width: 60px;
      margin-bottom: 10px;
    }

    .card {
      box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
      border-radius: 16px;
    }

    .login-card-body {
      border-radius: 16px;
      background: #fff;
    }

    .btn-primary {
      background: linear-gradient(90deg, #007bff 0%, #00c6ff 100%);
      border: none;
    }

    .login-box-msg {
      font-size: 1.1rem;
      color: #007bff;
      font-weight: 500;
    }
  </style>
</head>

<body class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <img src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/img/AdminLTELogo.png" alt="Logo">
      <b>Smarthome</b> Admin
    </div>
    <div class="card">
      <div class="card-body login-card-body">
        <p class="login-box-msg">Login untuk akses dashboard</p>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <form method="post">
          <div class="input-group mb-3">
            <input type="text" name="username" class="form-control" placeholder="Username" required autofocus>
            <span class="input-group-text"><i class="fas fa-user"></i></span>
          </div>
          <div class="input-group mb-3">
            <input type="password" name="password" class="form-control" placeholder="Password" required>
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
          </div>
          <div class="row mb-2">
            <div class="col-7">
              <a href="reset_password.php">Lupa password?</a>
            </div>
            <div class="col-5 text-end">
              <button type="submit" class="btn btn-primary btn-block w-100">Login <i class="fas fa-sign-in-alt"></i></button>
            </div>
          </div>
        </form>
        <hr>
        <div class="text-center text-muted" style="font-size:0.95rem;">
          &copy; <?php echo date('Y'); ?> Smarthome IoT | Powered by AdminLTE
        </div>
      </div>
    </div>
  </div>
</body>

</html>