<?php
// pages/member-detail.php
$path_prefix = '../';
include '../config/database.php';

// Validasi parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: members.php");
    exit;
}

$member_id = (int)$_GET['id'];

// Ambil data detail anggota
$stmt = $pdo->prepare("
    SELECT m.*, 
           ms.package_id, ms.start_date as ms_start, ms.end_date as ms_end, ms.status as ms_status,
           p.name as package_name, p.price as package_price,
           ta_active.trainer_id, u.name as trainer_name, t.specialization as trainer_specialty
    FROM members m
    LEFT JOIN (
        SELECT m1.* FROM memberships m1
        INNER JOIN (SELECT member_id, MAX(id) as max_id FROM memberships GROUP BY member_id) m2
        ON m1.id = m2.max_id
    ) ms ON ms.member_id = m.id
    LEFT JOIN packages p ON ms.package_id = p.id
    LEFT JOIN trainer_assignments ta_active ON ta_active.member_id = m.id AND ta_active.status = 'aktif'
    LEFT JOIN trainers t ON ta_active.trainer_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE m.id = ? AND m.deleted_at IS NULL
");
$stmt->execute([$member_id]);
$member = $stmt->fetch();

if (!$member) {
    die("Error: Anggota dengan ID " . htmlspecialchars($member_id) . " tidak ditemukan.");
}

$page_title = 'Detail Anggota - ' . htmlspecialchars($member['name']);

// Set breadcrumb
$breadcrumb = '
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0" style="font-size:14px">
        <li class="breadcrumb-item"><a href="members.php" class="text-primary">Data Anggota</a></li>
        <li class="breadcrumb-item active">Detail Anggota</li>
    </ol>
</nav>';

include '../includes/header.php';

$user_role = $_SESSION['user_role'] ?? '';
$trainer_session_id = $_SESSION['trainer_id'] ?? null;

// Enforce Trainer Access Restrictions
if ($user_role === 'trainer') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trainer_assignments WHERE member_id = ? AND trainer_id = ? AND status = 'aktif'");
    $stmt->execute([$member_id, $trainer_session_id]);
    if ($stmt->fetchColumn() == 0) {
        http_response_code(403);
        echo '<div class="container mt-5 text-center">
                <div class="alert alert-danger p-5" style="border-radius:12px; background: rgba(255, 71, 87, 0.1); border: 1px solid var(--danger); color: var(--text-color)">
                    <i class="bi bi-exclamation-octagon-fill text-danger mb-3" style="font-size: 48px; display:block"></i>
                    <h4 class="alert-heading fw-bold text-danger">Akses Ditolak (403 Forbidden)</h4>
                    <p class="mb-4 text-muted">Anda hanya dapat mengakses member yang ditugaskan kepada Anda!</p>
                    <a href="members.php" class="btn btn-primary" style="background:var(--primary); border:none"><i class="bi bi-arrow-left me-2"></i>Kembali ke Data Anggota</a>
                </div>
              </div>';
        include '../includes/footer.php';
        exit;
    }
}

