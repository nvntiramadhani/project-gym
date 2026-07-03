<?php
// api/workout_programs.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/csrf_helper.php';

// Proteksi akses API: hanya untuk Owner dan Personal Trainer
require_roles(['owner', 'trainer'], '../');

// Validasi CSRF Token
verify_csrf_post_or_json();

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    if ($action === 'create' || empty($action)) {
        $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $target = isset($_POST['target']) ? trim($_POST['target']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';

        if (empty($member_id) || empty($title) || empty($target) || empty($description) || empty($start_date) || empty($end_date)) {
            echo json_encode(['success' => false, 'message' => 'Mohon lengkapi semua field wajib!']);
            exit;
        }

        // Jika user adalah trainer, validasi apakah member ini adalah binaannya
        if ($_SESSION['user_role'] === 'trainer') {
            $trainer_id = $_SESSION['trainer_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trainer_assignments WHERE member_id = ? AND trainer_id = ? AND status = 'aktif'");
            $stmt->execute([$member_id, $trainer_id]);
            if ($stmt->fetchColumn() == 0) {
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki wewenang untuk member ini!']);
                exit;
            }
        } else {
            // Owner: cari trainer yang aktif untuk member ini saat ini
            $stmt = $pdo->prepare("SELECT trainer_id FROM trainer_assignments WHERE member_id = ? AND status = 'aktif' LIMIT 1");
            $stmt->execute([$member_id]);
            $trainer_id = $stmt->fetchColumn();
            if (!$trainer_id) {
                echo json_encode(['success' => false, 'message' => 'Member ini belum memiliki trainer yang ditugaskan! Silakan tugaskan trainer terlebih dahulu.']);
                exit;
            }
        }

        // Simpan program latihan
        $stmt = $pdo->prepare("INSERT INTO workout_programs (member_id, trainer_id, title, target, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif')");
        $stmt->execute([$member_id, $trainer_id, $title, $target, $description, $start_date, $end_date]);

        echo json_encode(['success' => true, 'message' => 'Program latihan berhasil ditambahkan!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal!']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Kesalahan sistem: ' . $e->getMessage()]);
}
?>
