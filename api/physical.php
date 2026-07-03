<?php
// api/physical.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/csrf_helper.php';

// Proteksi akses API: Owner, Admin, dan Personal Trainer
require_roles(['owner', 'admin', 'trainer'], '../');

// Validasi CSRF Token
verify_csrf_post_or_json();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$member_id = isset($input['member_id']) ? (int)$input['member_id'] : 0;
$height = isset($input['height']) ? (int)$input['height'] : 0;
$weight = isset($input['weight']) ? (int)$input['weight'] : 0;
$body_fat = isset($input['body_fat']) ? (float)$input['body_fat'] : 0.0;
$date = isset($input['measurement_date']) ? trim($input['measurement_date']) : (isset($input['date']) ? trim($input['date']) : date('Y-m-d'));
$notes = isset($input['notes']) ? trim($input['notes']) : '';

if (empty($member_id) || $height <= 0 || $weight <= 0 || $body_fat <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mohon isi tinggi badan, berat badan, dan lemak tubuh dengan benar!']);
    exit;
}

try {
    // Validasi wewenang jika role adalah trainer
    if ($_SESSION['user_role'] === 'trainer') {
        $trainer_id = $_SESSION['trainer_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM trainer_assignments WHERE member_id = ? AND trainer_id = ? AND status = 'aktif'");
        $stmt->execute([$member_id, $trainer_id]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki wewenang untuk member ini!']);
            exit;
        }
    } else {
        // Owner / Admin: cari trainer yang aktif untuk member ini saat ini
        $stmt = $pdo->prepare("SELECT trainer_id FROM trainer_assignments WHERE member_id = ? AND status = 'aktif' LIMIT 1");
        $stmt->execute([$member_id]);
        $trainer_id = $stmt->fetchColumn() ?: NULL;
    }

    // Hitung BMI secara otomatis: weight (kg) / (height(m) ^ 2)
    $height_m = $height / 100;
    $bmi = $weight / ($height_m * $height_m);
    $bmi = round($bmi, 2);

    // Simpan data fisik baru
    $stmt = $pdo->prepare("INSERT INTO physical_progress (member_id, trainer_id, measurement_date, weight, height, bmi, body_fat, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$member_id, $trainer_id, $date, $weight, $height, $bmi, $body_fat, $notes]);

    echo json_encode([
        'success' => true, 
        'message' => 'Data perkembangan fisik berhasil ditambahkan!',
        'bmi' => $bmi
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data fisik: ' . $e->getMessage()]);
}
?>
