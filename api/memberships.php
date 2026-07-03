<?php
// api/memberships.php
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

$member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
$package_id = isset($_POST['package_id']) ? trim($_POST['package_id']) : '';
$payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'pending';

if (empty($member_id) || empty($package_id)) {
    echo json_encode(['success' => false, 'message' => 'Mohon lengkapi data perpanjangan!']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Ambil data member aktif
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$member_id]);
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Anggota tidak ditemukan atau sudah dihapus!']);
        $pdo->rollBack();
        exit;
    }

    // 2. Ambil informasi paket
    $stmt = $pdo->prepare("SELECT price, duration_months FROM packages WHERE id = ? AND status = 'aktif'");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch();
    if (!$package) {
        echo json_encode(['success' => false, 'message' => 'Paket tidak aktif atau tidak ditemukan!']);
        $pdo->rollBack();
        exit;
    }

    // 3. Tentukan tanggal start dan end membership jika pembayaran lunas
    $start_date = NULL;
    $end_date = NULL;
    $m_status = 'pending';
    
    if ($payment_status === 'lunas') {
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$package['duration_months']} months"));
        $m_status = 'aktif';

        // Nonaktifkan/set expired membership aktif sebelumnya untuk member ini
        $stmt = $pdo->prepare("UPDATE memberships SET status = 'expired' WHERE member_id = ? AND status = 'aktif'");
        $stmt->execute([$member_id]);
    }

    // 4. Buat data membership baru
    $stmt = $pdo->prepare("INSERT INTO memberships (member_id, package_id, start_date, end_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$member_id, $package_id, $start_date, $end_date, $m_status, $_SESSION['user_id']]);
    $new_membership_id = $pdo->lastInsertId();

    // 5. Buat data pembayaran transaksi
    $stmt = $pdo->prepare("INSERT INTO payments (member_id, membership_id, amount, payment_date, payment_method, status, received_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$member_id, $new_membership_id, $package['price'], date('Y-m-d'), 'Cash', $payment_status, $_SESSION['user_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Membership baru berhasil didaftarkan!']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Kesalahan sistem: ' . $e->getMessage()]);
}
?>
