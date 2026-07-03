<?php
// pages/reports.php
$path_prefix = '../';
$page_title = 'Laporan Keuangan & Statistik';
include '../includes/header.php';
include '../config/database.php';

// Proteksi hak akses role: Owner saja
require_roles('owner', '../');

// 1. Total Pendapatan Keseluruhan (lunas)
$stmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'lunas'");
$total_revenue = $stmt->fetchColumn() ?: 0;

// 2. Member Aktif dan Expired
$stmt = $pdo->query("SELECT COUNT(DISTINCT member_id) FROM memberships WHERE status = 'aktif' AND (end_date IS NULL OR end_date >= CURDATE())");
$active_members = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("SELECT COUNT(DISTINCT member_id) FROM memberships WHERE status = 'expired' OR (status = 'aktif' AND end_date < CURDATE())");
$expired_members = $stmt->fetchColumn() ?: 0;

$total_members = $active_members + $expired_members;

// 3. Ambil data Pendapatan Bulanan (6 bulan terakhir) untuk Grafik
$chart_months = [];
$chart_revenue = [];
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i months"));
    $month_num = date('n', strtotime($date));
    $year_num = date('Y', strtotime($date));
    $month_name = date('M Y', strtotime($date));
    
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE MONTH(payment_date) = ? AND YEAR(payment_date) = ? AND status = 'lunas'");
    $stmt->execute([$month_num, $year_num]);
    $rev = $stmt->fetchColumn() ?: 0;
    
    $chart_months[] = $month_name;
    $chart_revenue[] = (int)$rev;
}

// 4. Statistik Beban Trainer (Klien aktif per Trainer)
$stmt = $pdo->query("
    SELECT u.name as trainer_name, COUNT(ta.id) as client_count
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN trainer_assignments ta ON t.id = ta.trainer_id AND ta.status = 'aktif'
    WHERE t.status = 'aktif'
    GROUP BY t.id
");
$trainer_stats = $stmt->fetchAll();

$trainer_names = [];
$trainer_clients = [];
foreach ($trainer_stats as $ts) {
    $trainer_names[] = $ts['trainer_name'];
    $trainer_clients[] = (int)$ts['client_count'];
}

// 5. Total Presensi (Kunjungan) Bulan Ini
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
$stmt->execute();
$monthly_visits = $stmt->fetchColumn() ?: 0;

$load_chart = true; // load chart.js di footer
?>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Navbar -->
        <?php include '../includes/topbar.php'; ?>

        <div class="content-wrapper">
            <!-- Stat Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon primary"><i class="bi bi-wallet2"></i></div>
                        <div class="stat-value text-success">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                        <div class="stat-label">Total Pendapatan (Lunas)</div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon success"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-value"><?php echo $active_members; ?></div>
                        <div class="stat-label">Member Aktif</div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="stat-value"><?php echo $expired_members; ?></div>
                        <div class="stat-label">Member Expired</div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon warning"><i class="bi bi-calendar-check-fill"></i></div>
                        <div class="stat-value"><?php echo $monthly_visits; ?></div>
                        <div class="stat-label">Total Kunjungan (Bulan Ini)</div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row g-4 mb-4">
                <!-- Pendapatan Chart -->
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5>Tren Pendapatan Keuangan (6 Bulan Terakhir)</h5>
                        </div>
                        <div class="card-body-custom">
                            <canvas id="revenueReportChart" height="280"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Status Distribution & Trainer Clients -->
                <div class="col-lg-4">
                    <div class="card-custom mb-4">
                        <div class="card-header-custom">
                            <h5>Beban Personal Trainer (Klien Aktif)</h5>
                        </div>
                        <div class="card-body-custom">
                            <canvas id="trainerLoadChart" height="180"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Tabel Klien per Trainer detail -->
                <div class="col-md-6">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5>Detail Klien per Trainer</h5>
                        </div>
                        <div class="card-body-custom p-0">
                            <table class="table-custom">
                                <thead>
                                    <tr><th>Nama Trainer</th><th class="text-center">Klien Binaan Aktif</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($trainer_stats)): ?>
                                        <tr><td colspan="2" class="text-center p-3 text-muted">Belum ada trainer aktif.</td></tr>
                                    <?php else: foreach ($trainer_stats as $ts): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ts['trainer_name']); ?></strong></td>
                                            <td class="text-center"><span class="badge bg-primary fs-6"><?php echo $ts['client_count']; ?></span></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Summary Status Anggota -->
                <div class="col-md-6">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <h5>Distribusi Status Membership</h5>
                        </div>
                        <div class="card-body-custom">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Member Aktif</span>
                                <span class="fw-bold text-success"><?php echo $active_members; ?> (<?php echo $total_members > 0 ? round(($active_members/$total_members)*100) : 0; ?>%)</span>
                            </div>
                            <div class="progress mb-4" style="height: 10px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $total_members > 0 ? ($active_members/$total_members)*100 : 0; ?>%"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Member Expired</span>
                                <span class="fw-bold text-danger"><?php echo $expired_members; ?> (<?php echo $total_members > 0 ? round(($expired_members/$total_members)*100) : 0; ?>%)</span>
                            </div>
                            <div class="progress mb-4" style="height: 10px;">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $total_members > 0 ? ($expired_members/$total_members)*100 : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Script Chart Rendering -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Line chart pendapatan
        var ctxRev = document.getElementById('revenueReportChart').getContext('2d');
        new Chart(ctxRev, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_months); ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?php echo json_encode($chart_revenue); ?>,
                    borderColor: 'rgba(0, 200, 151, 1)',
                    backgroundColor: 'rgba(0, 200, 151, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Bar chart beban trainer
        var ctxTrainer = document.getElementById('trainerLoadChart').getContext('2d');
        new Chart(ctxTrainer, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($trainer_names); ?>,
                datasets: [{
                    label: 'Jumlah Klien',
                    data: <?php echo json_encode($trainer_clients); ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.8)',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    });
    </script>

<?php
include '../includes/footer.php';
?>
