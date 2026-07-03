<?php
// helpers/csrf_helper.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? generate_csrf_token();
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function verify_csrf_post_or_json() {
    // Cek header X-CSRF-Token (untuk Fetch API)
    $headers = getallheaders();
    $token = '';
    
    // Normalisasi case headers
    $normalized_headers = array_change_key_case($headers, CASE_LOWER);
    
    if (isset($normalized_headers['x-csrf-token'])) {
        $token = $normalized_headers['x-csrf-token'];
    } else {
        // Fallback ke POST data atau JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['csrf_token'])) {
            $token = $input['csrf_token'];
        } elseif (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }
    }
    
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Validasi keamanan CSRF gagal. Silakan muat ulang halaman.']);
        exit;
    }
}
?>
