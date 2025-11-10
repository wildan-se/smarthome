<?php

/**
 * Render Footer Section with Scripts
 * 
 * @param array $pageJS Additional JavaScript files
 * @param array $mqttConfig MQTT configuration
 */
function renderFooter($pageJS = [], $mqttConfig = null)
{
?>
  <footer class="main-footer">
    <strong>Copyright &copy; <?= date('Y') ?> <a href="index.php">Koneksi Pintar</a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 2.0.0
    </div>
  </footer>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Bootstrap 4 -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- AdminLTE App -->
  <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- MQTT.js -->
  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>

  <!-- Sidebar Collapse Fix - MUST load after AdminLTE -->
  <script src="assets/js/sidebar-fix.js"></script>

  <!-- Global MQTT Config -->
  <?php if ($mqttConfig): ?>
    <script>
      window.mqttConfig = <?= json_encode($mqttConfig) ?>;
    </script>
  <?php endif; ?>

  <!-- Global Utils -->
  <script src="assets/js/utils.js"></script>
  <script src="assets/js/components/alert.js"></script>

  <!-- Page Specific Scripts -->
  <?php foreach ($pageJS as $js): ?>
    <script src="<?= htmlspecialchars($js) ?>"></script>
  <?php endforeach; ?>

  <!-- Real-time Clock -->
  <script>
    function updateClock() {
      const now = new Date();
      const options = {
        timeZone: 'Asia/Jakarta',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      };
      const formattedTime = now.toLocaleString('id-ID', options);
      $('#current-time').text(formattedTime);
    }
    updateClock();
    setInterval(updateClock, 1000);
  </script>
<?php
}
?>