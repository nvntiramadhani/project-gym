<?php
// includes/sidebar.php

$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['user_role'] ?? '';
?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-trophy-fill"></i></div>
        <div>
            <span class="brand-text">Gym Management</span>
            <span class="brand-subtitle">Novi Prime Athletics</span>
        </div>
    </div>
    <nav class="sidebar-menu">
        <div class="menu-label">Menu Utama</div>
        
        <a href="<?php echo $path_prefix; ?>pages/dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>
        
        <a href="<?php echo $path_prefix; ?>pages/members.php" class="nav-link <?php echo ($current_page == 'members.php' || $current_page == 'member-detail.php') ? 'active' : ''; ?>">
            <i class="bi bi-people-fill"></i> Data Anggota
        </a>
        
        <?php if ($user_role === 'owner' || $user_role === 'admin'): ?>
            <a href="<?php echo $path_prefix; ?>pages/packages.php" class="nav-link <?php echo $current_page == 'packages.php' ? 'active' : ''; ?>">
                <i class="bi bi-box-seam-fill"></i> Paket Membership
            </a>
            
            <a href="<?php echo $path_prefix; ?>pages/attendance.php" class="nav-link <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check-fill"></i> Presensi
            </a>
            
            <a href="<?php echo $path_prefix; ?>pages/payments.php" class="nav-link <?php echo $current_page == 'payments.php' ? 'active' : ''; ?>">
                <i class="bi bi-cash-stack"></i> Transaksi Pembayaran
            </a>
            
            <a href="<?php echo $path_prefix; ?>pages/trainers.php" class="nav-link <?php echo $current_page == 'trainers.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-badge-fill"></i> Personal Trainer
            </a>
        <?php endif; ?>
        
        <?php if ($user_role === 'owner' || $user_role === 'trainer'): ?>
            <a href="<?php echo $path_prefix; ?>pages/workout-programs.php" class="nav-link <?php echo $current_page == 'workout-programs.php' ? 'active' : ''; ?>">
                <i class="bi bi-activity"></i> Program Latihan
            </a>
        <?php endif; ?>
        
        <?php if ($user_role === 'owner'): ?>
            <div class="menu-label">Kelola Owner</div>
            <a href="<?php echo $path_prefix; ?>pages/reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-bar-graph-fill"></i> Laporan Pendapatan
            </a>
            <a href="<?php echo $path_prefix; ?>pages/users.php" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                <i class="bi bi-gear-fill"></i> Kelola Pengguna
            </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="<?php echo $path_prefix; ?>logout.php" class="nav-link" id="logoutBtn">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>
</aside>
