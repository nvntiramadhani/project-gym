<?php
// pages/members.php
$path_prefix = '../';
$page_title = 'Data Anggota';
include '../includes/header.php';
include '../config/database.php';

// Proteksi hak akses role
require_roles(['owner', 'admin', 'trainer'], '../');

$user_role = $_SESSION['user_role'] ?? '';
$trainer_session_id = $_SESSION['trainer_id'] ?? null;

// Ambil data anggota dengan membership terbaru
// Trainer hanya melihat member binaannya
if ($user_role === 'trainer' && $trainer_session_id) {
    $stmt = $pdo->prepare("
        SELECT m.*, m.member_code,
               ms.package_id, ms.start_date as ms_start, ms.end_date as ms_end, ms.status as ms_status,
               p.name as package_name,
               t.id as current_trainer_id,
               u.name as trainer_name
        FROM members m
        INNER JOIN trainer_assignments ta ON ta.member_id = m.id AND ta.status = 'aktif' AND ta.trainer_id = ?
        LEFT JOIN (
            SELECT m1.* FROM memberships m1
            INNER JOIN (SELECT member_id, MAX(id) as max_id FROM memberships GROUP BY member_id) m2
            ON m1.id = m2.max_id
        ) ms ON ms.member_id = m.id
        LEFT JOIN packages p ON ms.package_id = p.id
        LEFT JOIN trainers t ON ta.trainer_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE m.deleted_at IS NULL
        ORDER BY m.id ASC
    ");
    $stmt->execute([$trainer_session_id]);
} else {
    $stmt = $pdo->query("
        SELECT m.*, m.member_code,
               ms.package_id, ms.start_date as ms_start, ms.end_date as ms_end, ms.status as ms_status,
               p.name as package_name,
               ta_active.trainer_id as current_trainer_id,
               u.name as trainer_name
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
        WHERE m.deleted_at IS NULL
        ORDER BY m.id ASC
    ");
}
$members = $stmt->fetchAll();

// Ambil list trainer aktif untuk dropdown di modal
$stmt = $pdo->query("SELECT t.id, u.name FROM trainers t JOIN users u ON t.user_id = u.id WHERE t.status = 'aktif'");
$trainers = $stmt->fetchAll();

// Ambil list paket aktif
$stmt = $pdo->query("SELECT id, name FROM packages WHERE status = 'aktif'");
$packages = $stmt->fetchAll();

$is_admin_or_owner = in_array($user_role, ['admin', 'owner']);
?>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Navbar -->
        <?php include '../includes/topbar.php'; ?>

        <div class="content-wrapper">
            <!-- Toolbar -->
            <div class="toolbar">
                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchMember" placeholder="Cari anggota..." onkeyup="filterMembers()">
                    </div>
                    <select class="form-select form-select-sm" style="width:auto" id="filterPaket" onchange="filterMembers()">
                        <option value="">Semua Paket</option>
                        <?php foreach ($packages as $pkg): ?>
                            <option value="<?php echo htmlspecialchars($pkg['id']); ?>"><?php echo htmlspecialchars($pkg['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" style="width:auto" id="filterStatus" onchange="filterMembers()">
                        <option value="">Semua Status</option>
                        <option value="aktif">Aktif</option>
                        <option value="expired">Expired</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <?php if ($is_admin_or_owner): ?>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#memberModal" onclick="openAddModal()">
                    <i class="bi bi-plus-lg"></i> Tambah Anggota
                </button>
                <?php endif; ?>
            </div>

            <!-- Table -->
            <div class="card-custom">
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table-custom" id="memberTable">
                            <thead>
                                <tr>
                                    <th>ID</th><th>Anggota</th><th>Paket</th><th>Tanggal Join</th><th>Expired</th><th>Status</th><th>Trainer</th><th style="width:140px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($members)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center p-4 text-muted">Belum ada data anggota.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($members as $m): 
                                        // Determine membership status dynamically
                                        $display_status = $m['ms_status'] ?? 'pending';
                                        // Check if end_date has passed
                                        if ($display_status === 'aktif' && !empty($m['ms_end']) && strtotime($m['ms_end']) < time()) {
                                            $display_status = 'expired';
                                        }

                                        // Generate avatar bg gradient
                                        $pkg_id = $m['package_id'] ?? '';
                                        $bg_grad = 'linear-gradient(135deg,#4361ee,#7b2ff7)';
                                        if ($pkg_id == 'platinum') {
                                            $bg_grad = 'linear-gradient(135deg,#00c897,#00b4d8)';
                                        } elseif ($pkg_id == 'silver') {
                                            $bg_grad = 'linear-gradient(135deg,#ff4757,#ff6b81)';
                                        }
                                        
                                        // Ambil inisial nama
                                        $words = explode(' ', $m['name']);
                                        $initials = '';
                                        foreach (array_slice($words, 0, 2) as $w) {
                                            $initials .= strtoupper(substr($w, 0, 1));
                                        }
                                    ?>
                                        <tr data-paket="<?php echo htmlspecialchars($pkg_id); ?>" data-status="<?php echo htmlspecialchars($display_status); ?>">
                                            <td>#<?php echo htmlspecialchars($m['member_code']); ?></td>
                                            <td>
                                                <div class="member-info">
                                                    <?php if (!empty($m['photo'])): ?>
                                                        <img src="../uploads/<?php echo htmlspecialchars($m['photo']); ?>" alt="" class="member-avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
                                                    <?php else: ?>
                                                        <div class="member-avatar" style="background:<?php echo $bg_grad; ?>"><?php echo htmlspecialchars($initials); ?></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="member-name"><?php echo htmlspecialchars($m['name']); ?></div>
                                                        <div class="member-email"><?php echo htmlspecialchars($m['phone']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge-package <?php echo htmlspecialchars($pkg_id); ?>"><?php echo htmlspecialchars($m['package_name'] ?? '-'); ?></span></td>
                                            <td><?php echo date('d M Y', strtotime($m['join_date'])); ?></td>
                                            <td><?php echo !empty($m['ms_end']) ? date('d M Y', strtotime($m['ms_end'])) : '-'; ?></td>
                                            <td><span class="badge-status <?php echo htmlspecialchars($display_status); ?>"><?php echo ucfirst(htmlspecialchars($display_status)); ?></span></td>
                                            <td><?php echo htmlspecialchars($m['trainer_name'] ?? '-'); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="member-detail.php?id=<?php echo urlencode($m['id']); ?>" class="btn-action view" title="Detail"><i class="bi bi-eye"></i></a>
                                                    <?php if ($is_admin_or_owner): ?>
                                                        <button class="btn-action edit" title="Edit" onclick="openEditModal(<?php echo $m['id']; ?>)"><i class="bi bi-pencil"></i></button>
                                                        <button class="btn-action delete" title="Hapus" onclick="confirmDelete(<?php echo $m['id']; ?>,'<?php echo htmlspecialchars(addslashes($m['name'])); ?>')"><i class="bi bi-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div style="font-size:13px;color:var(--text-muted)">Menampilkan <span id="displayedCount"><?php echo count($members); ?></span> dari <?php echo count($members); ?> anggota</div>
            </div>
        </div>
    </main>

    <?php if ($is_admin_or_owner): ?>
    <!-- Add/Edit Modal -->
    <div class="modal fade" id="memberModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border:none;border-radius:var(--radius)">
                <div class="modal-header" style="border-bottom:1px solid var(--border-color);padding:20px 24px">
                    <h5 class="modal-title" id="memberModalTitle">Tambah Anggota Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:24px">
                    <form id="memberForm" enctype="multipart/form-data">
                        <input type="hidden" id="memberId" name="id">
                        <input type="hidden" name="csrf_token" id="memberCsrf" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="memberName">Nama Lengkap *</label>
                                <input type="text" class="form-control" id="memberName" name="name" required placeholder="Masukkan nama lengkap">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberEmail">Email</label>
                                <input type="email" class="form-control" id="memberEmail" name="email" placeholder="Masukkan email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberPhone">No. Telepon *</label>
                                <input type="text" class="form-control" id="memberPhone" name="phone" required placeholder="Masukkan no. telepon">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberBirthDate">Tanggal Lahir *</label>
                                <input type="date" class="form-control" id="memberBirthDate" name="birth_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberGender">Jenis Kelamin *</label>
                                <select class="form-select" id="memberGender" name="gender" required>
                                    <option value="">Pilih</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="memberPhoto">Foto Profil (JPG/PNG, Maks 2MB)</label>
                                <input type="file" class="form-control" id="memberPhoto" name="photo" accept="image/jpeg,image/png">
                            </div>
                            <div class="col-md-6" id="packageGroup">
                                <label class="form-label" for="memberPackage">Paket Membership *</label>
                                <select class="form-select" id="memberPackage" name="package_id" required>
                                    <option value="">Pilih Paket</option>
                                    <?php foreach ($packages as $pkg): ?>
                                        <option value="<?php echo htmlspecialchars($pkg['id']); ?>"><?php echo htmlspecialchars($pkg['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" id="paymentStatusGroup">
                                <label class="form-label" for="memberPaymentStatus">Status Pembayaran *</label>
                                <select class="form-select" id="memberPaymentStatus" name="payment_status" required>
                                    <option value="lunas">Lunas</option>
                                    <option value="pending">Pending</option>
                                </select>
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
                                <textarea class="form-control" id="memberAddress" name="address" rows="2" placeholder="Masukkan alamat"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border-color);padding:16px 24px">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="saveMember()">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border:none;border-radius:var(--radius)">
                <div class="modal-body text-center" style="padding:32px">
                    <div class="stat-icon danger mx-auto mb-3" style="width:56px;height:56px;font-size:24px"><i class="bi bi-trash"></i></div>
                    <h5>Hapus Anggota?</h5>
                    <p id="deleteMessage" style="font-size:14px;color:var(--text-secondary)">Apakah Anda yakin ingin menghapus anggota ini?</p>
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <button class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-danger" onclick="deleteMember()">Ya, Hapus</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php
include '../includes/footer.php';
?>
