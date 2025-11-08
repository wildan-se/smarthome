<?php

/**
 * Render Top Navigation Bar
 */
function renderHeader()
{
  require_once __DIR__ . '/../../core/Auth.php';
  $username = Auth::getUsername();
?>
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button">
          <i class="fas fa-bars"></i>
        </a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="index.php" class="nav-link">
          <i class="fas fa-home"></i> Home
        </a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Real-time Clock -->
      <li class="nav-item">
        <span class="nav-link">
          <i class="far fa-clock"></i>
          <span id="current-time"></span>
        </span>
      </li>

      <!-- User Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-user"></i> <?= htmlspecialchars($username) ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <a href="logout.php" class="dropdown-item">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
          </a>
        </div>
      </li>
    </ul>
  </nav>
<?php
}
?>