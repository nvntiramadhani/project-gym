<?php
require_once 'config/database.php';

// index.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'helpers/csrf_helper.php';

// Jika user sudah login, langsung alihkan ke dashboard
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header("Location: pages/dashboard.php");
    exit;
}

$stmt = $pdo->query("SELECT COUNT(*) FROM members WHERE deleted_at IS NULL");
$total_members = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("SELECT COUNT(*) FROM trainers WHERE status = 'aktif'");
$total_trainers = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("SELECT COUNT(*) FROM packages WHERE status = 'aktif'");
$total_packages = $stmt->fetchColumn() ?: 0;

$is_login_page = true;
$page_title = 'Login';
$body_class = 'login-page';
$path_prefix = '';
include 'includes/header.php';
?>
    <div class="login-wrapper">
        <!-- Left Side - Image -->
        <div class="login-image-side">
            <img src="./assets/images/banner2.png" alt="Gym Interior" id="loginImage">
            <video id="loginVideo" loop muted playsinline preload="auto">
                <source src="./assets/images/gym.mp4" type="video/mp4">
            </video>
            <div class="login-image-overlay">
                <div class="login-image-content">
                    <h2>Novi Prime Athletics</h2>
                    <p>Sistem manajemen membership gym.</p>
                    <div class="login-image-stats">
                        <div class="img-stat">
                            <span class="img-stat-value"><?php echo $total_members; ?></span>
                            <span class="img-stat-label">Member Aktif</span>
                        </div>
                        <div class="img-stat">
                            <span class="img-stat-value"><?php echo $total_trainers; ?></span>
                            <span class="img-stat-label">Trainer Pro</span>
                        </div>
                        <div class="img-stat">
                            <span class="img-stat-value"><?php echo $total_packages; ?></span>
                            <span class="img-stat-label">Paket</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-form-side">
            <div class="login-card">
                <div class="login-logo">
                    <div class="logo-icon">
                        <img src="./assets/images/logo_gym.png" alt="Logo Novi Prime" class="logo-img">
                    </div>
                    <h1>Selamat Datang!</h1>
                    <p>Masuk ke dashboard manajemen gym Anda</p>
                </div>
                <form id="loginForm">
                    <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="loginEmail">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="loginEmail" placeholder="Masukkan email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="loginPassword">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="loginPassword" placeholder="Masukkan password" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rememberMe">
                            <label class="form-check-label" for="rememberMe">Ingat saya</label>
                        </div>
                        <a href="#" class="forgot-link" onclick="alert('Fitur reset password silakan hubungi owner.')">Lupa password?</a>
                    </div>
                    <button type="submit" class="btn btn-login" id="btnLogin">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
                    </button>
                </form>
                <div class="login-demo-info">
                    <p>Demo Credentials (Password: <code>password</code>)</p>
                    <div style="font-size:11px; text-align:left; padding-left:10px">
                        Owner: <code>owner@gym.com</code><br>
                        Admin: <code>admin@gym.com</code><br>
                        Trainer: <code>rudikurniawan@gym.com</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
(function() {
    const emailInput = document.getElementById('loginEmail');
    const passInput = document.getElementById('loginPassword');
    const loginImage = document.getElementById('loginImage');
    const loginVideo = document.getElementById('loginVideo');

    if (!emailInput || !passInput || !loginImage || !loginVideo) return;

    let isVideoActive = false;

    function activateVideo() {
        if (isVideoActive) return;
        isVideoActive = true;
        loginVideo.play();
        loginVideo.classList.add('active');
        loginImage.classList.add('hidden');
    }

    function deactivateVideo() {
        // Hanya nonaktifkan jika kedua input tidak aktif dan kosong
        if (document.activeElement === emailInput || document.activeElement === passInput) return;
        if (emailInput.value.trim() !== '' || passInput.value.trim() !== '') return;
        isVideoActive = false;
        loginVideo.classList.remove('active');
        loginImage.classList.remove('hidden');
        setTimeout(function() {
            if (!isVideoActive) loginVideo.pause();
        }, 600);
    }

    emailInput.addEventListener('focus', activateVideo);
    passInput.addEventListener('focus', activateVideo);
    emailInput.addEventListener('input', activateVideo);
    passInput.addEventListener('input', activateVideo);
    emailInput.addEventListener('blur', function() { setTimeout(deactivateVideo, 100); });
    passInput.addEventListener('blur', function() { setTimeout(deactivateVideo, 100); });
})();
</script>
<?php
include 'includes/footer.php';
?>
