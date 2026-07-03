<?php
// helpers/auth_helper.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function check_login() {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        return false;
    }
    return true;
}

function require_login($path_prefix = '') {
    if (!check_login()) {
        header("Location: " . $path_prefix . "index.php");
        exit;
    }
}

function require_roles($allowed_roles, $path_prefix = '') {
    require_login($path_prefix);
    $user_role = $_SESSION['user_role'] ?? '';
    
    if (is_array($allowed_roles)) {
        if (!in_array($user_role, $allowed_roles)) {
            // Tolak akses
            http_response_code(403);
            $page_title = 'Akses Ditolak';
            include_once __DIR__ . '/../includes/header.php';
            echo '<div class="container mt-5 text-center">
                    <div class="alert alert-danger p-5" style="border-radius:12px; background: rgba(255, 71, 87, 0.1); border: 1px solid var(--danger); color: var(--text-color)">
                        <i class="bi bi-exclamation-octagon-fill text-danger mb-3" style="font-size: 48px; display:block"></i>
                        <h4 class="alert-heading fw-bold text-danger">Akses Ditolak (403 Forbidden)</h4>
                        <p class="mb-4 text-muted">Anda tidak memiliki hak akses untuk membuka halaman ini.</p>
                        <a href="' . $path_prefix . 'pages/dashboard.php" class="btn btn-primary" style="background:var(--primary); border:none"><i class="bi bi-house-door-fill me-2"></i>Kembali ke Dashboard</a>
                    </div>
                  </div>';
            include_once __DIR__ . '/../includes/footer.php';
            exit;
        }
    } else {
        if ($user_role !== $allowed_roles) {
            http_response_code(403);
            $page_title = 'Akses Ditolak';
            include_once __DIR__ . '/../includes/header.php';
            echo '<div class="container mt-5 text-center">
                    <div class="alert alert-danger p-5" style="border-radius:12px; background: rgba(255, 71, 87, 0.1); border: 1px solid var(--danger); color: var(--text-color)">
                        <i class="bi bi-exclamation-octagon-fill text-danger mb-3" style="font-size: 48px; display:block"></i>
                        <h4 class="alert-heading fw-bold text-danger">Akses Ditolak (403 Forbidden)</h4>
                        <p class="mb-4 text-muted">Anda tidak memiliki hak akses untuk membuka halaman ini.</p>
                        <a href="' . $path_prefix . 'pages/dashboard.php" class="btn btn-primary" style="background:var(--primary); border:none"><i class="bi bi-house-door-fill me-2"></i>Kembali ke Dashboard</a>
                    </div>
                  </div>';
            include_once __DIR__ . '/../includes/footer.php';
            exit;
        }
    }
}
?>
