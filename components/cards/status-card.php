<?php

/**
 * Render Status Card (Small Box)
 * 
 * @param array $config Card configuration
 */
function renderStatusCard($config)
{
  $defaults = [
    'id' => '',
    'title' => '',
    'value' => '0',
    'icon' => 'info-circle',
    'color' => 'info',
    'footer' => '',
    'link' => '#',
    'class' => ''
  ];

  $data = array_merge($defaults, $config);
?>
  <div class="small-box bg-<?= htmlspecialchars($data['color']) ?> <?= htmlspecialchars($data['class']) ?>"
    id="<?= htmlspecialchars($data['id']) ?>">
    <div class="inner">
      <h3><?= htmlspecialchars($data['value']) ?></h3>
      <p><?= htmlspecialchars($data['title']) ?></p>
    </div>
    <div class="icon">
      <i class="fas fa-<?= htmlspecialchars($data['icon']) ?>"></i>
    </div>
    <?php if ($data['footer']): ?>
      <a href="<?= htmlspecialchars($data['link']) ?>" class="small-box-footer">
        <?= htmlspecialchars($data['footer']) ?> <i class="fas fa-arrow-circle-right"></i>
      </a>
    <?php endif; ?>
  </div>
<?php
}

/**
 * Render Info Box
 * 
 * @param array $config Info box configuration
 */
function renderInfoBox($config)
{
  $defaults = [
    'icon' => 'info-circle',
    'iconColor' => 'info',
    'text' => '',
    'number' => '0',
    'class' => ''
  ];

  $data = array_merge($defaults, $config);
?>
  <div class="info-box <?= htmlspecialchars($data['class']) ?>">
    <span class="info-box-icon bg-<?= htmlspecialchars($data['iconColor']) ?>">
      <i class="fas fa-<?= htmlspecialchars($data['icon']) ?>"></i>
    </span>
    <div class="info-box-content">
      <span class="info-box-text"><?= htmlspecialchars($data['text']) ?></span>
      <span class="info-box-number"><?= htmlspecialchars($data['number']) ?></span>
    </div>
  </div>
<?php
}
?>