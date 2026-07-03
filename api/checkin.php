<?php
// api/checkin.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Proteksi API: hanya Owner dan Admin yang boleh mencatat presensi
require_once '../config/database.php';
require_once '../helpers/auth_helper.php';

require_roles(['owner', 'admin'], '../');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$raw_id = isset($input['member_id']) ? trim($input['member_id']) : '';
$type = isset($input['type']) ? trim($input['type']) : 'in'; // 'in' atau 'out'

if (empty($raw_id)) {
    echo json_encode(['success' => false, 'message' => 'ID Member wajib diisi!']);
    exit;
}

// Normalisasi ID Member (cth: '#M001' -> 'M001', '1' -> 'M001')
$member_code = strtoupper($raw_id);
$member_code = ltrim($member_code, '#');
if (is_numeric($member_code)) {
    $member_code = 'M' . sprintf('%03d', (int)$member_code);
}

try {
    // 1. Validasi apakah member ada dan tidak dihapus
    $stmt = $pdo->prepare("SELECT * FROM members WHERE member_code = ? AND deleted_at IS NULL");
    $stmt->execute([$member_code]);
    $member = $stmt->fetch();

    if (!$member) {
        echo json_encode(['success' => false, 'message' => 'ID Member "' . htmlspecialchars($raw_id) . '" tidak terdaftar!']);
        exit;
    }

    $member_db_id = $member['id'];

    // 2. Ambil data membership yang aktif saat ini
    $stmt = $pdo->prepare("
        SELECT * FROM memberships 
        WHERE member_id = ? AND status = 'aktif' AND (end_date IS NULL OR end_date >= CURDATE())
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$member_db_id]);
    $active_membership = $stmt->fetch();

    // Jika check-in dan tidak ada membership aktif, ditolak!
    if ($type === 'in' && !$active_membership) {
        echo json_encode(['success' => false, 'message' => 'Presensi ditolak! Membership "' . htmlspecialchars($member['name']) . '" sudah expired atau tidak aktif.']);
        exit;
    }

    $today = date('Y-m-d');
    $now_time = date('H:i:s');

    if ($type === 'in') {
        // Cek apakah member sudah check-in hari ini dan belum check-out
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE member_id = ? AND date = ? AND check_out IS NULL");
        $stmt->execute([$member_db_id, $today]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Member "' . htmlspecialchars($member['name']) . '" sudah check-in hari ini dan belum check-out!']);
            exit;
        }

        // Simpan log check-in ke tabel attendances
        $stmt = $pdo->prepare("INSERT INTO attendances (member_id, date, check_in, recorded_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$member_db_id, $today, $now_time, $_SESSION['user_id']]);

        echo json_encode([
            'success' => true, 
            'message' => 'Check-in berhasil untuk ' . htmlspecialchars($member['name']) . ' pada pukul ' . date('H:i', strtotime($now_time))
        ]);

    } elseif ($type === 'out') {
        // Cari log check-in hari ini yang belum check-out
        $stmt = $pdo->prepare("SELECT * FROM attendances WHERE member_id = ? AND date = ? AND check_out IS NULL ORDER BY check_in DESC LIMIT 1");
        $stmt->execute([$member_db_id, $today]);
        $log = $stmt->fetch();

        // Jika tidak ada check-in hari ini, cari check-in terakhir yang belum check-out
        if (!$log) {
            $stmt = $pdo->prepare("SELECT * FROM attendances WHERE member_id = ? AND check_out IS NULL ORDER BY date DESC, check_in DESC LIMIT 1");
            $stmt->execute([$member_db_id]);
            $log = $stmt->fetch();
        }

        if (!$log) {
            echo json_encode(['success' => false, 'message' => 'Member "' . htmlspecialchars($member['name']) . '" tidak memiliki sesi check-in aktif (belum check-in).']);
            exit;
        }

        // Update log check-out
        $stmt = $pdo->prepare("UPDATE attendances SET check_out = ? WHERE id = ?");
        $stmt->execute([$now_time, $log['id']]);

        echo json_encode([
            'success' => true, 
            'message' => 'Check-out berhasil untuk ' . htmlspecialchars($member['name']) . ' pada pukul ' . date('H:i', strtotime($now_time))
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tipe presensi tidak dikenal.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal memproses presensi: ' . $e->getMessage()]);
}
?>
