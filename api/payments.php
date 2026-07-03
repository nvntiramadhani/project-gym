<?php
// api/payments.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/csrf_helper.php';

// Proteksi akses API: hanya untuk Owner dan Admin
require_roles(['owner', 'admin'], '../');

// Validasi CSRF Token
verify_csrf_post_or_json();

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    if ($action === 'confirm') {
        $payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;

        if (empty($payment_id)) {
            echo json_encode(['success' => false, 'message' => 'ID Pembayaran tidak valid!']);
            exit;
        }

        $pdo->beginTransaction();

        // 1. Ambil data pembayaran
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();

        if (!$payment) {
            echo json_encode(['success' => false, 'message' => 'Data transaksi tidak ditemukan!']);
            $pdo->rollBack();
            exit;
        }

        if ($payment['status'] === 'lunas') {
            echo json_encode(['success' => false, 'message' => 'Pembayaran sudah lunas sebelumnya!']);
            $pdo->rollBack();
            exit;
        }

        // 2. Ambil data membership yang terkait
        $stmt = $pdo->prepare("SELECT * FROM memberships WHERE id = ?");
        $stmt->execute([$payment['membership_id']]);
        $membership = $stmt->fetch();

        if (!$membership) {
            echo json_encode(['success' => false, 'message' => 'Data membership terkait tidak ditemukan!']);
            $pdo->rollBack();
            exit;
        }

        // 3. Ambil data paket untuk menghitung durasi
        $stmt = $pdo->prepare("SELECT duration_months FROM packages WHERE id = ?");
        $stmt->execute([$membership['package_id']]);
        $duration_months = $stmt->fetchColumn() ?: 1;

        // 4. Hitung tanggal mulai dan tanggal expired baru
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$duration_months} months"));

        // 5. Nonaktifkan membership aktif sebelumnya untuk member ini
        $stmt = $pdo->prepare("UPDATE memberships SET status = 'expired' WHERE member_id = ? AND status = 'aktif'");
        $stmt->execute([$payment['member_id']]);

        // 6. Update status membership terkait menjadi aktif
        $stmt = $pdo->prepare("UPDATE memberships SET start_date = ?, end_date = ?, status = 'aktif' WHERE id = ?");
        $stmt->execute([$start_date, $end_date, $payment['membership_id']]);

        // 7. Update status pembayaran menjadi lunas
        $stmt = $pdo->prepare("UPDATE payments SET status = 'lunas', payment_date = ?, received_by = ? WHERE id = ?");
        $stmt->execute([$start_date, $_SESSION['user_id'], $payment_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dikonfirmasi dan membership diaktifkan!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal!']);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Kesalahan sistem: ' . $e->getMessage()]);
}
?>
