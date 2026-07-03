<?php
// includes/header.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set default path prefix jika belum diset
if (!isset($path_prefix)) {
    $path_prefix = '';
}

require_once $path_prefix . 'helpers/auth_helper.php';
require_once $path_prefix . 'helpers/csrf_helper.php';

// Cek apakah halaman saat ini butuh proteksi login
if (!isset($is_login_page) || !$is_login_page) {
    require_login($path_prefix);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Novi Prime Athletics - Sistem Manajemen Anggota Gym">
    <title><?php echo isset($page_title) ? $page_title . ' - Novi Prime Athletics' : 'Novi Prime Athletics - Gym Management'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo $path_prefix; ?>css/style.css" rel="stylesheet">
</head>
<body class="<?php echo isset($body_class) ? $body_class : ''; ?>">
<?php if (!isset($is_login_page) || !$is_login_page): ?>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php endif; ?>
