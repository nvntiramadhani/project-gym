<?php
// pages/payments.php
$path_prefix = '../';
$page_title = 'Transaksi Pembayaran';
include '../includes/header.php';
include '../config/database.php';

// Proteksi hak akses role: Owner dan Admin
require_roles(['owner', 'admin'], '../');

// Ambil semua data pembayaran transaksi
$stmt = $pdo->query("
    SELECT py.*, m.name as member_name, m.member_code, ms.package_id, p.name as package_name, u.name as receiver_name
    FROM payments py
    JOIN members m ON py.member_id = m.id
    JOIN memberships ms ON py.membership_id = ms.id
    JOIN packages p ON ms.package_id = p.id
    LEFT JOIN users u ON py.received_by = u.id
    WHERE m.deleted_at IS NULL
    ORDER BY py.id DESC
");
$payments = $stmt->fetchAll();
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
                        <input type="text" id="searchPayment" placeholder="Cari nama atau ID member..." onkeyup="filterPayments()">
                    </div>
                    <select class="form-select form-select-sm" style="width:auto" id="filterPaymentStatus" onchange="filterPayments()">
                        <option value="">Semua Status</option>
                        <option value="lunas">Lunas</option>
                        <option value="pending">Pending</option>
                        <option value="gagal">Gagal</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5>Log Transaksi Keuangan & Pembayaran</h5>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table-custom" id="paymentTable">
                            <thead>
                                <tr>
                                    <th>No</th><th>Tanggal</th><th>Anggota</th><th>ID Member</th><th>Paket</th><th>Jumlah</th><th>Metode</th><th>Penerima</th><th>Status</th><th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center p-4 text-muted">Belum ada transaksi pembayaran terdaftar.</td>
                                    </tr>
                                <?php else: 
                                    $no = 1;
                                    foreach ($payments as $py): 
                                        $status_class = $py['status'] === 'lunas' ? 'aktif' : ($py['status'] === 'pending' ? 'pending' : 'expired');
                                    ?>
                                        <tr class="payment-row" data-status="<?php echo htmlspecialchars($py['status']); ?>" data-search="<?php echo htmlspecialchars(strtolower($py['member_name'] . ' ' . $py['member_code'])); ?>">
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo date('d M Y', strtotime($py['payment_date'])); ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($py['member_name']); ?></td>
                                            <td>#<?php echo htmlspecialchars($py['member_code']); ?></td>
                                            <td><span class="badge-package <?php echo htmlspecialchars($py['package_id']); ?>"><?php echo htmlspecialchars($py['package_name']); ?></span></td>
                                            <td class="fw-bold text-success">Rp <?php echo number_format($py['amount'], 0, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars($py['payment_method']); ?></td>
                                            <td><?php echo htmlspecialchars($py['receiver_name'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge-status <?php echo $status_class; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($py['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <?php if ($py['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-success" onclick="confirmPayment(<?php echo $py['id']; ?>, '<?php echo htmlspecialchars(addslashes($py['member_name'])); ?>')"><i class="bi bi-check-lg"></i> Konfirmasi</button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" onclick="alert('Kuitansi Pembayaran\n\nNama: <?php echo htmlspecialchars(addslashes($py['member_name'])); ?>\nPaket: <?php echo htmlspecialchars(addslashes($py['package_name'])); ?>\nJumlah: Rp <?php echo number_format($py['amount'], 0, ',', '.'); ?>\nMetode: <?php echo htmlspecialchars($py['payment_method']); ?>\nTanggal: <?php echo date('d-m-Y', strtotime($py['payment_date'])); ?>\nPenerima: <?php echo htmlspecialchars(addslashes($py['receiver_name'] ?? '')); ?>')"><i class="bi bi-printer"></i> Cetak Resi</button>
                                                    <?php endif; ?>
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

    <!-- Hidden token for JS -->
    <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">

    <script>
    function filterPayments() {
        var query = document.getElementById('searchPayment').value.toLowerCase().trim();
        var status = document.getElementById('filterPaymentStatus').value;
        var rows = document.querySelectorAll('.payment-row');
        rows.forEach(function(row) {
            var searchContent = row.getAttribute('data-search');
            var rowStatus = row.getAttribute('data-status');
            
            var matchSearch = searchContent.indexOf(query) > -1;
            var matchStatus = !status || rowStatus === status;
            
            if (matchSearch && matchStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function confirmPayment(id, memberName) {
        if (!confirm('Konfirmasi pembayaran lunas untuk member "' + memberName + '"?')) {
            return;
        }

        var csrfToken = document.getElementById('csrfToken').value;

        fetch('../api/payments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: 'action=confirm&payment_id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Gagal konfirmasi: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error confirm payment:', error);
            alert('Gagal menghubungi server!');
        });
    }
    </script>

<?php
include '../includes/footer.php';
?>
