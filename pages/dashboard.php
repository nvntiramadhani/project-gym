<?php
// pages/dashboard.php
$path_prefix = '../';
$page_title = 'Dashboard';
include '../includes/header.php';
include '../config/database.php';

$user_role = $_SESSION['user_role'] ?? '';
$trainer_session_id = $_SESSION['trainer_id'] ?? null;

// 1. Ambil data statistik berdasarkan role
if ($user_role === 'trainer' && $trainer_session_id) {
    // Total Klien Binaan (aktif)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT member_id) FROM trainer_assignments WHERE trainer_id = ? AND status = 'aktif'");
    $stmt->execute([$trainer_session_id]);
    $total_members = $stmt->fetchColumn() ?: 0;

    // Klien Aktif Membership
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ta.member_id) 
        FROM trainer_assignments ta 
        JOIN memberships ms ON ta.member_id = ms.member_id
        WHERE ta.trainer_id = ? AND ta.status = 'aktif' AND ms.status = 'aktif' AND (ms.end_date IS NULL OR ms.end_date >= CURDATE())
    ");
    $stmt->execute([$trainer_session_id]);
    $active_members = $stmt->fetchColumn() ?: 0;

    // Klien Expired Membership
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ta.member_id) 
        FROM trainer_assignments ta 
        LEFT JOIN (
            SELECT m1.* FROM memberships m1
            INNER JOIN (SELECT member_id, MAX(id) as max_id FROM memberships GROUP BY member_id) m2
            ON m1.id = m2.max_id
        ) ms ON ta.member_id = ms.member_id
        WHERE ta.trainer_id = ? AND ta.status = 'aktif' 
          AND (ms.status = 'expired' OR ms.status IS NULL OR (ms.status = 'aktif' AND ms.end_date < CURDATE()))
    ");
    $stmt->execute([$trainer_session_id]);
    $expired_members = $stmt->fetchColumn() ?: 0;

    // Sesi PT Hari Ini
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM pt_appointments pt
        JOIN trainer_assignments ta ON pt.member_id = ta.member_id AND ta.status = 'aktif'
        WHERE ta.trainer_id = ? AND pt.appointment_date = CURDATE() AND pt.status = 'Terjadwal'
    ");
    $stmt->execute([$trainer_session_id]);
    $pt_today_count = $stmt->fetchColumn() ?: 0;

} else {
    // Owner / Admin melihat data global
    // Total Member (tidak dihapus)
    $stmt = $pdo->query("SELECT COUNT(*) FROM members WHERE deleted_at IS NULL");
    $total_members = $stmt->fetchColumn() ?: 0;

    // Member Aktif
    $stmt = $pdo->query("SELECT COUNT(DISTINCT member_id) FROM memberships WHERE status = 'aktif' AND (end_date IS NULL OR end_date >= CURDATE())");
    $active_members = $stmt->fetchColumn() ?: 0;

    // Member Expired
    $stmt = $pdo->query("SELECT COUNT(DISTINCT member_id) FROM memberships WHERE status = 'expired' OR (status = 'aktif' AND end_date < CURDATE())");
    $expired_members = $stmt->fetchColumn() ?: 0;

    // Pendapatan Bulan Ini (Owner) / Kunjungan Hari Ini (Admin)
    if ($user_role === 'owner') {
        $stmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'lunas' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
        $revenue = $stmt->fetchColumn() ?: 0;
        $revenue_str = 'Rp ' . number_format($revenue, 0, ',', '.');
    } else {
        // Admin: Presensi Hari Ini
        $stmt = $pdo->query("SELECT COUNT(*) FROM attendances WHERE date = CURDATE()");
        $today_attendance = $stmt->fetchColumn() ?: 0;
    }
}

