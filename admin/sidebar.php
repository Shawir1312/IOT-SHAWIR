<?php
/**
 * ShawirIOT - Admin Sidebar Include
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$admin_s = currentUser();
$platformName = getSetting('platform_name', 'ShawirIOT');
$baseUrl = defined('PLATFORM_URL') ? PLATFORM_URL : '';

function adminNavItem(string $href, string $icon, string $label, string $current, string $page): string {
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
    <div class="logo-icon" style="background:linear-gradient(135deg, #f97316 0%, #fb923c 100%)">
      <i class="fas fa-shield-alt"></i>
    </div>
    <div class="logo-text" style="background:linear-gradient(135deg, #f97316 0%, #fb923c 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent">
      <?= $platformName ?> Admin
    </div>
    <button type="button" class="sidebar-close-btn" onclick="toggleSidebar(false)" aria-label="Close sidebar">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Panel Admin</div>
    <?= adminNavItem('index.php', 'chart-pie', 'Ringkasan', $currentPage, 'index.php') ?>
    <?= adminNavItem('users.php', 'users', 'Manajemen Pengguna', $currentPage, 'users.php') ?>
    <?= adminNavItem('credits.php', 'coins', 'Sistem Kredit', $currentPage, 'credits.php') ?>
    <?= adminNavItem('devices.php', 'server', 'Monitor Perangkat', $currentPage, 'devices.php') ?>
    <?= adminNavItem('settings.php', 'cog', 'Pengaturan Platform', $currentPage, 'settings.php') ?>

    <div class="nav-section-label" style="margin-top:1rem">Navigasi Pengguna</div>
    <a href="../dashboard.php" class="nav-item">
      <span class="nav-icon"><i class="fas fa-arrow-left"></i></span>
      <span>Ke Dashboard Pengguna</span>
    </a>
    <a href="../logout.php" class="nav-item" style="color:var(--danger)">
      <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
      <span>Keluar</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar" style="background:linear-gradient(135deg, #f97316 0%, #fb923c 100%)">
        <?= strtoupper(substr($admin_s['name'] ?? 'A', 0, 1)) ?>
      </div>
      <div class="user-info">
        <div class="name"><?= sanitize($admin_s['name'] ?? 'Admin') ?></div>
        <div class="role" style="color:#fb923c"><?= strtoupper($admin_s['role'] ?? 'ADMIN') ?></div>
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
