<?php
// api/users.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/csrf_helper.php';

// Proteksi akses API: hanya untuk Owner
require_roles('owner', '../');

// Validasi CSRF Token
verify_csrf_post_or_json();

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    if ($action === 'create') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : 'admin';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'aktif';
        
        // Data Khusus Trainer
        $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            echo json_encode(['success' => false, 'message' => 'Mohon lengkapi data wajib!']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Format email tidak valid!']);
            exit;
        }

        $pdo->beginTransaction();

        // Cek email duplikat
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar!']);
            $pdo->rollBack();
            exit;
        }

        // Insert ke users
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashed_password, $role, $status]);
        $new_user_id = $pdo->lastInsertId();

        // Jika role adalah trainer, insert ke tabel trainers
        if ($role === 'trainer') {
            if (empty($specialization) || empty($phone)) {
                echo json_encode(['success' => false, 'message' => 'Spesialisasi dan telepon wajib diisi untuk Personal Trainer!']);
                $pdo->rollBack();
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO trainers (user_id, specialization, phone, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$new_user_id, $specialization, $phone, $status === 'aktif' ? 'aktif' : 'nonaktif']);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Akun pengguna baru berhasil dibuat!']);

    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'aktif';
        
        // Data Khusus Trainer
        $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

        if (empty($id) || empty($name) || empty($email) || empty($role)) {
            echo json_encode(['success' => false, 'message' => 'Mohon lengkapi data wajib!']);
            exit;
        }

        $pdo->beginTransaction();

        // Cek email duplikat
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar oleh pengguna lain!']);
            $pdo->rollBack();
            exit;
        }

        // Ambil data user saat ini untuk cek perubahan role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $old_role = $stmt->fetchColumn();

        // Update data dasar user
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $email, $hashed_password, $role, $status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $status, $id]);
        }

        // Kelola sub-profile trainers
        if ($role === 'trainer') {
            // Cek apakah ada record trainer sebelumnya
            $stmt = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
            $stmt->execute([$id]);
            $trainer_id = $stmt->fetchColumn();

            if ($trainer_id) {
                // Update trainers
                $stmt = $pdo->prepare("UPDATE trainers SET specialization = ?, phone = ?, status = ? WHERE id = ?");
                $stmt->execute([$specialization, $phone, $status === 'aktif' ? 'aktif' : 'nonaktif', $trainer_id]);
            } else {
                // Insert baru ke trainers
                $stmt = $pdo->prepare("INSERT INTO trainers (user_id, specialization, phone, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$id, $specialization, $phone, $status === 'aktif' ? 'aktif' : 'nonaktif']);
            }
        } else {
            // Jika role diubah dari trainer ke role lain, nonaktifkan sub-profile trainer
            if ($old_role === 'trainer') {
                $stmt = $pdo->prepare("UPDATE trainers SET status = 'nonaktif' WHERE user_id = ?");
                $stmt->execute([$id]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Akun pengguna berhasil diperbarui!']);

    } elseif ($action === 'toggle_status') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid!']);
            exit;
        }

        // Mencegah Owner menonaktifkan dirinya sendiri
        if ($id === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak bisa menonaktifkan akun Anda sendiri!']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT status, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan!']);
            $pdo->rollBack();
            exit;
        }

        $new_status = $user['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        
        // Update user status
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);

        // Jika trainer, update juga status trainernya
        if ($user['role'] === 'trainer') {
            $stmt = $pdo->prepare("UPDATE trainers SET status = ? WHERE user_id = ?");
            $stmt->execute([$new_status === 'aktif' ? 'aktif' : 'nonaktif', $id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Status akun berhasil diubah menjadi ' . $new_status . '!']);
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
