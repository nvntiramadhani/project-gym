<?php
// api/login.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../helpers/csrf_helper.php';

// Validasi CSRF Token
verify_csrf_post_or_json();

// Ambil input JSON atau POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email dan password wajib diisi!']);
    exit;
}

try {
    // Cari user di database berdasarkan email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Cek status akun
        if ($user['status'] !== 'aktif') {
            echo json_encode(['success' => false, 'message' => 'Akun Anda dinonaktifkan. Silakan hubungi Owner!']);
            exit;
        }

        // Regenerasi Session ID untuk mencegah Session Fixation
        session_regenerate_id(true);

        // Set session
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        // Jika user adalah Trainer, simpan ID Trainer ke session agar gampang digunakan di query client
        if ($user['role'] === 'trainer') {
            $t_stmt = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
            $t_stmt->execute([$user['id']]);
            $_SESSION['trainer_id'] = $t_stmt->fetchColumn() ?: null;
        }

        echo json_encode(['success' => true, 'message' => 'Login berhasil!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email atau password salah!']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
}
?>
