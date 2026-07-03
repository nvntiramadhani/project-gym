<?php
// pages/workout-programs.php
$path_prefix = '../';
$page_title = 'Program Latihan';
include '../includes/header.php';
include '../config/database.php';

// Proteksi hak akses role: Owner, Admin, dan Personal Trainer
require_roles(['owner', 'admin', 'trainer'], '../');

$user_role = $_SESSION['user_role'] ?? '';
$trainer_session_id = $_SESSION['trainer_id'] ?? null;

// Query program latihan berdasarkan role
if ($user_role === 'trainer' && $trainer_session_id) {
    $stmt = $pdo->prepare("
        SELECT wp.*, m.name as member_name, m.member_code, u.name as trainer_name
        FROM workout_programs wp
        JOIN members m ON wp.member_id = m.id
        JOIN trainers t ON wp.trainer_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE wp.trainer_id = ? AND m.deleted_at IS NULL
        ORDER BY wp.id DESC
    ");
    $stmt->execute([$trainer_session_id]);
} else {
    $stmt = $pdo->query("
        SELECT wp.*, m.name as member_name, m.member_code, u.name as trainer_name
        FROM workout_programs wp
        JOIN members m ON wp.member_id = m.id
        JOIN trainers t ON wp.trainer_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE m.deleted_at IS NULL
        ORDER BY wp.id DESC
    ");
}
$programs = $stmt->fetchAll();
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
                        <input type="text" id="searchProgram" placeholder="Cari program atau anggota..." onkeyup="filterPrograms()">
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5>Daftar Program Latihan Anggota</h5>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table-custom" id="programTable">
                            <thead>
                                <tr>
                                    <th>No</th><th>Anggota</th><th>ID Member</th><th>Nama Program</th><th>Target</th><th>Masa Berlaku</th><th>Trainer</th><th>Status</th><th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($programs)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center p-4 text-muted">Belum ada program latihan yang terdaftar.</td>
                                    </tr>
                                <?php else: 
                                    $no = 1;
                                    foreach ($programs as $p): 
                                        $display_status = $p['status'];
                                        if ($display_status === 'aktif' && strtotime($p['end_date']) < time()) {
                                            $display_status = 'selesai'; // auto mark as completed when past end_date
                                        }
                                ?>
                                    <tr class="program-row" data-search-content="<?php echo htmlspecialchars(strtolower($p['member_name'] . ' ' . $p['title'] . ' ' . $p['member_code'])); ?>">
                                        <td><?php echo $no++; ?></td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($p['member_name']); ?></td>
                                        <td>#<?php echo htmlspecialchars($p['member_code']); ?></td>
                                        <td><?php echo htmlspecialchars($p['title']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($p['target']); ?></span></td>
                                        <td><?php echo date('d M Y', strtotime($p['start_date'])); ?> s/d <?php echo date('d M Y', strtotime($p['end_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($p['trainer_name']); ?></td>
                                        <td>
                                            <span class="badge-status <?php echo $display_status === 'aktif' ? 'aktif' : ($display_status === 'selesai' ? 'lunas' : 'expired'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($display_status)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="member-detail.php?id=<?php echo urlencode($p['member_id']); ?>#tabProgram" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Lihat Detail</a>
                                            </div>
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

    <script>
    function filterPrograms() {
        var query = document.getElementById('searchProgram').value.toLowerCase().trim();
        var rows = document.querySelectorAll('.program-row');
        rows.forEach(function(row) {
            var content = row.getAttribute('data-search-content');
            if (content.indexOf(query) > -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    </script>

<?php
include '../includes/footer.php';
?>
