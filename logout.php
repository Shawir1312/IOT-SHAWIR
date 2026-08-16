<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
logoutUser();
flash('success', 'Anda berhasil keluar.');
redirect(PLATFORM_URL . '/login.php');
