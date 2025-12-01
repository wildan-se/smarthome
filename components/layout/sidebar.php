<?php

/**
 * Render Sidebar Navigation
 */

function renderSidebar($activePage = '')
{
  $menuItems = [
    'dashboard' => ['icon' => 'tachometer-alt', 'text' => 'Dashboard', 'url' => 'index.php'],
    'rfid'      => ['icon' => 'id-card', 'text' => 'Manajemen RFID', 'url' => 'rfid.php'],
    'kipas'     => ['icon' => 'fan', 'text' => 'Kontrol Kipas', 'url' => 'kipas.php'],
    'kontrol'   => ['icon' => 'door-open', 'text' => 'Kontrol Pintu', 'url' => 'kontrol.php'],
    'log'       => ['icon' => 'clipboard-list', 'text' => 'Log Aktivitas', 'url' => 'log.php'],
    'export'    => ['icon' => 'file-export', 'text' => 'Export Data', 'url' => 'export.php'],
    'wifi'      => ['icon' => 'wifi', 'text' => 'Konfigurasi WiFi', 'url' => 'wifi.php']
  ];
?>
  <aside class="main-sidebar elevation-4">
    <!-- Brand Logo -->
    <a href="index.php" class="brand-link">
      <img src="" alt="Logo" class="brand-image" style="width:32px;height:32px;" onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block';">
      <!-- Fallback Icon -->
      <i class="fas fa-solid fa-house brand-image" style="display:none;font-size:1.3rem;"></i>
      <span class="brand-text font-weight-bold">SMART HOME</span>
    </a>

    <div class="sidebar">


      <!-- Navigation Menu -->
      <nav style="margin-top: 0 !important;">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-header">MENU UTAMA</li>
          <?php foreach ($menuItems as $key => $item): ?>
            <li class="nav-item">
              <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link <?= $activePage === $key ? 'active' : '' ?>">
                <i class="nav-icon fas fa-<?= htmlspecialchars($item['icon']) ?>"></i>
                <p><?= htmlspecialchars($item['text']) ?></p>
              </a>
            </li>
          <?php endforeach; ?>
          <li class="nav-header">PENGATURAN</li>
          <li class="nav-item">
            <a href="logout.php" class="nav-link bg-danger-soft">
              <i class="nav-icon fas fa-sign-out-alt"></i>
              <p>Keluar Sistem</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php
}
?>