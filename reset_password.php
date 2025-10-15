<?php
require_once 'config/config.php';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? '';
  $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) {
    $token = bin2hex(random_bytes(32));
    $stmt->bind_result($id);
    $stmt->fetch();
    $stmt->close();
    $stmt2 = $conn->prepare('UPDATE users SET reset_token = ? WHERE id = ?');
    $stmt2->bind_param('si', $token, $id);
    $stmt2->execute();
    $stmt2->close();
    // Kirim email (dummy)
    $message = "Link reset password: <a href='reset_password.php?token=$token'>Reset Password</a>";
  } else {
    $message = 'Email tidak ditemukan.';
  }
}
if (isset($_GET['token'])) {
  $token = $_GET['token'];
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?');
    $stmt->bind_param('ss', $new_password, $token);
    $stmt->execute();
    $stmt->close();
    $message = 'Password berhasil direset. Silakan login.';
  }
  echo "<form method='post'><input type='password' name='new_password' placeholder='Password baru' required><button type='submit'>Reset</button></form>";
  if ($message) echo "<div>$message</div>";
  exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Reset Password Admin</title>
  <link rel="stylesheet" href="src/scss/adminlte.scss">
</head>

<body class="login-page">
  <div class="login-box">
    <div class="login-logo"><b>Reset</b> Password</div>
    <div class="card">
      <div class="card-body login-card-body">
        <form method="post">
          <div class="input-group mb-3">
            <input type="email" name="email" class="form-control" placeholder="Email admin" required>
            <div class="input-group-append">
              <div class="input-group-text"><span class="fas fa-envelope"></span></div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Kirim Link Reset</button>
        </form>
        <?php if ($message) echo "<div class='mt-3'>$message</div>"; ?>
      </div>
    </div>
  </div>
</body>

</html>