// 1. Ambil Riwayat Membership
$stmt = $pdo->prepare("
    SELECT ms.*, p.name as package_name, u.name as creator_name
    FROM memberships ms
    JOIN packages p ON ms.package_id = p.id
    LEFT JOIN users u ON ms.created_by = u.id
    WHERE ms.member_id = ?
    ORDER BY ms.id DESC
");
$stmt->execute([$member_id]);
$membership_history = $stmt->fetchAll();

// 2. Ambil Riwayat Pembayaran
$stmt = $pdo->prepare("
    SELECT py.*, p.name as package_name, u.name as receiver_name
    FROM payments py
    JOIN memberships ms ON py.membership_id = ms.id
    JOIN packages p ON ms.package_id = p.id
    LEFT JOIN users u ON py.received_by = u.id
    WHERE py.member_id = ?
    ORDER BY py.id DESC
");
$stmt->execute([$member_id]);
$payment_history = $stmt->fetchAll();

// 3. Ambil data kunjungan (presensi)
$stmt = $pdo->prepare("
    SELECT a.*, u.name as recorder_name
    FROM attendances a
    LEFT JOIN users u ON a.recorded_by = u.id
    WHERE a.member_id = ? 
    ORDER BY a.date DESC, a.check_in DESC
");
$stmt->execute([$member_id]);
$attendance_logs = $stmt->fetchAll();
$total_visits = count($attendance_logs);

// 4. Ambil data perkembangan fisik
$stmt = $pdo->prepare("
    SELECT pp.*, u.name as trainer_name
    FROM physical_progress pp
    LEFT JOIN trainers t ON pp.trainer_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE pp.member_id = ? 
    ORDER BY pp.measurement_date DESC
");
$stmt->execute([$member_id]);
$physical_logs = $stmt->fetchAll();

// 5. Ambil data program latihan
$stmt = $pdo->prepare("
    SELECT wp.*, u.name as trainer_name
    FROM workout_programs wp
    LEFT JOIN trainers t ON wp.trainer_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE wp.member_id = ?
    ORDER BY wp.id DESC
");
$stmt->execute([$member_id]);
$workout_programs = $stmt->fetchAll();

// 6. Hitung bulan aktif dari tanggal join
$join_date = new DateTime($member['join_date']);
$now = new DateTime();
$diff = $join_date->diff($now);
$months_active = ($diff->y * 12) + $diff->m;
$months_active = $months_active > 0 ? $months_active : 1;

// List trainer aktif untuk modal penugasan
$stmt = $pdo->query("SELECT t.id, u.name FROM trainers t JOIN users u ON t.user_id = u.id WHERE t.status = 'aktif'");
$trainers = $stmt->fetchAll();

// List paket aktif untuk modal perpanjangan
$stmt = $pdo->query("SELECT id, name, price FROM packages WHERE status = 'aktif'");
$packages = $stmt->fetchAll();

// Inisial avatar
$words = explode(' ', $member['name']);
$initials = '';
foreach (array_slice($words, 0, 2) as $w) {
    $initials .= strtoupper(substr($w, 0, 1));
}

// Menentukan status membership saat ini secara dinamis
$display_status = $member['ms_status'] ?? 'pending';
if ($display_status === 'aktif' && !empty($member['ms_end']) && strtotime($member['ms_end']) < time()) {
    $display_status = 'expired';
}

$is_admin_or_owner = in_array($user_role, ['admin', 'owner']);
$is_trainer = ($user_role === 'trainer');
?>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Navbar -->
        <?php include '../includes/topbar.php'; ?>

        <div class="content-wrapper">
            <!-- Profile Header -->
            <div class="profile-header">
                <?php if (!empty($member['photo'])): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($member['photo']); ?>" alt="" class="profile-avatar" style="width:100px;height:100px;border-radius:50%;object-fit:cover">
                <?php else: ?>
                    <div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <?php endif; ?>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($member['name']); ?></h2>
                    <div class="profile-id">ID Member: #<?php echo htmlspecialchars($member['member_code']); ?></div>
                    <div class="profile-badges">
                        <span class="badge-package <?php echo htmlspecialchars($member['package_id'] ?? ''); ?>"><?php echo htmlspecialchars($member['package_name'] ?? 'Belum ada paket'); ?></span>
                        <span class="badge-status <?php echo htmlspecialchars($display_status); ?>"><?php echo ucfirst(htmlspecialchars($display_status)); ?></span>
                    </div>
                </div>
                <div class="profile-stats">
                    <div class="p-stat">
                        <div class="p-stat-value"><?php echo $total_visits; ?></div>
                        <div class="p-stat-label">Total Kunjungan</div>
                    </div>
                    <div class="p-stat">
                        <div class="p-stat-value"><?php echo $months_active; ?></div>
                        <div class="p-stat-label">Bulan Aktif</div>
                    </div>
                    <div class="p-stat">
                        <div class="p-stat-value"><?php echo count($physical_logs); ?></div>
                        <div class="p-stat-label">Pengukuran Fisik</div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs nav-tabs-custom mb-4" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabInfo">Informasi Pribadi</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMembership">Membership & Transaksi</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVisit">Riwayat Kunjungan</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPhysical">Data Fisik</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabProgram">Program Latihan</button></li>
            </ul>

            <div class="tab-content">
                <!-- Tab: Info Pribadi -->
                <div class="tab-pane fade show active" id="tabInfo">
                    <div class="card-custom">
                        <div class="card-body-custom">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted" style="font-size:12px">Nama Lengkap</label>
                                        <p class="mb-0 fw-medium"><?php echo htmlspecialchars($member['name']); ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted" style="font-size:12px">Email</label>
                                        <p class="mb-0"><?php echo htmlspecialchars($member['email'] ? $member['email'] : '-'); ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted" style="font-size:12px">No. Telepon</label>
                                        <p class="mb-0"><?php echo htmlspecialchars($member['phone']); ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted" style="font-size:12px">Tanggal Lahir</label>
                                        <p class="mb-0"><?php echo !empty($member['birth_date']) ? date('d F Y', strtotime($member['birth_date'])) : '-'; ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted" style="font-size:12px">Jenis Kelamin</label>
                                        <p class="mb-0"><?php echo $member['gender'] === 'L' ? 'Laki-laki' : ($member['gender'] === 'P' ? 'Perempuan' : '-'); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted" style="font-size:12px">Alamat</label>
                                        <p class="mb-0"><?php echo htmlspecialchars($member['address'] ? $member['address'] : '-'); ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted" style="font-size:12px">Tanggal Bergabung</label>
                                        <p class="mb-0"><?php echo date('d F Y', strtotime($member['join_date'])); ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted" style="font-size:12px">Personal Trainer Utama</label>
                                        <p class="mb-0 fw-medium text-primary">
                                            <?php echo htmlspecialchars($member['trainer_name'] ? $member['trainer_name'] : 'Tanpa Personal Trainer'); ?>
                                            <?php if ($member['trainer_name'] && $member['trainer_specialty']): ?>
                                                <small class="text-muted d-block" style="font-size:11px"><?php echo htmlspecialchars($member['trainer_specialty']); ?></small>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Membership & Transaksi -->
                <div class="tab-pane fade" id="tabMembership">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card-custom h-100">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h5>Riwayat Paket Membership</h5>
                                    <?php if ($is_admin_or_owner): ?>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#renewModal">Perpanjang / Ganti Paket</button>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body-custom p-0">
                                    <div class="table-responsive">
                                        <table class="table-custom">
                                            <thead>
                                                <tr>
                                                    <th>Paket</th><th>Mulai</th><th>Expired</th><th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($membership_history)): ?>
                                                    <tr><td colspan="4" class="text-center p-4 text-muted">Belum ada riwayat membership.</td></tr>
                                                <?php else: foreach ($membership_history as $mh): 
                                                    $mh_status = $mh['status'];
                                                    if ($mh_status === 'aktif' && !empty($mh['end_date']) && strtotime($mh['end_date']) < time()) {
                                                        $mh_status = 'expired';
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><span class="badge-package <?php echo htmlspecialchars($mh['package_id']); ?>"><?php echo htmlspecialchars($mh['package_name']); ?></span></td>
                                                        <td><?php echo $mh['start_date'] ? date('d M Y', strtotime($mh['start_date'])) : '-'; ?></td>
                                                        <td><?php echo $mh['end_date'] ? date('d M Y', strtotime($mh['end_date'])) : '-'; ?></td>
                                                        <td><span class="badge-status <?php echo htmlspecialchars($mh_status); ?>"><?php echo ucfirst(htmlspecialchars($mh_status)); ?></span></td>
                                                    </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card-custom h-100">
                                <div class="card-header-custom">
                                    <h5>Riwayat Pembayaran Transaksi</h5>
                                </div>
                                <div class="card-body-custom p-0">
                                    <div class="table-responsive">
                                        <table class="table-custom">
                                            <thead>
                                                <tr>
                                                    <th>Tgl Bayar</th><th>Jumlah</th><th>Metode</th><th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($payment_history)): ?>
                                                    <tr><td colspan="4" class="text-center p-4 text-muted">Belum ada riwayat pembayaran.</td></tr>
                                                <?php else: foreach ($payment_history as $ph): ?>
                                                    <tr>
                                                        <td><?php echo date('d M Y', strtotime($ph['payment_date'])); ?></td>
                                                        <td class="fw-semibold">Rp <?php echo number_format($ph['amount'], 0, ',', '.'); ?></td>
                                                        <td><?php echo htmlspecialchars($ph['payment_method']); ?></td>
                                                        <td>
                                                            <span class="badge-status <?php echo $ph['status'] === 'lunas' ? 'aktif' : ($ph['status'] === 'pending' ? 'pending' : 'expired'); ?>">
                                                                <?php echo ucfirst(htmlspecialchars($ph['status'])); ?>
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
                    </div>
                </div>

                <!-- Tab: Riwayat Kunjungan -->
                <div class="tab-pane fade" id="tabVisit">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5>Log Presensi Kedatangan</h5>
                        </div>
                        <div class="card-body-custom p-0">
                            <div class="table-responsive">
                                <table class="table-custom">
                                    <thead><tr><th>No</th><th>Tanggal</th><th>Check-in</th><th>Check-out</th><th>Durasi</th><th>Pencatat</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($attendance_logs)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center p-4 text-muted">Belum ada riwayat kunjungan.</td>
                                            </tr>
                                        <?php else: 
                                            $no = 1;
                                            foreach ($attendance_logs as $log):
                                                $duration = '-';
                                                if ($log['check_out']) {
                                                    $in = strtotime($log['check_in']);
                                                    $out = strtotime($log['check_out']);
                                                    $diff_sec = $out - $in;
                                                    $hours = floor($diff_sec / 3600);
                                                    $minutes = floor(($diff_sec % 3600) / 60);
                                                    
                                                    $duration = '';
                                                    if ($hours > 0) $duration .= "{$hours} jam ";
                                                    if ($minutes > 0) $duration .= "{$minutes} menit";
                                                    if ($duration === '') $duration = '0 menit';
                                                }
                                        ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo date('d M Y', strtotime($log['date'])); ?></td>
                                                <td><?php echo date('H:i', strtotime($log['check_in'])); ?></td>
                                                <td><?php echo $log['check_out'] ? date('H:i', strtotime($log['check_out'])) : '-'; ?></td>
                                                <td><?php echo $duration; ?></td>
                                                <td><?php echo htmlspecialchars($log['recorder_name'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Data Fisik -->
                <div class="tab-pane fade" id="tabPhysical">
                    <?php if (empty($physical_logs)): ?>
                        <div class="alert alert-info">Belum ada data pengukuran fisik untuk anggota ini. Silakan klik tambah data di bawah.</div>
                    <?php else: 
                        $latest_phys = $physical_logs[0];
                    ?>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="stat-card text-center">
                                    <div class="stat-icon primary mx-auto"><i class="bi bi-rulers"></i></div>
                                    <div class="stat-value"><?php echo htmlspecialchars($latest_phys['height']); ?></div>
                                    <div class="stat-label">Tinggi Badan (cm)</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card text-center">
                                    <div class="stat-icon success mx-auto"><i class="bi bi-speedometer2"></i></div>
                                    <div class="stat-value"><?php echo htmlspecialchars($latest_phys['weight']); ?></div>
                                    <div class="stat-label">Berat Badan (kg)</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card text-center">
                                    <div class="stat-icon warning mx-auto"><i class="bi bi-heart-pulse"></i></div>
                                    <div class="stat-value"><?php echo number_format($latest_phys['bmi'], 1); ?></div>
                                    <div class="stat-label">BMI (<?php 
                                        $bmi_val = $latest_phys['bmi'];
                                        if ($bmi_val < 18.5) echo 'Kurang';
                                        elseif ($bmi_val < 25) echo 'Normal';
                                        elseif ($bmi_val < 30) echo 'Berlebih';
                                        else echo 'Obesitas';
                                    ?>)</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card-custom mt-4">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h5>Riwayat Pengukuran Fisik</h5>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#physicalModal">+ Tambah Data</button>
                        </div>
                        <div class="card-body-custom p-0">
                            <div class="table-responsive">
                                <table class="table-custom">
                                    <thead><tr><th>Tanggal</th><th>Berat (kg)</th><th>Tinggi (cm)</th><th>BMI</th><th>Lemak (%)</th><th>Trainer</th><th>Catatan</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($physical_logs)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center p-4 text-muted">Belum ada riwayat pengukuran fisik.</td>
                                            </tr>
                                        <?php else: foreach ($physical_logs as $phys): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($phys['measurement_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($phys['weight']); ?> kg</td>
                                                <td><?php echo htmlspecialchars($phys['height']); ?> cm</td>
                                                <td><?php echo number_format($phys['bmi'], 1); ?></td>
                                                <td><?php echo htmlspecialchars($phys['body_fat']); ?>%</td>
                                                <td><?php echo htmlspecialchars($phys['trainer_name'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($phys['notes']); ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Program Latihan -->
                <div class="tab-pane fade" id="tabProgram">
                    <div class="card-custom">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h5>Program Latihan & Target</h5>
                            <?php if ($is_trainer): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#programModal">+ Tambah Program</button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body-custom p-0">
                            <div class="table-responsive">
                                <table class="table-custom">
                                    <thead>
                                        <tr>
                                            <th>Program</th><th>Target Latihan</th><th>Keterangan</th><th>Mulai</th><th>Selesai</th><th>Trainer</th><th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($workout_programs)): ?>
                                            <tr><td colspan="7" class="text-center p-4 text-muted">Belum ada program latihan yang dibuat.</td></tr>
                                        <?php else: foreach ($workout_programs as $wp): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($wp['title']); ?></td>
                                                <td><?php echo htmlspecialchars($wp['target']); ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($wp['description'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($wp['start_date'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($wp['end_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($wp['trainer_name'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge-status <?php echo $wp['status'] === 'aktif' ? 'aktif' : ($wp['status'] === 'selesai' ? 'lunas' : 'expired'); ?>">
                                                        <?php echo ucfirst(htmlspecialchars($wp['status'])); ?>
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
            </div>

            <!-- Action buttons -->
            <div class="d-flex gap-2 mt-4">
                <a href="members.php" class="btn btn-light"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
                <?php if ($is_admin_or_owner): ?>
                <button class="btn btn-primary" onclick="openEditModal(
                    '<?php echo htmlspecialchars($member['id']); ?>',
                    '<?php echo htmlspecialchars(addslashes($member['name'])); ?>',
                    '<?php echo htmlspecialchars(addslashes($member['email'] ?? '')); ?>',
                    '<?php echo htmlspecialchars($member['package_id'] ?? ''); ?>',
                    '<?php echo htmlspecialchars($display_status); ?>',
                    '<?php echo htmlspecialchars($member['phone']); ?>',
                    '<?php echo htmlspecialchars(addslashes($member['address'] ?? '')); ?>',
                    '<?php echo htmlspecialchars($member['join_date']); ?>',
                    '<?php echo htmlspecialchars($member['trainer_id'] ?? ''); ?>'
                )"><i class="bi bi-pencil me-2"></i>Edit Data</button>
                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#assignTrainerModal"><i class="bi bi-person-badge me-2"></i>Tugaskan Trainer</button>
                <button class="btn btn-outline-danger" onclick="if(confirm('Yakin ingin menghapus anggota ini?')) { deleteMemberDirect(<?php echo $member['id']; ?>); }"><i class="bi bi-trash me-2"></i>Hapus</button>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php if ($is_admin_or_owner): ?>
    <!-- Modal Perpanjangan / Ganti Paket -->
    <div class="modal fade" id="renewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border:none;border-radius:var(--radius)">
                <div class="modal-header" style="border-bottom:1px solid var(--border-color);padding:20px 24px">
                    <h5 class="modal-title">Perpanjang / Ganti Paket Membership</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:24px">
                    <form id="renewForm">
                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="renewPackage">Pilih Paket Membership Baru *</label>
                            <select class="form-select" id="renewPackage" name="package_id" required>
                                <option value="">Pilih Paket</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?php echo htmlspecialchars($pkg['id']); ?>"><?php echo htmlspecialchars($pkg['name']); ?> (Rp <?php echo number_format($pkg['price'], 0, ',', '.'); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="renewPaymentStatus">Status Transaksi Pembayaran *</label>
                            <select class="form-select" id="renewPaymentStatus" name="payment_status" required>
                                <option value="lunas">Lunas</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border-color);padding:16px 24px">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="submitRenewal()">Proses Perpanjangan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Penugasan Trainer -->
    <div class="modal fade" id="assignTrainerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border:none;border-radius:var(--radius)">
                <div class="modal-header" style="border-bottom:1px solid var(--border-color);padding:20px 24px">
                    <h5 class="modal-title">Tugaskan Personal Trainer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:24px">
                    <form id="assignTrainerForm">
                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="assignTrainerSelect">Pilih Personal Trainer *</label>
                            <select class="form-select" id="assignTrainerSelect" name="trainer_id" required>
                                <option value="">Tanpa Personal Trainer</option>
                                <?php foreach ($trainers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo ($member['trainer_id'] == $t['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border-color);padding:16px 24px">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="submitTrainerAssignment()">Simpan Penugasan</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Data Fisik -->
    <div class="modal fade" id="physicalModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border:none;border-radius:var(--radius)">
                <div class="modal-header" style="border-bottom:1px solid var(--border-color);padding:20px 24px">
                    <h5 class="modal-title">Tambah Data Pengukuran Fisik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:24px">
                    <form id="physicalForm">
                        <input type="hidden" name="member_id" id="physMemberId" value="<?php echo $member['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="physHeight">Tinggi Badan (cm) *</label>
                                <input type="number" class="form-control" id="physHeight" name="height" required placeholder="Cth: 175" min="50" max="300">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="physWeight">Berat Badan (kg) *</label>
                                <input type="number" class="form-control" id="physWeight" name="weight" required placeholder="Cth: 70" min="20" max="500">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="physBodyFat">Lemak Tubuh (%) *</label>
                                <input type="number" step="0.1" class="form-control" id="physBodyFat" name="body_fat" required placeholder="Cth: 18.5" min="1" max="90">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="physDate">Tanggal Pengukuran *</label>
                                <input type="date" class="form-control" id="physDate" name="measurement_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="physNotes">Catatan</label>
                                <textarea class="form-control" id="physNotes" name="notes" rows="2" placeholder="Masukkan catatan perkembangan..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border-color);padding:16px 24px">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="savePhysical()">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_trainer): ?>
    <!-- Modal Program Latihan -->
    <div class="modal fade" id="programModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border:none;border-radius:var(--radius)">
                <div class="modal-header" style="border-bottom:1px solid var(--border-color);padding:20px 24px">
                    <h5 class="modal-title">Tambah Program Latihan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:24px">
                    <form id="programForm">
                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="programTitle">Nama Program Latihan *</label>
                                <input type="text" class="form-control" id="programTitle" name="title" required placeholder="Cth: Hypertrophy Dada & Tricep">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="programTarget">Target Latihan *</label>
                                <input type="text" class="form-control" id="programTarget" name="target" required placeholder="Cth: Menurunkan Lemak / Membentuk Otot">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="programStart">Tanggal Mulai *</label>
                                <input type="date" class="form-control" id="programStart" name="start_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="programEnd">Tanggal Selesai *</label>
                                <input type="date" class="form-control" id="programEnd" name="end_date" required value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="programDesc">Keterangan / Detail Latihan *</label>
                                <textarea class="form-control" id="programDesc" name="description" rows="3" required placeholder="Masukkan set, repetisi, dan jenis gerakan..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border-color);padding:16px 24px">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="submitWorkoutProgram()">Simpan Program</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($is_admin_or_owner): ?>
    <!-- Edit Member Modal (untuk fallback tombol Edit di detail page) -->
    <div class="modal fade" id="memberModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border:none;border-radius:var(--radius)">
                <div class="modal-header" style="border-bottom:1px solid var(--border-color);padding:20px 24px">
                    <h5 class="modal-title" id="memberModalTitle">Edit Anggota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:24px">
                    <form id="memberForm" enctype="multipart/form-data">
                        <input type="hidden" id="memberId" name="id">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="memberName">Nama Lengkap *</label>
                                <input type="text" class="form-control" id="memberName" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberEmail">Email</label>
                                <input type="email" class="form-control" id="memberEmail" name="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberPhone">No. Telepon *</label>
                                <input type="text" class="form-control" id="memberPhone" name="phone" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberBirthDate">Tanggal Lahir *</label>
                                <input type="date" class="form-control" id="memberBirthDate" name="birth_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberGender">Jenis Kelamin *</label>
                                <select class="form-select" id="memberGender" name="gender" required>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberPhoto">Foto Profil (JPG/PNG, Maks 2MB)</label>
                                <input type="file" class="form-control" id="memberPhoto" name="photo" accept="image/jpeg,image/png">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberTrainer">Personal Trainer (Opsional)</label>
                                <select class="form-select" id="memberTrainer" name="trainer_id">
                                    <option value="">Tanpa Personal Trainer</option>
                                    <?php foreach ($trainers as $t): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="memberAddress">Alamat</label>
                                <textarea class="form-control" id="memberAddress" name="address" rows="2"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border-color);padding:16px 24px">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="saveMember(true)">Simpan</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php
include '../includes/footer.php';
?>
