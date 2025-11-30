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
      <img src="" alt="Logo" class="brand-image" style="width:35px;height:35px;box-shadow:0 0 15px rgba(56,189,248,0.4);border-radius:50%;margin-right:12px;vertical-align:middle;" onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block';">
      <!-- Fallback Icon -->
      <i class="fas fa-solid fa-house brand-image" style="display:none;font-size:1.5rem;line-height:35px;text-align:center;width:35px;height:35px;margin-right:12px;vertical-align:middle;"></i>
      <span class="brand-text font-weight-bold" style="color:#fff;font-weight:700;font-size:1.1rem;">SMART HOME</span>
    </a>

    <div class="sidebar">
      <!-- User Panel -->
      <div class="user-panel" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:12px;margin:15px 5px 25px 5px;display:flex;align-items:center;">
        <img src="assets/img/avatar.png" class="img-circle elevation-2" alt="User Image" style="width:38px;height:38px;border:2px solid rgba(56,189,248,0.5);" onerror="this.src='https://ui-avatars.com/api/?name=Admin&background=0ea5e9&color=fff'">
        <div class="info text-white" style="padding-left:12px;">
          <a href="#" class="d-block" style="color:#e2e8f0;font-weight:600;font-size:0.9rem;">Administrator</a>
          <small><span class="status-dot" style="display:inline-block;width:8px;height:8px;background-color:#22c55e;border-radius:50%;margin-right:6px;"></span>Online</small>
        </div>
      </div>

      <!-- Navigation Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-header" style="padding:1.5rem 1rem 0.5rem 0.5rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:1px;">MENU UTAMA</li>
          <?php foreach ($menuItems as $key => $item): ?>
            <li class="nav-item">
              <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link <?= $activePage === $key ? 'active' : '' ?>">
                <i class="nav-icon fas fa-<?= htmlspecialchars($item['icon']) ?>" style="width:24px;font-size:1.1rem;text-align:center;margin-right:12px;"></i>
                <p><?= htmlspecialchars($item['text']) ?></p>
              </a>
            </li>
          <?php endforeach; ?>
          <li class="nav-header" style="padding:1.5rem 1rem 0.5rem 0.5rem;font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:1px;">PENGATURAN</li>
          <li class="nav-item">
            <a href="logout.php" class="nav-link bg-danger-soft" style="margin-top:25px;background:rgba(239,68,68,0.1);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);justify-content:center;">
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