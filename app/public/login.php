<?php
require_once __DIR__ . '/../src/auth.php';
auth_start();
if (is_logged_in()) header('Location: /');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = $_POST['username'] ?? '';
  $p = $_POST['password'] ?? '';
  if (login($u, $p)) {
    header('Location: /');
    exit;
  } else {
    $error = 'Login gagal';
  }
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - SmartHome Admin</title>
  <link rel="stylesheet" href="/scss/adminlte.css">
  <link rel="stylesheet" href="/scss/adminlte.min.css">
</head>

<body class="hold-transition login-page">
  <div class="login-box">
    <div class="login-logo"><b>SmartHome</b> Admin</div>
    <div class="card">
      <div class="card-body login-card-body">
        <p class="login-box-msg">Masuk untuk memulai sesi</p>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" action="">
          <div class="input-group mb-3">
            <input type="text" name="username" class="form-control" placeholder="Username">
          </div>
          <div class="input-group mb-3">
            <input type="password" name="password" class="form-control" placeholder="Password">
          </div>
          <div class="row">
            <div class="col-8"></div>
            <div class="col-4"><button type="submit" class="btn btn-primary btn-block">Masuk</button></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>

</html>