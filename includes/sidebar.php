<?php
/**
 * ShawirIOT - Sidebar Include
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$user_s = currentUser();
function navItem(string $href, string $icon, string $label, string $current, string $page): string {
    $active = ($current === $page) ? ' active' : '';
    return "<a href=\"{$href}\" class=\"nav-item{$active}\">
      <span class=\"nav-icon\"><i class=\"fas fa-{$icon}\"></i></span>
      <span>{$label}</span>
    </a>";
}
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><i class="fas fa-wifi"></i></div>
    <div class="logo-text"><?= getSetting('platform_name', 'ShawirIOT') ?></div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Utama</div>
    <?= navItem('dashboard.php', 'home', 'Beranda', $currentPage, 'dashboard.php') ?>
    <?= navItem('device.php', 'microchip', 'Device Saya', $currentPage, 'device.php') ?>
    <?= navItem('profile.php', 'user-circle', 'Profil & Kredit', $currentPage, 'profile.php') ?>

    <?php if (isAdmin()): ?>
    <div class="nav-section-label" style="margin-top:1rem">Admin</div>
    <?= navItem('admin/', 'chart-pie', 'Dashboard Admin', $currentPage, 'index.php') ?>
    <?= navItem('admin/users.php', 'users', 'Manajemen User', $currentPage, 'users.php') ?>
    <?= navItem('admin/credits.php', 'coins', 'Kelola Kredit', $currentPage, 'credits.php') ?>
    <?= navItem('admin/devices.php', 'server', 'Monitor Device', $currentPage, 'devices.php') ?>
    <?= navItem('admin/settings.php', 'cog', 'Pengaturan', $currentPage, 'settings.php') ?>
    <?php endif; ?>

    <div class="nav-section-label" style="margin-top:1rem">Lainnya</div>
    <a href="#" class="nav-item" onclick="event.preventDefault();window.open('https://github.com','_blank')">
      <span class="nav-icon"><i class="fas fa-book"></i></span>
      <span>Dokumentasi</span>
    </a>
    <a href="logout.php" class="nav-item" style="color:var(--danger)">
      <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
      <span>Keluar</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">
        <?php if ($user_s['avatar']): ?>
          <img src="<?= sanitize($user_s['avatar']) ?>" alt="">
        <?php else: ?>
          <?= strtoupper(substr($user_s['name'], 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <div class="name"><?= sanitize($user_s['name']) ?></div>
        <div class="role"><?= $user_s['plan_name'] ?? 'Free' ?> · <?= number_format($user_s['credits']) ?> kredit</div>
      </div>
    </div>
  </div>
</aside>
