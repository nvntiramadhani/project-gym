<?php
// pages/trainers.php
$path_prefix = '../';
$page_title = 'Personal Trainer';
include '../includes/header.php';
include '../config/database.php';

// Proteksi hak akses role
require_roles(['owner', 'admin', 'trainer'], '../');

$user_role = $_SESSION['user_role'] ?? '';
$is_owner = ($user_role === 'owner');

// Ambil data trainer dari database dengan informasi user terkait
$stmt = $pdo->query("
    SELECT t.*, u.name, u.email, u.status as user_status
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    WHERE t.status IN ('aktif', 'cuti')
    ORDER BY t.id ASC
");
$trainers = $stmt->fetchAll();
?>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Navbar -->
        <?php include '../includes/topbar.php'; ?>

        <div class="content-wrapper">
            <div class="toolbar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchTrainer" placeholder="Cari trainer..." onkeyup="filterTrainers()">
                </div>
                <?php if ($is_owner): ?>
                <button class="btn-primary-custom" onclick="location.href='users.php'">
                    <i class="bi bi-gear"></i> Kelola Trainer & Admin
                </button>
                <?php endif; ?>
            </div>

            <!-- Trainer Cards -->
            <div class="row g-4" id="trainerContainer">
                <?php if (empty($trainers)): ?>
                    <div class="col-12 text-center p-5 text-muted">Belum ada data trainer.</div>
                <?php else: foreach ($trainers as $t): 
                    // Generate avatar initials from name
                    $words = explode(' ', $t['name']);
                    $initials = '';
                    foreach (array_slice($words, 0, 2) as $w) {
                        $initials .= strtoupper(substr($w, 0, 1));
                    }
                    
                    // Avatar bg gradient
                    $bg_grad = 'linear-gradient(135deg,#4361ee,#7b2ff7)';
                    if ($t['id'] % 5 == 1) {
                        $bg_grad = 'linear-gradient(135deg,#4361ee,#7b2ff7)'; // RK
                    } elseif ($t['id'] % 5 == 2) {
                        $bg_grad = 'linear-gradient(135deg,#00c897,#00b4d8)'; // MA
                    } elseif ($t['id'] % 5 == 3) {
                        $bg_grad = 'linear-gradient(135deg,#ff4757,#ff6b81)'; // DA
                    } elseif ($t['id'] % 5 == 4) {
                        $bg_grad = 'linear-gradient(135deg,#ffb800,#ff9500)'; // LS
                    } else {
                        $bg_grad = 'linear-gradient(135deg,#6c5ce7,#a29bfe)'; // BW
                    }
                    
                    // Status styling
                    $status_class = $t['status'] == 'aktif' ? 'aktif' : 'expired';
                    $status_text = $t['status'] == 'aktif' ? 'Aktif' : 'Cuti';
                    
                    // Count active clients
                    $stmt_clients = $pdo->prepare("SELECT COUNT(*) FROM trainer_assignments WHERE trainer_id = ? AND status = 'aktif'");
                    $stmt_clients->execute([$t['id']]);
                    $clients_count = $stmt_clients->fetchColumn();
                ?>
                    <div class="col-lg-4 col-md-6 trainer-item" data-name="<?php echo htmlspecialchars(strtolower($t['name'])); ?>">
                        <div class="trainer-card">
                            <div class="trainer-avatar" style="background:<?php echo $bg_grad; ?>"><?php echo htmlspecialchars($initials); ?></div>
                            <div class="trainer-name"><?php echo htmlspecialchars($t['name']); ?></div>
                            <div class="trainer-spec"><?php echo htmlspecialchars($t['specialization']); ?></div>
                            <div class="d-flex justify-content-center gap-2 mb-3">
                                <span class="badge-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </div>
                            <div class="trainer-stats">
                                <span><strong><?php echo $clients_count; ?></strong>Klien Aktif</span>
                                <span><strong><?php echo htmlspecialchars($t['experience_years']); ?></strong>Tahun Exp</span>
                                <span><strong><?php echo htmlspecialchars($t['rating']); ?></strong>Rating</span>
                            </div>
                            <hr>
                            <div class="d-flex gap-2 justify-content-center">
                                <button class="btn btn-sm btn-outline-primary" onclick="alert('Detail <?php echo htmlspecialchars(addslashes($t['name'])); ?>:\n\nEmail: <?php echo htmlspecialchars($t['email']); ?>\nTelepon: <?php echo htmlspecialchars($t['phone']); ?>\nSpesialis: <?php echo htmlspecialchars(addslashes($t['specialization'])); ?>\nSertifikasi: <?php echo htmlspecialchars(addslashes($t['certifications'] ?? '-')); ?>\nJadwal: <?php echo htmlspecialchars($t['schedule_days'] ?? '-'); ?> <?php echo htmlspecialchars($t['schedule_time'] ?? '-'); ?>')"><i class="bi bi-eye me-1"></i>Detail Info</button>
                                <?php if ($is_owner): ?>
                                    <button class="btn btn-sm btn-outline-warning" onclick="location.href='users.php'"><i class="bi bi-pencil me-1"></i>Kelola</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </main>

    <script>
    // Client-side trainer search
    function filterTrainers() {
        var query = document.getElementById('searchTrainer').value.toLowerCase().trim();
        var items = document.querySelectorAll('.trainer-item');
        items.forEach(function(item) {
            var name = item.getAttribute('data-name');
            if (name.indexOf(query) > -1) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }
    </script>

<?php
include '../includes/footer.php';
?>
