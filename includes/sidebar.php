<?php
/**
 * ShawirIOT - Sidebar Include
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$user_s = currentUser();
$baseUrl = defined('PLATFORM_URL') ? PLATFORM_URL : '';

function navItem(string $href, string $icon, string $label, string $current, string $page): string {
    $active = ($current === $page) ? ' active' : '';
    return "<a href=\"{$href}\" class=\"nav-item{$active}\">
      <span class=\"nav-icon\"><i class=\"fas fa-{$icon}\"></i></span>
      <span>{$label}</span>
    </a>";
}
?>
<!-- SIDEBAR BACKDROP FOR MOBILE -->
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar(false)"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><i class="fas fa-wifi"></i></div>
    <div class="logo-text"><?= getSetting('platform_name', 'ShawirIOT') ?></div>
    <button type="button" class="sidebar-close-btn" onclick="toggleSidebar(false)" aria-label="Close sidebar">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Utama</div>
    <?= navItem($baseUrl . '/dashboard.php', 'home', 'Beranda', $currentPage, 'dashboard.php') ?>
    <?= navItem($baseUrl . '/device.php', 'microchip', 'Perangkat Saya', $currentPage, 'device.php') ?>
    <?= navItem($baseUrl . '/profile.php', 'user-circle', 'Profil & Kredit', $currentPage, 'profile.php') ?>

    <?php if (isAdmin()): ?>
    <div class="nav-section-label" style="margin-top:1rem">Admin</div>
    <?= navItem($baseUrl . '/admin/index.php', 'chart-pie', 'Dashboard Admin', $currentPage, 'index.php') ?>
    <?= navItem($baseUrl . '/admin/users.php', 'users', 'Manajemen Pengguna', $currentPage, 'users.php') ?>
    <?= navItem($baseUrl . '/admin/credits.php', 'coins', 'Kelola Kredit', $currentPage, 'credits.php') ?>
    <?= navItem($baseUrl . '/admin/devices.php', 'server', 'Monitor Perangkat', $currentPage, 'devices.php') ?>
    <?= navItem($baseUrl . '/admin/settings.php', 'cog', 'Pengaturan Platform', $currentPage, 'settings.php') ?>
    <?php endif; ?>

    <div class="nav-section-label" style="margin-top:1rem">Lainnya</div>
    <a href="#" class="nav-item" onclick="event.preventDefault();window.open('https://github.com/Shawir1312/IOT-SHAWIR','_blank')">
      <span class="nav-icon"><i class="fas fa-book"></i></span>
      <span>Dokumentasi</span>
    </a>
    <a href="<?= $baseUrl ?>/logout.php" class="nav-item" style="color:var(--danger)">
      <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
      <span>Keluar</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">
        <?php if (!empty($user_s['avatar'])): ?>
          <img src="<?= sanitize($user_s['avatar']) ?>" alt="">
        <?php else: ?>
          <?= strtoupper(substr($user_s['name'] ?? 'U', 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <div class="name"><?= sanitize($user_s['name'] ?? 'User') ?></div>
        <div class="role"><?= $user_s['plan_name'] ?? 'Free' ?> · <?= number_format($user_s['credits'] ?? 0) ?> kredit</div>
      </div>
    </div>
  </div>
</aside>

<script>
function toggleSidebar(forceState) {
  const sb = document.getElementById('sidebar');
  const bd = document.getElementById('sidebar-backdrop');
  if (!sb) return;
  const isOpen = (typeof forceState === 'boolean') ? forceState : !sb.classList.contains('open');
  if (isOpen) {
    sb.classList.add('open');
    if (bd) bd.classList.add('active');
    document.body.style.overflow = 'hidden';
  } else {
    sb.classList.remove('open');
    if (bd) bd.classList.remove('active');
    document.body.style.overflow = '';
  }
}
</script>
