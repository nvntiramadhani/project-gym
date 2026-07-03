<?php
// pages/packages.php
$path_prefix = '../';
$page_title = 'Paket Membership';
include '../includes/header.php';
include '../config/database.php';

// Proteksi hak akses role
require_roles(['owner', 'admin'], '../');

// Ambil paket membership dari database yang aktif
$stmt = $pdo->query("SELECT * FROM packages WHERE status = 'aktif'");
$packages = $stmt->fetchAll(PDO::FETCH_UNIQUE);
?>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Top Navbar -->
        <?php include '../includes/topbar.php'; ?>

        <div class="content-wrapper">
            <!-- Package Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="package-card">
                        <div class="package-icon">🥈</div>
                        <div class="package-name">Silver</div>
                        <div class="package-price">Rp <?php echo number_format($packages['silver']['price'] ?? 200000, 0, ',', '.'); ?></div>
                        <div class="package-period">per bulan</div>
                        <ul class="package-features">
                            <li><i class="bi bi-check-circle-fill"></i> Akses gym area</li>
                            <li><i class="bi bi-check-circle-fill"></i> Locker harian</li>
                            <li><i class="bi bi-check-circle-fill"></i> Air minum gratis</li>
                            <li><i class="bi bi-check-circle-fill"></i> Jam operasional standar</li>
                            <li><i class="bi bi-x-circle-fill" style="color:var(--danger)"></i> <span style="color:var(--text-muted)">Personal Trainer</span></li>
                            <li><i class="bi bi-x-circle-fill" style="color:var(--danger)"></i> <span style="color:var(--text-muted)">Kelas grup</span></li>
                        </ul>
                        <button class="btn-primary-custom w-100 justify-content-center" onclick="alert('Pendaftaran paket Silver berhasil! (dummy)')">Pilih Paket</button>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="package-card popular">
                        <div class="package-icon">🥇</div>
                        <div class="package-name">Gold</div>
                        <div class="package-price">Rp <?php echo number_format($packages['gold']['price'] ?? 350000, 0, ',', '.'); ?></div>
                        <div class="package-period">per bulan</div>
                        <ul class="package-features">
                            <li><i class="bi bi-check-circle-fill"></i> Akses gym area</li>
                            <li><i class="bi bi-check-circle-fill"></i> Locker permanen</li>
                            <li><i class="bi bi-check-circle-fill"></i> Air minum gratis</li>
                            <li><i class="bi bi-check-circle-fill"></i> Jam operasional penuh</li>
                            <li><i class="bi bi-check-circle-fill"></i> 4x sesi Personal Trainer</li>
                            <li><i class="bi bi-x-circle-fill" style="color:var(--danger)"></i> <span style="color:var(--text-muted)">Kelas grup</span></li>
                        </ul>
                        <button class="btn-primary-custom w-100 justify-content-center" onclick="alert('Pendaftaran paket Gold berhasil! (dummy)')">Pilih Paket</button>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="package-card">
                        <div class="package-icon">💎</div>
                        <div class="package-name">Platinum</div>
                        <div class="package-price">Rp <?php echo number_format($packages['platinum']['price'] ?? 500000, 0, ',', '.'); ?></div>
                        <div class="package-period">per bulan</div>
                        <ul class="package-features">
                            <li><i class="bi bi-check-circle-fill"></i> Akses gym area</li>
                            <li><i class="bi bi-check-circle-fill"></i> Locker premium</li>
                            <li><i class="bi bi-check-circle-fill"></i> Air & handuk gratis</li>
                            <li><i class="bi bi-check-circle-fill"></i> Akses 24 jam</li>
                            <li><i class="bi bi-check-circle-fill"></i> 8x sesi Personal Trainer</li>
                            <li><i class="bi bi-check-circle-fill"></i> Semua kelas grup</li>
                        </ul>
                        <button class="btn-primary-custom w-100 justify-content-center" onclick="alert('Pendaftaran paket Platinum berhasil! (dummy)')">Pilih Paket</button>
                    </div>
                </div>
            </div>

            <!-- Comparison Table -->
            <div class="card-custom">
                <div class="card-header-custom"><h5>Perbandingan Fitur</h5></div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr><th>Fitur</th><th class="text-center">Silver</th><th class="text-center">Gold</th><th class="text-center">Platinum</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Akses Gym Area</td><td class="text-center"><i class="bi bi-check-lg text-success"></i></td><td class="text-center"><i class="bi bi-check-lg text-success"></i></td><td class="text-center"><i class="bi bi-check-lg text-success"></i></td></tr>
                                <tr><td>Jam Operasional</td><td class="text-center">06:00-22:00</td><td class="text-center">05:00-23:00</td><td class="text-center">24 Jam</td></tr>
                                <tr><td>Locker</td><td class="text-center">Harian</td><td class="text-center">Permanen</td><td class="text-center">Premium</td></tr>
                                <tr><td>Personal Trainer</td><td class="text-center"><i class="bi bi-x-lg text-danger"></i></td><td class="text-center">4 sesi/bln</td><td class="text-center">8 sesi/bln</td></tr>
                                <tr><td>Kelas Grup</td><td class="text-center"><i class="bi bi-x-lg text-danger"></i></td><td class="text-center"><i class="bi bi-x-lg text-danger"></i></td><td class="text-center"><i class="bi bi-check-lg text-success"></i></td></tr>
                                <tr><td>Handuk</td><td class="text-center"><i class="bi bi-x-lg text-danger"></i></td><td class="text-center"><i class="bi bi-x-lg text-danger"></i></td><td class="text-center"><i class="bi bi-check-lg text-success"></i></td></tr>
                                <tr><td>Sauna & Spa</td><td class="text-center"><i class="bi bi-x-lg text-danger"></i></td><td class="text-center"><i class="bi bi-x-lg text-danger"></i></td><td class="text-center"><i class="bi bi-check-lg text-success"></i></td></tr>
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
