<?php
// api/trainers.php
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
    // Default action: assign trainer
    $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
    $trainer_id = isset($_POST['trainer_id']) && !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;

    if (empty($member_id)) {
        echo json_encode(['success' => false, 'message' => 'ID Member tidak valid!']);
        exit;
    }

    $pdo->beginTransaction();

    // 1. Ambil penugasan trainer aktif saat ini
    $stmt = $pdo->prepare("SELECT * FROM trainer_assignments WHERE member_id = ? AND status = 'aktif' LIMIT 1");
    $stmt->execute([$member_id]);
    $active_assignment = $stmt->fetch();

    if ($trainer_id) {
        // Jika trainer yang ditugaskan berbeda dari yang aktif saat ini
        if (!$active_assignment || (int)$active_assignment['trainer_id'] !== $trainer_id) {
            // Selesaikan penugasan lama
            if ($active_assignment) {
                $stmt = $pdo->prepare("UPDATE trainer_assignments SET status = 'selesai', end_date = ? WHERE id = ?");
                $stmt->execute([date('Y-m-d'), $active_assignment['id']]);
            }
            // Tambahkan penugasan baru
            $stmt = $pdo->prepare("INSERT INTO trainer_assignments (member_id, trainer_id, start_date, status, assigned_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$member_id, $trainer_id, date('Y-m-d'), 'aktif', $_SESSION['user_id']]);
        }
    } else {
        // Jika dikosongkan (tanpa trainer), selesaikan penugasan lama
        if ($active_assignment) {
            $stmt = $pdo->prepare("UPDATE trainer_assignments SET status = 'selesai', end_date = ? WHERE id = ?");
            $stmt->execute([date('Y-m-d'), $active_assignment['id']]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Penugasan Personal Trainer berhasil diperbarui!']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Kesalahan sistem: ' . $e->getMessage()]);
}
?>
