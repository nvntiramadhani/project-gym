<?php
// api/members.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../helpers/auth_helper.php';
require_once '../helpers/csrf_helper.php';
require_once '../helpers/upload_helper.php';

// Proteksi akses API: hanya untuk Owner dan Admin
require_roles(['owner', 'admin'], '../');

// Validasi CSRF Token
verify_csrf_post_or_json();

$action = isset($_POST['action']) ? $_POST['action'] : '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $id > 0 ? 'update' : 'create';
}

try {
    if ($action === 'create') {
        // --- TAMBAH MEMBER BARU ---
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $birth_date = isset($_POST['birth_date']) ? trim($_POST['birth_date']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        
        $package_id = isset($_POST['package_id']) ? trim($_POST['package_id']) : '';
        $payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'pending';
        $trainer_id = isset($_POST['trainer_id']) && !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;

        // Validasi input wajib
        if (empty($name) || empty($email) || empty($phone) || empty($birth_date) || empty($gender) || empty($package_id)) {
            echo json_encode(['success' => false, 'message' => 'Mohon lengkapi semua field wajib!']);
            exit;
        }

        // Validasi format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Format email tidak valid!']);
            exit;
        }

        $pdo->beginTransaction();

        // Cek email duplikat (abaikan soft-deleted jika diperlukan, atau cek semua)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar!']);
            $pdo->rollBack();
            exit;
        }

        // Proses upload foto jika ada
        $photo_name = NULL;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload = upload_member_photo($_FILES['photo'], '../uploads/');
            if (!$upload['success']) {
                echo json_encode(['success' => false, 'message' => $upload['message']]);
                $pdo->rollBack();
                exit;
            }
            $photo_name = $upload['filename'];
        }

        // Generate ID Member (M001, M002, dst)
        $stmt = $pdo->query("SELECT member_code FROM members ORDER BY id DESC LIMIT 1");
        $last_code = $stmt->fetchColumn();
        if ($last_code) {
            $num = (int)substr($last_code, 1);
            $next_code = 'M' . sprintf('%03d', $num + 1);
        } else {
            $next_code = 'M001';
        }

        // Ambil info paket untuk durasi dan harga
        $stmt = $pdo->prepare("SELECT price, duration_months FROM packages WHERE id = ? AND status = 'aktif'");
        $stmt->execute([$package_id]);
        $package = $stmt->fetch();
        if (!$package) {
            echo json_encode(['success' => false, 'message' => 'Paket tidak aktif atau tidak ditemukan!']);
            $pdo->rollBack();
            exit;
        }

        // Simpan data member
        $join_date = date('Y-m-d');
        $stmt = $pdo->prepare("INSERT INTO members (member_code, name, photo, phone, address, birth_date, gender, join_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$next_code, $name, $photo_name, $phone, $address, $birth_date, $gender, $join_date]);
        $new_member_id = $pdo->lastInsertId();

        // Tentukan tanggal start dan end membership
        $start_date = NULL;
        $end_date = NULL;
        $m_status = 'pending';
        
        if ($payment_status === 'lunas') {
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime("+{$package['duration_months']} months"));
            $m_status = 'aktif';
        }

        // Simpan membership
        $stmt = $pdo->prepare("INSERT INTO memberships (member_id, package_id, start_date, end_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$new_member_id, $package_id, $start_date, $end_date, $m_status, $_SESSION['user_id']]);
        $new_membership_id = $pdo->lastInsertId();

        // Simpan transaksi pembayaran
        $stmt = $pdo->prepare("INSERT INTO payments (member_id, membership_id, amount, payment_date, payment_method, status, received_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$new_member_id, $new_membership_id, $package['price'], date('Y-m-d'), 'Cash', $payment_status, $_SESSION['user_id']]);

        // Simpan penugasan Personal Trainer jika dipilih
        if ($trainer_id) {
            $stmt = $pdo->prepare("INSERT INTO trainer_assignments (member_id, trainer_id, start_date, status, assigned_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$new_member_id, $trainer_id, date('Y-m-d'), 'aktif', $_SESSION['user_id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Anggota ' . $name . ' berhasil didaftarkan!', 'member_id' => $new_member_id]);

    } elseif ($action === 'update') {
        // --- EDIT DETAIL MEMBER ---
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $birth_date = isset($_POST['birth_date']) ? trim($_POST['birth_date']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $trainer_id = isset($_POST['trainer_id']) && !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;

        if (empty($id) || empty($name) || empty($email) || empty($phone) || empty($birth_date) || empty($gender)) {
            echo json_encode(['success' => false, 'message' => 'Mohon lengkapi semua data wajib!']);
            exit;
        }

        $pdo->beginTransaction();

        // Ambil data member saat ini untuk memproses file foto lama
        $stmt = $pdo->prepare("SELECT photo FROM members WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $current_member = $stmt->fetch();
        if (!$current_member) {
            echo json_encode(['success' => false, 'message' => 'Data anggota tidak ditemukan!']);
            $pdo->rollBack();
            exit;
        }

        // Cek email duplikat
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE email = ? AND id != ? AND deleted_at IS NULL");
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar oleh anggota lain!']);
            $pdo->rollBack();
            exit;
        }

        // Proses upload foto baru jika diunggah
        $photo_name = $current_member['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload = upload_member_photo($_FILES['photo'], '../uploads/');
            if (!$upload['success']) {
                echo json_encode(['success' => false, 'message' => $upload['message']]);
                $pdo->rollBack();
                exit;
            }
            // Hapus foto lama jika ada
            if ($photo_name && file_exists('../uploads/' . $photo_name)) {
                unlink('../uploads/' . $photo_name);
            }
            $photo_name = $upload['filename'];
        }

        // Update data profil member
        $stmt = $pdo->prepare("UPDATE members SET name = ?, email = ?, phone = ?, address = ?, birth_date = ?, gender = ?, photo = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $address, $birth_date, $gender, $photo_name, $id]);

        // Kelola penugasan trainer
        // Cari assignment aktif untuk member ini saat ini
        $stmt = $pdo->prepare("SELECT * FROM trainer_assignments WHERE member_id = ? AND status = 'aktif' LIMIT 1");
        $stmt->execute([$id]);
        $active_assignment = $stmt->fetch();

        if ($trainer_id) {
            // Jika trainer yang dipilih berbeda dari yang aktif sekarang
            if (!$active_assignment || (int)$active_assignment['trainer_id'] !== $trainer_id) {
                // Nonaktifkan assignment lama
                if ($active_assignment) {
                    $stmt = $pdo->prepare("UPDATE trainer_assignments SET status = 'selesai', end_date = ? WHERE id = ?");
                    $stmt->execute([date('Y-m-d'), $active_assignment['id']]);
                }
                // Tambahkan assignment baru
                $stmt = $pdo->prepare("INSERT INTO trainer_assignments (member_id, trainer_id, start_date, status, assigned_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id, $trainer_id, date('Y-m-d'), 'aktif', $_SESSION['user_id']]);
            }
        } else {
            // Jika trainer dikosongkan, nonaktifkan assignment aktif
            if ($active_assignment) {
                $stmt = $pdo->prepare("UPDATE trainer_assignments SET status = 'selesai', end_date = ? WHERE id = ?");
                $stmt->execute([date('Y-m-d'), $active_assignment['id']]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Data anggota ' . $name . ' berhasil diperbarui!']);

    } elseif ($action === 'delete') {
        // --- SOFT DELETE MEMBER ---
        $delete_id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if (empty($delete_id)) {
            echo json_encode(['success' => false, 'message' => 'ID Anggota tidak valid!']);
            exit;
        }

        // Lakukan soft-delete dengan menandai deleted_at
        $stmt = $pdo->prepare("UPDATE members SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$delete_id]);

        // Set status membership aktif menjadi dibatalkan
        $stmt = $pdo->prepare("UPDATE memberships SET status = 'dibatalkan' WHERE member_id = ? AND status IN ('pending', 'aktif')");
        $stmt->execute([$delete_id]);

        // Cek redirect jika diakses dari parameter GET (detail member)
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['redirect']) && $_GET['redirect'] == '1') {
            header("Location: ../pages/members.php");
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Anggota berhasil dihapus!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali!']);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Kesalahan sistem: ' . $e->getMessage()]);
}
?>
