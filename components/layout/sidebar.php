<?php

/**
 * Render Sidebar Navigation
 * 
 * @param string $activePage Current active page
 */
function renderSidebar($activePage = '')
{
  $menuItems = [
    'dashboard' => [
      'icon' => 'tachometer-alt',
      'text' => 'Dashboard',
      'url' => 'index.php'
    ],
    'rfid' => [
      'icon' => 'id-card',
      'text' => 'Manajemen RFID',
      'url' => 'rfid.php'
    ],
    'kipas' => [
      'icon' => 'fan',
      'text' => 'Kontrol Kipas',
      'url' => 'kipas.php'
    ],
    'kontrol' => [
      'icon' => 'door-open',
      'text' => 'Kontrol Pintu',
      'url' => 'kontrol.php'
    ],
    'log' => [
      'icon' => 'clipboard-list',
      'text' => 'Log Aktivitas',
      'url' => 'log.php'
    ],
    'export' => [
      'icon' => 'file-export',
      'text' => 'Export Data',
      'url' => 'export.php'
    ]
  ];
?>
  <aside class="main-sidebar sidebar-dark-primary elevation-4 sidebar-no-expand">
    <!-- Brand Logo -->
    <a href="index.php" class="brand-link">
      <i class="fas fa-home brand-image" style="font-size: 2rem; opacity: .8; margin-left: 0.8rem;"></i>
      <span class="brand-text font-weight-bold">Smart Home</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar Menu -->
      <nav class="mt-3">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

          <?php foreach ($menuItems as $key => $item): ?>
            <li class="nav-item">
              <a href="<?= htmlspecialchars($item['url']) ?>"
                class="nav-link <?= $activePage === $key ? 'active' : '' ?>">
                <i class="nav-icon fas fa-<?= htmlspecialchars($item['icon']) ?>"></i>
                <p><?= htmlspecialchars($item['text']) ?></p>
              </a>
            </li>
          <?php endforeach; ?>

          <!-- Separator -->
          <li class="nav-header">ACCOUNT</li>

          <!-- Logout -->
          <li class="nav-item">
            <a href="logout.php" class="nav-link">
              <i class="nav-icon fas fa-sign-out-alt text-danger"></i>
              <p>Logout</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>
<?php
}
?>