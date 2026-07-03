<?php
// includes/topbar.php

$admin_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$role_raw = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';

$role_labels = [
    'owner' => 'Owner / Manajer',
    'admin' => 'Admin Resepsionis',
    'trainer' => 'Personal Trainer'
];
$admin_role = $role_labels[$role_raw] ?? ucfirst($role_raw);
$admin_initial = strtoupper(substr($admin_name, 0, 1));
?>
<!-- Top Navbar -->
<header class="top-navbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn-icon sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <?php if (isset($breadcrumb)): ?>
            <?php echo $breadcrumb; ?>
        <?php else: ?>
            <h1 class="page-title"><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?></h1>
        <?php endif; ?>
    </div>
    <div class="navbar-actions">
        <button class="btn-icon" title="Notifikasi" onclick="alert('Tidak ada notifikasi baru.')">
            <i class="bi bi-bell"></i><span class="badge-dot"></span>
        </button>
        <div class="user-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="user-avatar"><?php echo htmlspecialchars($admin_initial); ?></div>
            <div class="user-info d-none d-sm-block">
                <div class="user-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($admin_role); ?></div>
            </div>
            <i class="bi bi-chevron-down ms-1 d-none d-sm-block" style="font-size:12px;color:var(--text-muted)"></i>
        </div>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" onclick="alert('Fitur profil akan segera hadir.')"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
            <li><a class="dropdown-item" href="#" onclick="alert('Fitur pengaturan akan segera hadir.')"><i class="bi bi-gear me-2"></i>Pengaturan</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?php echo $path_prefix; ?>logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
        </ul>
    </div>
</header>
