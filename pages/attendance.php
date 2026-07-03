<?php
// pages/attendance.php
$path_prefix = '../';
$page_title = 'Presensi';
include '../includes/header.php';
include '../config/database.php';

// Proteksi hak akses role: Owner dan Admin saja
require_roles(['owner', 'admin'], '../');

// Ambil tanggal terpilih
$selected_date = isset($_GET['date']) ? $_GET['date'] : '';
if (empty($selected_date)) {
    // Cari tanggal terakhir dari database agar data seed kelihatan
    $stmt = $pdo->query("SELECT MAX(date) FROM attendances");
    $max_date = $stmt->fetchColumn();
    $selected_date = $max_date ? $max_date : date('Y-m-d');
}

// 1. Hitung Statistik berdasarkan tanggal terpilih
// Total active members untuk hitung tingkat kehadiran
$stmt = $pdo->query("SELECT COUNT(DISTINCT member_id) FROM memberships WHERE status = 'aktif' AND (end_date IS NULL OR end_date >= CURDATE())");
$active_members_count = $stmt->fetchColumn();
$active_members_count = $active_members_count > 0 ? $active_members_count : 1;

// Check-in hari ini (tanggal terpilih)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE date = ?");
$stmt->execute([$selected_date]);
$checkins = $stmt->fetchColumn();

// Check-out hari ini
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE date = ? AND check_out IS NOT NULL");
$stmt->execute([$selected_date]);
$checkouts = $stmt->fetchColumn();

// Sedang di Gym
$in_gym = $checkins - $checkouts;
$in_gym = $in_gym > 0 ? $in_gym : 0;

// Tingkat Kehadiran
$attendance_rate = round(($checkins / $active_members_count) * 100);
$attendance_rate = $attendance_rate > 100 ? 100 : $attendance_rate;

// 2. Ambil data Log Presensi pada tanggal terpilih
$stmt = $pdo->prepare("
    SELECT a.*, m.name as member_name, m.member_code, ms.package_id, p.name as package_name, u.name as recorder_name
    FROM attendances a 
    JOIN members m ON a.member_id = m.id 
    LEFT JOIN (
        SELECT m1.* FROM memberships m1
        INNER JOIN (SELECT member_id, MAX(id) as max_id FROM memberships GROUP BY member_id) m2
        ON m1.id = m2.max_id
    ) ms ON ms.member_id = m.id
    LEFT JOIN packages p ON ms.package_id = p.id
    LEFT JOIN users u ON a.recorded_by = u.id
    WHERE a.date = ? 
    ORDER BY a.check_in DESC
");
$stmt->execute([$selected_date]);
$attendance_logs = $stmt->fetchAll();
?>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Navbar -->
        <?php include '../includes/topbar.php'; ?>

        <div class="content-wrapper">
            <!-- Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon primary"><i class="bi bi-box-arrow-in-right"></i></div>
                        <div class="stat-value"><?php echo $checkins; ?></div>
                        <div class="stat-label">Check-in Hari Ini</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon success"><i class="bi bi-box-arrow-right"></i></div>
                        <div class="stat-value"><?php echo $checkouts; ?></div>
                        <div class="stat-label">Check-out Hari Ini</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon warning"><i class="bi bi-people"></i></div>
                        <div class="stat-value"><?php echo $in_gym; ?></div>
                        <div class="stat-label">Sedang di Gym</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-icon danger"><i class="bi bi-graph-up"></i></div>
                        <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Tingkat Kehadiran</div>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchAttendance" placeholder="Cari anggota..." onkeyup="filterAttendance()">
                    </div>
                    <input type="date" id="attendanceDate" class="form-control form-control-sm" style="width:auto" value="<?php echo htmlspecialchars($selected_date); ?>" onchange="location.href='?date=' + this.value">
                </div>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#checkinModal">
                    <i class="bi bi-qr-code-scan"></i> Check-in Manual
                </button>
            </div>

            <!-- Attendance Table -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5>Log Presensi - <?php echo date('d F Y', strtotime($selected_date)); ?></h5>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table-custom" id="attendanceTable">
                            <thead>
                                <tr><th>No</th><th>Anggota</th><th>ID Member</th><th>Check-in</th><th>Check-out</th><th>Durasi</th><th>Pencatat</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attendance_logs)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center p-4 text-muted">Belum ada data presensi pada tanggal ini.</td>
                                    </tr>
                                <?php else: 
                                    $no = 1;
                                    foreach ($attendance_logs as $log):
                                        // Avatar bg gradient
                                        $pkg_id = $log['package_id'] ?? '';
                                        $bg_grad = 'linear-gradient(135deg,#4361ee,#7b2ff7)';
                                        if ($pkg_id == 'platinum') {
                                            $bg_grad = 'linear-gradient(135deg,#00c897,#00b4d8)';
                                        } elseif ($pkg_id == 'silver') {
                                            $bg_grad = 'linear-gradient(135deg,#ff4757,#ff6b81)';
                                        }
                                        
                                        // Avatar initials
                                        $words = explode(' ', $log['member_name']);
                                        $initials = '';
                                        foreach (array_slice($words, 0, 2) as $w) {
                                            $initials .= strtoupper(substr($w, 0, 1));
                                        }
                                        
                                        // Hitung durasi
                                        $duration = '-';
                                        if ($log['check_out']) {
                                            $in = strtotime($log['check_in']);
                                            $out = strtotime($log['check_out']);
                                            $diff_sec = $out - $in;
                                            $hours = floor($diff_sec / 3600);
                                            $minutes = floor(($diff_sec % 3600) / 60);
                                            
                                            $duration = '';
                                            if ($hours > 0) $duration .= "{$hours}j ";
                                            if ($minutes > 0) $duration .= "{$minutes}m";
                                            if ($duration === '') $duration = '0m';
                                        }
                                ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <div class="member-info">
                                                <div class="member-avatar" style="background:<?php echo $bg_grad; ?>"><?php echo htmlspecialchars($initials); ?></div>
                                                <div class="member-name"><?php echo htmlspecialchars($log['member_name']); ?></div>
                                            </div>
                                        </td>
                                        <td>#<?php echo htmlspecialchars($log['member_code']); ?></td>
                                        <td><?php echo date('H:i', strtotime($log['check_in'])); ?></td>
                                        <td><?php echo $log['check_out'] ? date('H:i', strtotime($log['check_out'])) : '-'; ?></td>
                                        <td><?php echo $duration; ?></td>
                                        <td><?php echo htmlspecialchars($log['recorder_name'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($log['check_out']): ?>
                                                <span class="badge-status aktif">Selesai</span>
                                            <?php else: ?>
                                                <span class="badge-status" style="background:var(--primary-light);color:var(--primary)">Di Gym</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                 <?php endforeach; ?><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Check-in Modal -->
    <div class="modal fade" id="checkinModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border:none;border-radius:var(--radius)">
                <div class="modal-header" style="border-bottom:1px solid var(--border-color);padding:20px 24px">
                    <h5 class="modal-title">Check-in Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:24px">
                    <div class="mb-3">
                        <label class="form-label" for="checkinId">ID Member</label>
                        <input type="text" class="form-control" id="checkinId" placeholder="Masukkan ID Member (cth: M001)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="checkinType">Tipe</label>
                        <select class="form-select" id="checkinType">
                            <option value="in">Check-in</option>
                            <option value="out">Check-out</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border-color);padding:16px 24px">
                    <button class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" onclick="doCheckin()">Proses</button>
                </div>
            </div>
        </div>
    </div>

<?php
include '../includes/footer.php';
?>
