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
    if ($hash && password_verify($password, $hash)) {
      $_SESSION['user_id']   = $id;
      $_SESSION['username']  = $db_username;
      header('Location: index.php');
      exit;
    } else {
      $error = 'Password yang kamu masukkan belum tepat.';
    }
  } else {
    $error = 'Username belum terdaftar.';
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

  <!-- Google Font -->
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">

  <style>
    :root {
      --bg-main: #020617;
      /* slate-950 */
      --bg-elevated: #020617;
      --bg-card: #020617;
      --bg-card-inner: #020617;
      --border-soft: #1f2937;
      --border-strong: #38bdf8;
      --primary: #38bdf8;
      /* cyan-400 */
      --primary-soft: rgba(56, 189, 248, 0.12);
      --primary-strong: #0ea5e9;
      /* sky-500 */
      --accent: #a855f7;
      /* violet-500 */
      --text-main: #e5e7eb;
      /* gray-200 */
      --text-muted: #9ca3af;
      /* gray-400 */
      --error-soft: rgba(248, 113, 113, 0.18);
      --error-border: rgba(248, 113, 113, 0.6);
    }

    /* ====== Global / Background ====== */
    body.login-page {
      min-height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--text-main);
      background:
        radial-gradient(circle at 10% 0%, rgba(59, 130, 246, 0.12) 0, transparent 55%),
        radial-gradient(circle at 90% 20%, rgba(236, 72, 153, 0.09) 0, transparent 55%),
        radial-gradient(circle at 0% 80%, rgba(45, 212, 191, 0.09) 0, transparent 55%),
        radial-gradient(circle at 100% 100%, rgba(56, 189, 248, 0.12) 0, transparent 55%),
        radial-gradient(circle at 50% 50%, #020617 0, #020617 40%, #020617 100%);
      position: relative;
      overflow: hidden;
    }

    /* subtle animated grid / scanline feel (optional) */
    body.login-page::before {
      content: "";
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
      background-size: 40px 40px;
      opacity: 0.3;
      pointer-events: none;
      mix-blend-mode: soft-light;
    }

    .login-box {
      width: 430px;
      margin: 0;
      position: relative;
      z-index: 2;
    }

    /* ====== Logo / Title ====== */
    .login-logo {
      margin-bottom: 22px;
      text-align: center;
    }

    .login-logo a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      text-decoration: none;
      color: var(--text-main);
      font-weight: 600;
      font-size: 1.7rem;
      letter-spacing: 0.04em;
    }

    .login-logo-icon {
      width: 46px;
      height: 46px;
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background:
        conic-gradient(from 160deg,
          #22c55e,
          #38bdf8,
          #6366f1,
          #a855f7,
          #22c55e);
      position: relative;
      box-shadow:
        0 0 0 1px rgba(15, 23, 42, 0.7),
        0 16px 40px rgba(8, 47, 73, 0.85);
      overflow: hidden;
    }

    .login-logo-icon::after {
      content: "";
      position: absolute;
      inset: 2px;
      border-radius: inherit;
      background: radial-gradient(circle at 30% 0%, #0f172a 0, #020617 45%, #020617 100%);
    }

    .login-logo-icon i {
      position: relative;
      z-index: 1;
      color: #e5e7eb;
      font-size: 1.2rem;
      text-shadow: 0 0 8px rgba(15, 23, 42, 0.9);
    }

    .login-logo span b {
      color: var(--primary);
      font-weight: 700;
    }

    /* ====== Card ====== */
    .card {
      border-radius: 22px;
      border: 1px solid rgba(15, 23, 42, 0.9);
      background: radial-gradient(circle at 0% 0%, rgba(56, 189, 248, 0.08) 0, transparent 40%),
        radial-gradient(circle at 100% 100%, rgba(168, 85, 247, 0.08) 0, transparent 40%),
        #020617;
      box-shadow:
        0 24px 70px rgba(15, 23, 42, 0.9),
        0 0 0 1px rgba(148, 163, 184, 0.15);
      overflow: hidden;
      backdrop-filter: blur(18px);
    }

    .login-card-body {
      padding: 26px 28px 22px;
      background: radial-gradient(circle at 20% 0%, rgba(15, 23, 42, 0.9), #020617);
    }

    /* ====== Header Text ====== */
    .login-header {
      margin-bottom: 20px;
      text-align: center;
    }

    .login-title {
      font-size: 1.25rem;
      font-weight: 600;
      margin: 0 0 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      color: var(--text-main);
    }

    .login-title-icon {
      width: 26px;
      height: 26px;
      border-radius: 999px;
      background: radial-gradient(circle at 30% 0%, rgba(56, 189, 248, 0.3), rgba(15, 23, 42, 1));
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      color: var(--primary);
      box-shadow: 0 0 16px rgba(56, 189, 248, 0.65);
    }

    .login-subtitle {
      margin: 0;
      font-size: 0.88rem;
      color: var(--text-muted);
    }

    /* ====== Alert ====== */
    .alert {
      border-radius: 14px;
      border: none;
      padding: 10px 13px;
      margin-bottom: 16px;
      font-size: 0.88rem;
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }

    .alert-danger {
      background: var(--error-soft);
      color: #fecaca;
      border: 1px solid var(--error-border);
      box-shadow: 0 12px 30px rgba(127, 29, 29, 0.45);
    }

    .alert-danger i {
      margin-top: 1px;
      font-size: 0.95rem;
      color: #fecaca;
    }

    /* ====== Labels & Inputs ====== */
    .form-label {
      font-size: 0.82rem;
      margin-bottom: 6px;
      font-weight: 500;
      color: var(--text-muted);
    }

    .input-group {
      border-radius: 14px;
      overflow: hidden;
      border: 1px solid var(--border-soft);
      background: radial-gradient(circle at 0% 0%, rgba(15, 23, 42, 0.98), #020617);
      transition: all 0.2s ease;
      box-shadow: 0 0 0 rgba(15, 23, 42, 0);
    }

    .input-group:focus-within {
      border-color: rgba(56, 189, 248, 0.9);
      box-shadow:
        0 0 0 1px rgba(56, 189, 248, 0.35),
        0 12px 32px rgba(8, 47, 73, 0.75);
    }

    .input-group .form-control {
      border: none;
      padding: 11px 14px;
      font-size: 0.93rem;
      background: transparent;
      color: var(--text-main);
      box-shadow: none !important;
    }

    .input-group .form-control::placeholder {
      color: #6b7280;
      font-size: 0.88rem;
    }

    .input-group-text {
      border: none;
      background: transparent;
      padding: 0 10px 0 12px;
      font-size: 0.9rem;
      color: #64748b;
    }

    .input-group-text i {
      opacity: 0.9;
    }

    .btn-toggle-password {
      border: none;
      background: transparent;
      outline: none;
      padding: 0 13px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      color: #64748b;
    }

    .btn-toggle-password:focus {
      outline: none;
    }

    /* ====== Button ====== */
    .btn-login {
      margin-top: 10px;
      background: #0ea5e9;
      /* sky-500 solid */
      border: none;
      color: #e5e7eb;
      padding: 11px 20px;
      font-size: 0.92rem;
      font-weight: 500;
      border-radius: 999px;
      width: 100%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      box-shadow:
        0 0 18px rgba(14, 165, 233, 0.35),
        0 0 0 1px rgba(14, 165, 233, 0.35);
      transition: all 0.18s ease;
    }

    .btn-login:hover {
      background: #38bdf8;
      box-shadow:
        0 0 26px rgba(56, 189, 248, 0.55),
        0 0 0 1px rgba(56, 189, 248, 0.65);
      transform: translateY(-1px);
    }

    .btn-login:active {
      transform: translateY(0);
      box-shadow:
        0 0 16px rgba(56, 189, 248, 0.45),
        0 0 0 1px rgba(56, 189, 248, 0.6);
    }

    .btn-login.loading {
      position: relative;
      pointer-events: none;
      opacity: 0.9;
    }

    .btn-login.loading::after {
      content: "";
      position: absolute;
      width: 16px;
      height: 16px;
      border-radius: 999px;
      border: 2px solid #e5e7eb;
      border-top-color: transparent;
      right: 18px;
      top: 50%;
      margin-top: -8px;
      animation: spinner 0.6s linear infinite;
    }

    @keyframes spinner {
      to {
        transform: rotate(360deg);
      }
    }

    /* ====== Divider & Footer ====== */
    .divider {
      margin: 22px 0 14px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.78rem;
      color: #6b7280;
    }

    .divider-line {
      flex: 1;
      height: 1px;
      background: linear-gradient(to right,
          transparent,
          rgba(30, 64, 175, 0.6),
          transparent);
    }

    .login-footer {
      font-size: 0.78rem;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .footer-left {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .footer-left i {
      font-size: 0.85rem;
      color: var(--primary-strong);
      text-shadow: 0 0 10px rgba(56, 189, 248, 0.8);
    }

    .footer-badge {
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 0.7rem;
      background: rgba(15, 23, 42, 1);
      border: 1px solid rgba(148, 163, 184, 0.6);
      color: #e5e7eb;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .footer-badge-dot {
      width: 7px;
      height: 7px;
      border-radius: 999px;
      background: #22c55e;
      box-shadow: 0 0 12px rgba(34, 197, 94, 0.9);
    }

    /* ====== Responsive ====== */
    @media (max-width: 576px) {
      .login-box {
        width: 92%;
      }

      .login-card-body {
        padding: 22px 19px 20px;
      }

      .login-logo a {
        font-size: 1.5rem;
      }

      .login-logo-icon {
        width: 42px;
        height: 42px;
      }

      .login-footer {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>

<body class="login-page">
  <div class="login-box">

    <!-- Logo -->
    <div class="login-logo">
      <a href="#">
        <div class="login-logo-icon">
          <i class="fas fa-home"></i>
        </div>
        <span><b>Smart</b>Home IoT</span>
      </a>
    </div>

    <!-- Card -->
    <div class="card">
      <div class="card-body login-card-body">

        <!-- Header text -->
        <div class="login-header">
          <h1 class="login-title">
            <span class="login-title-icon">
              <i class="fas fa-shield-alt"></i>
            </span>
            Masuk ke Dashboard
          </h1>
          <p class="login-subtitle">
            Kontrol perangkat dan pantau lingkungan rumah pintarmu secara realtime.
          </p>
        </div>

        <!-- Error Alert -->
        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert">
            <i class="fas fa-circle-exclamation"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
          </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="post" id="loginForm" autocomplete="on">
          <!-- Username -->
          <div class="form-group mb-3">
            <label for="username" class="form-label">Username</label>
            <div class="input-group">
              <input type="text"
                id="username"
                name="username"
                class="form-control"
                placeholder="Masukkan username"
                required
                autofocus
                autocomplete="username">
              <span class="input-group-text">
                <i class="fas fa-user"></i>
              </span>
            </div>
          </div>

          <!-- Password -->
          <div class="form-group mb-1">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
              <input type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="Masukkan password"
                required
                autocomplete="current-password">
              <button type="button" class="btn-toggle-password" aria-label="Tampilkan password">
                <i class="fas fa-eye-slash"></i>
              </button>
            </div>
          </div>

          <!-- Button -->
          <button type="submit" class="btn btn-login">
            <span>Masuk</span>
            <i class="fas fa-arrow-right"></i>
          </button>
        </form>

        <!-- Divider & Footer -->
        <div class="divider">
          <span class="divider-line"></span>
          <span>Smart Home IoT</span>
          <span class="divider-line"></span>
        </div>

        <div class="login-footer">
          <div class="footer-left">
            <i class="fas fa-lock"></i>
            <span>Koneksi aman & terenkripsi</span>
          </div>
          <span class="footer-badge">
            <span class="footer-badge-dot"></span>
            Realtime Monitoring
          </span>
        </div>

      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

  <script>
    // Loading animation on submit
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = this.querySelector('.btn-login');
      btn.classList.add('loading');
      btn.disabled = true;
    });

    // Toggle password visibility
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.btn-toggle-password');
    if (toggleButton && passwordInput) {
      toggleButton.addEventListener('click', function() {
        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        const icon = this.querySelector('i');
        if (icon) {
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        }
      });
    }

    // Auto-hide alert
    <?php if ($error): ?>
      setTimeout(function() {
        const alert = document.querySelector('.alert');
        if (!alert) return;
        alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-4px)';
        setTimeout(function() {
          alert.remove();
        }, 400);
      }, 4500);
    <?php endif; ?>
  </script>
</body>

</html>