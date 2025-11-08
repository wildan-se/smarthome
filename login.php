<?php
session_start();
require_once 'config/config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $stmt = $conn->prepare('SELECT id, username, password FROM users WHERE username = ?');
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $db_username, $hash);
    $stmt->fetch();
    if (password_verify($password, $hash)) {
      $_SESSION['user_id'] = $id;
      $_SESSION['username'] = $db_username;
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

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Smart Home IoT Dashboard</title>

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2917/2917995.png">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- AdminLTE CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">

  <style>
    /* ========== Background Gradient ========== */
    body.login-page {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Source Sans Pro', sans-serif;
    }

    /* ========== Login Box ========== */
    .login-box {
      width: 420px;
      margin: 0;
    }

    /* ========== Logo Area ========== */
    .login-logo {
      margin-bottom: 25px;
      text-align: center;
    }

    .login-logo a {
      color: #ffffff;
      text-decoration: none;
      font-size: 2rem;
      font-weight: 600;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }

    .login-logo i {
      font-size: 2.5rem;
      background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    }

    /* ========== Card Styling ========== */
    .card {
      border: none;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
      border-radius: 20px;
      overflow: hidden;
      backdrop-filter: blur(10px);
    }

    .login-card-body {
      padding: 35px 30px;
      background: rgba(255, 255, 255, 0.98);
      border-radius: 20px;
    }

    /* ========== Welcome Message ========== */
    .login-box-msg {
      text-align: center;
      margin-bottom: 25px;
      font-size: 1.15rem;
      color: #495057;
      font-weight: 500;
    }

    .welcome-subtitle {
      text-align: center;
      color: #6c757d;
      font-size: 0.9rem;
      margin-top: -15px;
      margin-bottom: 25px;
    }

    /* ========== Input Groups ========== */
    .input-group {
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
    }

    .input-group:focus-within {
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
      transform: translateY(-2px);
    }

    .input-group .form-control {
      border: 2px solid #e3e6f0;
      padding: 12px 15px;
      font-size: 0.95rem;
      border-radius: 10px 0 0 10px;
      border-right: none;
      transition: all 0.3s ease;
    }

    .input-group .form-control:focus {
      border-color: #667eea;
      box-shadow: none;
    }

    .input-group-text {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: 2px solid #667eea;
      border-left: none;
      color: white;
      padding: 12px 18px;
      border-radius: 0 10px 10px 0;
    }

    .input-group-text i {
      color: #ffffff;
      opacity: 1;
    }

    /* ========== Forgot Password Link ========== */
    .forgot-password {
      text-align: center;
      margin-bottom: 20px;
    }

    .forgot-password a {
      color: #667eea;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .forgot-password a:hover {
      color: #764ba2;
      text-decoration: underline;
    }

    .forgot-password i {
      margin-right: 5px;
    }

    /* ========== Button Styling ========== */
    .btn-login {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      color: white;
      padding: 12px 25px;
      font-size: 1rem;
      font-weight: 600;
      border-radius: 10px;
      width: 100%;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-login:hover {
      background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
      box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
      transform: translateY(-2px);
    }

    .btn-login:active {
      transform: translateY(0);
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }

    .btn-login i {
      margin-left: 8px;
    }

    /* ========== Alert Styling ========== */
    .alert {
      border-radius: 12px;
      border: none;
      padding: 14px 18px;
      margin-bottom: 20px;
      font-size: 0.95rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .alert-danger {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
      color: white;
    }

    .alert-danger i {
      margin-right: 8px;
    }

    /* ========== Divider ========== */
    hr {
      margin: 25px 0;
      border-top: 1px solid #e3e6f0;
    }

    /* ========== Footer ========== */
    .login-footer {
      text-align: center;
      color: #6c757d;
      font-size: 0.9rem;
      margin-top: 15px;
    }

    .login-footer i {
      color: #667eea;
      margin: 0 5px;
    }

    /* ========== Extra Info ========== */
    .info-box {
      background: linear-gradient(135deg, #f6f9fc 0%, #e9ecef 100%);
      padding: 15px;
      border-radius: 12px;
      margin-top: 20px;
      text-align: center;
    }

    .info-box p {
      margin: 0;
      color: #495057;
      font-size: 0.85rem;
    }

    .info-box .badge {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 5px 12px;
      border-radius: 20px;
      font-weight: 500;
      margin-top: 8px;
      display: inline-block;
    }

    /* ========== Responsive ========== */
    @media (max-width: 576px) {
      .login-box {
        width: 90%;
        margin: 20px auto;
      }

      .login-card-body {
        padding: 25px 20px;
      }

      .login-logo a {
        font-size: 1.6rem;
      }

      .login-logo i {
        font-size: 2rem;
      }
    }

    /* ========== Loading Animation ========== */
    .btn-login.loading {
      position: relative;
      pointer-events: none;
    }

    .btn-login.loading::after {
      content: "";
      position: absolute;
      width: 16px;
      height: 16px;
      top: 50%;
      right: 20px;
      margin-top: -8px;
      border: 2px solid white;
      border-radius: 50%;
      border-top-color: transparent;
      animation: spinner 0.6s linear infinite;
    }

    @keyframes spinner {
      to {
        transform: rotate(360deg);
      }
    }
  </style>
</head>

<body class="login-page">
  <div class="login-box">
    <!-- Logo -->
    <div class="login-logo">
      <a href="#">
        <i class="fas fa-home"></i>
        <span><b>Smart</b>Home</span>
        <span><b>I</b>Home</span>
      </a>
    </div>
    <!-- /.login-logo -->

    <!-- Card -->
    <div class="card">
      <div class="card-body login-card-body">
        <p class="login-box-msg">
          <i class="fas fa-shield-alt" style="color: #667eea;"></i>
          Selamat Datang Kembali
        </p>
        <p class="welcome-subtitle">Masuk ke dashboard untuk mengelola smart home Anda</p>

        <!-- Error Alert -->
        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="post" id="loginForm">
          <div class="input-group mb-3">
            <input type="text"
              name="username"
              class="form-control"
              placeholder="Username"
              required
              autofocus
              autocomplete="username">
            <span class="input-group-text">
              <i class="fas fa-user"></i>
            </span>
          </div>

          <div class="input-group mb-3">
            <input type="password"
              name="password"
              class="form-control"
              placeholder="Password"
              required
              autocomplete="current-password">
            <span class="input-group-text">
              <i class="fas fa-lock"></i>
            </span>
          </div>



          <!-- Login Button -->
          <button type="submit" class="btn btn-login">
            Login
            <i class="fas fa-arrow-right"></i>
          </button>
        </form>

        <hr>

        <!-- Footer Info -->
        <div class="login-footer">
          <i class="fas fa-lock"></i>
          Koneksi Aman & Terenkripsi
        </div>



      </div>
      <!-- /.login-card-body -->
    </div>
    <!-- /.card -->
  </div>
  <!-- /.login-box -->

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Bootstrap 4 -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- AdminLTE App -->
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

  <script>
    // Loading animation on submit
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const btn = this.querySelector('.btn-login');
      btn.classList.add('loading');
      btn.disabled = true;
    });

    // Auto-hide alert after 5 seconds
    <?php if ($error): ?>
      setTimeout(function() {
        const alert = document.querySelector('.alert');
        if (alert) {
          alert.style.transition = 'opacity 0.5s ease';
          alert.style.opacity = '0';
          setTimeout(function() {
            alert.remove();
          }, 500);
        }
      }, 5000);
    <?php endif; ?>
  </script>
</body>

</html>