// 2. Ambil data Anggota Terbaru (Limit 4)
if ($user_role === 'trainer' && $trainer_session_id) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.name, m.member_code, m.join_date, ms.status as ms_status, ms.package_id, p.name as package_name 
        FROM members m 
        JOIN trainer_assignments ta ON ta.member_id = m.id AND ta.status = 'aktif'
        LEFT JOIN (
            SELECT m1.* FROM memberships m1
            INNER JOIN (SELECT member_id, MAX(id) as max_id FROM memberships GROUP BY member_id) m2
            ON m1.id = m2.max_id
        ) ms ON ms.member_id = m.id
        LEFT JOIN packages p ON ms.package_id = p.id
        WHERE ta.trainer_id = ? AND m.deleted_at IS NULL
        ORDER BY m.join_date DESC LIMIT 4
    ");
    $stmt->execute([$trainer_session_id]);
} else {
    $stmt = $pdo->query("
        SELECT m.id, m.name, m.member_code, m.join_date, ms.status as ms_status, ms.package_id, p.name as package_name 
        FROM members m 
        LEFT JOIN (
            SELECT m1.* FROM memberships m1
            INNER JOIN (SELECT member_id, MAX(id) as max_id FROM memberships GROUP BY member_id) m2
            ON m1.id = m2.max_id
        ) ms ON ms.member_id = m.id
        LEFT JOIN packages p ON ms.package_id = p.id
        WHERE m.deleted_at IS NULL
        ORDER BY m.join_date DESC LIMIT 4
    ");
}
$recent_members = $stmt->fetchAll();

// 3. Ambil data Aktivitas Terbaru (Presensi check-in terbaru & Pendaftaran member baru)
if ($user_role === 'trainer' && $trainer_session_id) {
    // Check-in klien binaan
    $stmt = $pdo->prepare("
        SELECT m.name, 'checkin' as type, a.check_in as event_time, a.date as event_date 
        FROM attendances a 
        JOIN members m ON a.member_id = m.id 
        JOIN trainer_assignments ta ON ta.member_id = m.id AND ta.status = 'aktif'
        WHERE ta.trainer_id = ? AND m.deleted_at IS NULL
        ORDER BY a.date DESC, a.check_in DESC LIMIT 5
    ");
    $stmt->execute([$trainer_session_id]);
} else {
    // Global check-in
    $stmt = $pdo->query("
        SELECT m.name, 'checkin' as type, a.check_in as event_time, a.date as event_date 
        FROM attendances a 
        JOIN members m ON a.member_id = m.id 
        WHERE m.deleted_at IS NULL
        ORDER BY a.date DESC, a.check_in DESC LIMIT 3
    ");
}
$recent_checkins = $stmt->fetchAll();

// Gabungkan pendaftaran baru jika Owner/Admin
$activities = [];
foreach ($recent_checkins as $c) {
    $activities[] = [
        'title' => htmlspecialchars($c['name']) . ' check-in',
        'type' => 'checkin',
        'time' => $c['event_date'] . ' ' . $c['event_time']
    ];
}

if ($user_role !== 'trainer') {
    // Pendaftaran terbaru
    $stmt = $pdo->query("
        SELECT m.name, 'register' as type, p.name as package_name, m.join_date as event_date 
        FROM members m 
        JOIN memberships ms ON ms.member_id = m.id
        JOIN packages p ON ms.package_id = p.id
        WHERE m.deleted_at IS NULL
        ORDER BY m.join_date DESC LIMIT 3
    ");
    $recent_registrations = $stmt->fetchAll();
    
    foreach ($recent_registrations as $r) {
        $activities[] = [
            'title' => htmlspecialchars($r['name']) . ' mendaftar paket ' . htmlspecialchars($r['package_name']),
            'type' => 'register',
            'time' => $r['event_date'] . ' 00:00:00'
        ];
    }
}

// Urutkan aktivitas berdasarkan waktu (descending)
usort($activities, function($a, $b) {
    return strcmp($b['time'], $a['time']);
});
$activities = array_slice($activities, 0, 5); // ambil 5 teratas

// 4. Ambil statistik bulanan untuk Chart Anggota Baru (6 bulan terakhir)
$chart_months = [];
$chart_joins = [];
$chart_leaves = [0, 0, 0, 0, 0, 0]; // dummy/placeholder

for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i months"));
    $month_num = date('n', strtotime($date));
    $year_num = date('Y', strtotime($date));
    $month_name = date('M', strtotime($date));
    
    if ($user_role === 'trainer' && $trainer_session_id) {
        // Registrasi klien binaan baru di bulan ini
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM trainer_assignments ta
            JOIN members m ON ta.member_id = m.id
            WHERE ta.trainer_id = ? AND MONTH(ta.start_date) = ? AND YEAR(ta.start_date) = ? AND m.deleted_at IS NULL
        ");
        $stmt->execute([$trainer_session_id, $month_num, $year_num]);
    } else {
        // Registrasi member baru global
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE MONTH(join_date) = ? AND YEAR(join_date) = ? AND deleted_at IS NULL");
        $stmt->execute([$month_num, $year_num]);
    }
    $joins_count = $stmt->fetchColumn() ?: 0;
    
    $chart_months[] = $month_name;
    $chart_joins[] = (int)$joins_count;
}

// Inisialisasi Chart data untuk di-render di app.js
echo "<script>
    var dynamicChartData = {
        labels: " . json_encode($chart_months) . ",
        joins: " . json_encode($chart_joins) . ",
        leaves: " . json_encode($chart_leaves) . "
    };
</script>";

$load_chart = true; // load chart.js di footer
?>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Navbar -->
        <?php include '../includes/topbar.php'; ?>

        <div class="content-wrapper">
            <!-- Stat Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon primary"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-value"><?php echo $total_members; ?></div>
                        <div class="stat-label"><?php echo $user_role === 'trainer' ? 'Total Klien Binaan' : 'Total Anggota'; ?></div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon success"><i class="bi bi-person-check-fill"></i></div>
                        <div class="stat-value"><?php echo $active_members; ?></div>
                        <div class="stat-label">Anggota Aktif</div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="stat-value"><?php echo $expired_members; ?></div>
                        <div class="stat-label">Membership Expired</div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <?php if ($user_role === 'owner'): ?>
                        <div class="stat-card">
                            <div class="stat-icon danger"><i class="bi bi-cash-stack"></i></div>
                            <div class="stat-value text-success"><?php echo $revenue_str; ?></div>
                            <div class="stat-label">Pendapatan Bulan Ini</div>
                        </div>
                    <?php elseif ($user_role === 'trainer'): ?>
                        <div class="stat-card">
                            <div class="stat-icon danger"><i class="bi bi-calendar-event-fill"></i></div>
                            <div class="stat-value"><?php echo $pt_today_count; ?></div>
                            <div class="stat-label">Sesi PT Hari Ini</div>
                        </div>
                    <?php else: ?>
                        <!-- Admin Resepsionis -->
                        <div class="stat-card">
                            <div class="stat-icon danger"><i class="bi bi-calendar-check-fill"></i></div>
                            <div class="stat-value"><?php echo $today_attendance; ?></div>
                            <div class="stat-label">Presensi Hari Ini</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4">
                <!-- Chart Area -->
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5><?php echo $user_role === 'trainer' ? 'Klien Baru Binaan' : 'Statistik Anggota Baru'; ?></h5>
                        </div>
                        <div class="card-body-custom">
                            <canvas id="memberChart" height="280"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5>Aktivitas Terbaru</h5>
                            <?php if ($user_role !== 'trainer'): ?>
                                <a href="attendance.php" class="text-primary" style="font-size:13px">Lihat Semua</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body-custom p-0">
                            <div class="list-group list-group-flush">
                                <?php if (empty($activities)): ?>
                                    <div class="p-4 text-center text-muted" style="font-size:13px">Belum ada aktivitas hari ini.</div>
                                <?php else: ?>
                                    <?php foreach ($activities as $act): 
                                        $icon_class = 'primary';
                                        $icon_bi = 'bi-info-circle-fill';
                                        if ($act['type'] === 'register') {
                                            $icon_class = 'success';
                                            $icon_bi = 'bi-person-plus-fill';
                                        } elseif ($act['type'] === 'checkin') {
                                            $icon_class = 'primary';
                                            $icon_bi = 'bi-box-arrow-in-right';
                                        }
                                    ?>
                                        <div class="list-group-item px-4 py-3 d-flex align-items-center gap-3">
                                            <div class="stat-icon <?php echo $icon_class; ?>" style="width:36px;height:36px;font-size:14px;flex-shrink:0">
                                                <i class="bi <?php echo $icon_bi; ?>"></i>
                                            </div>
                                            <div style="flex-grow:1">
                                                <div style="font-size:13px;font-weight:500;color:var(--text-color)"><?php echo $act['title']; ?></div>
                                                <div style="font-size:11px;color:var(--text-muted)"><?php echo date('d M Y H:i', strtotime($act['time'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Members table in dashboard -->
            <div class="card-custom mt-4">
                <div class="card-header-custom">
                    <h5><?php echo $user_role === 'trainer' ? 'Klien Terbaru Anda' : 'Pendaftaran Anggota Terbaru'; ?></h5>
                    <a href="members.php" class="text-primary" style="font-size:13px">Lihat Semua Anggota</a>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>ID Member</th><th>Nama</th><th>Paket</th><th>Tanggal Bergabung</th><th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_members)): ?>
                                    <tr><td colspan="5" class="text-center p-3 text-muted">Belum ada pendaftaran baru.</td></tr>
                                <?php else: foreach ($recent_members as $rm): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($rm['member_code']); ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($rm['name']); ?></td>
                                        <td><span class="badge-package <?php echo htmlspecialchars($rm['package_id'] ?? ''); ?>"><?php echo htmlspecialchars($rm['package_name'] ?? '-'); ?></span></td>
                                        <td><?php echo date('d M Y', strtotime($rm['join_date'])); ?></td>
                                        <td>
                                            <span class="badge-status <?php echo htmlspecialchars($rm['ms_status'] ?? 'pending'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($rm['ms_status'] ?? 'pending')); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php
include '../includes/footer.php';
?>
