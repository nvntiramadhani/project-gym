<?php
// database/migrate.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

echo "=== MEMULAI MIGRASI DATABASE NOVI GYM ===\n\n";

try {

    // 1. Cek apakah tabel backup sudah ada
    // Jika belum, backup tabel lama yang ada
    $tables = ['users', 'members', 'packages', 'trainers', 'physical_measurements', 'attendance', 'pt_appointments'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "Membuat cadangan untuk tabel '$table'...\n";
            $pdo->exec("DROP TABLE IF EXISTS backup_$table");
            $pdo->exec("CREATE TABLE backup_$table SELECT * FROM $table");
        }
    }

    // Nonaktifkan check foreign key sementara
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Drop tabel lama agar bisa dibuat ulang dengan struktur baru
    $pdo->exec("DROP TABLE IF EXISTS `pt_appointments`");
    $pdo->exec("DROP TABLE IF EXISTS `attendance`");
    $pdo->exec("DROP TABLE IF EXISTS `attendances`");
    $pdo->exec("DROP TABLE IF EXISTS `physical_measurements`");
    $pdo->exec("DROP TABLE IF EXISTS `physical_progress`");
    $pdo->exec("DROP TABLE IF EXISTS `members`");
    $pdo->exec("DROP TABLE IF EXISTS `packages`");
    $pdo->exec("DROP TABLE IF EXISTS `trainers`");
    $pdo->exec("DROP TABLE IF EXISTS `users`");
    $pdo->exec("DROP TABLE IF EXISTS `memberships`");
    $pdo->exec("DROP TABLE IF EXISTS `payments`");
    $pdo->exec("DROP TABLE IF EXISTS `trainer_assignments`");
    $pdo->exec("DROP TABLE IF EXISTS `workout_programs`");

    echo "\nTabel lama berhasil dibersihkan.\n";

    // 2. Membuat tabel users
    echo "Membuat tabel 'users'...\n";
    $pdo->exec("CREATE TABLE `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('owner', 'admin', 'trainer') NOT NULL,
        `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. Membuat tabel members
    echo "Membuat tabel 'members'...\n";
    $pdo->exec("CREATE TABLE `members` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_code` VARCHAR(50) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `photo` VARCHAR(255) DEFAULT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `address` TEXT DEFAULT NULL,
        `birth_date` DATE DEFAULT NULL,
        `gender` ENUM('L', 'P') DEFAULT NULL,
        `join_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. Membuat tabel packages
    echo "Membuat tabel 'packages'...\n";
    $pdo->exec("CREATE TABLE `packages` (
        `id` VARCHAR(50) PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `price` INT NOT NULL,
        `duration_months` INT NOT NULL DEFAULT 1,
        `description` TEXT NOT NULL,
        `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. Membuat tabel memberships
    echo "Membuat tabel 'memberships'...\n";
    $pdo->exec("CREATE TABLE `memberships` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_id` INT NOT NULL,
        `package_id` VARCHAR(50) NOT NULL,
        `start_date` DATE NULL,
        `end_date` DATE NULL,
        `status` ENUM('pending', 'aktif', 'expired', 'dibatalkan') DEFAULT 'pending',
        `created_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. Membuat tabel payments
    echo "Membuat tabel 'payments'...\n";
    $pdo->exec("CREATE TABLE `payments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_id` INT NOT NULL,
        `membership_id` INT NOT NULL,
        `amount` INT NOT NULL,
        `payment_date` DATE NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `status` ENUM('pending', 'lunas', 'gagal', 'dibatalkan') DEFAULT 'pending',
        `proof` VARCHAR(255) DEFAULT NULL,
        `received_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`membership_id`) REFERENCES `memberships` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 7. Membuat tabel trainers
    echo "Membuat tabel 'trainers'...\n";
    $pdo->exec("CREATE TABLE `trainers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNIQUE NOT NULL,
        `specialization` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `status` ENUM('aktif', 'cuti', 'nonaktif') DEFAULT 'aktif',
        `experience_years` INT DEFAULT 0,
        `rating` DECIMAL(2,1) DEFAULT 0.0,
        `certifications` TEXT DEFAULT NULL,
        `schedule_days` VARCHAR(100) DEFAULT NULL,
        `schedule_time` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 8. Membuat tabel trainer_assignments
    echo "Membuat tabel 'trainer_assignments'...\n";
    $pdo->exec("CREATE TABLE `trainer_assignments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_id` INT NOT NULL,
        `trainer_id` INT NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NULL,
        `status` ENUM('aktif', 'selesai', 'dibatalkan') DEFAULT 'aktif',
        `assigned_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 9. Membuat tabel attendances
    echo "Membuat tabel 'attendances'...\n";
    $pdo->exec("CREATE TABLE `attendances` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_id` INT NOT NULL,
        `date` DATE NOT NULL,
        `check_in` TIME NOT NULL,
        `check_out` TIME DEFAULT NULL,
        `recorded_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 10. Membuat tabel physical_progress
    echo "Membuat tabel 'physical_progress'...\n";
    $pdo->exec("CREATE TABLE `physical_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_id` INT NOT NULL,
        `trainer_id` INT NULL,
        `measurement_date` DATE NOT NULL,
        `weight` INT NOT NULL,
        `height` INT NOT NULL,
        `bmi` DECIMAL(4,2) NOT NULL,
        `body_fat` DECIMAL(4,1) NOT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 11. Membuat tabel workout_programs
    echo "Membuat tabel 'workout_programs'...\n";
    $pdo->exec("CREATE TABLE `workout_programs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_id` INT NOT NULL,
        `trainer_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `target` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `status` ENUM('aktif', 'selesai', 'dibatalkan') DEFAULT 'aktif',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 12. Membuat tabel pt_appointments (untuk mempertahankan fitur jadwal sesi PT yang sudah ada)
    echo "Membuat tabel 'pt_appointments'...\n";
    $pdo->exec("CREATE TABLE `pt_appointments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_id` INT NOT NULL,
        `appointment_date` DATE NOT NULL,
        `appointment_time` VARCHAR(100) NOT NULL,
        `topic` VARCHAR(255) NOT NULL,
        `status` VARCHAR(50) DEFAULT 'Terjadwal',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Aktifkan kembali check foreign key
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "\nStruktur database baru berhasil dibuat.\n\n";

    // ===================================================
    // MIGRASI DATA LAMA KE TABEL BARU
    // ===================================================
    echo "=== MEMULAI PROSES MIGRASI DATA ===\n";

    // 1. Tambah Akun Default Owner dan Admin
    $owner_pass = password_hash('password', PASSWORD_DEFAULT);
    $admin_pass = password_hash('password', PASSWORD_DEFAULT);
    $trainer_default_pass = password_hash('password', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Owner Manajer', 'owner@gym.com', $owner_pass, 'owner', 'aktif']);
    $owner_id = $pdo->lastInsertId();
    echo "Akun Owner disematkan: owner@gym.com\n";

    $stmt->execute(['Admin Resepsionis', 'admin@gym.com', $admin_pass, 'admin', 'aktif']);
    $admin_id = $pdo->lastInsertId();
    echo "Akun Admin disematkan: admin@gym.com\n";

    // 2. Migrasi Packages
    echo "Migrasi paket membership...\n";
    $old_pkg_stmt = $pdo->query("SELECT * FROM backup_packages");
    $old_packages = $old_pkg_stmt->fetchAll();
    $pkg_stmt = $pdo->prepare("INSERT INTO packages (id, name, price, duration_months, description, status) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($old_packages as $op) {
        // Tentukan durasi dalam bulan. Default 1 bulan
        $duration = isset($op['duration_months']) ? $op['duration_months'] : 1;
        $description = isset($op['features']) ? $op['features'] : ($op['description'] ?? '');
        $pkg_stmt->execute([$op['id'], $op['name'], $op['price'], $duration, $description, 'aktif']);
    }
    echo "Paket membership dimigrasikan: " . count($old_packages) . " paket.\n";

    // 3. Migrasi Trainers (Membuat user login untuk trainer lama)
    echo "Migrasi data personal trainer...\n";
    $old_trainer_stmt = $pdo->query("SELECT * FROM backup_trainers");
    $old_trainers = $old_trainer_stmt->fetchAll();

    $user_stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $new_trainer_stmt = $pdo->prepare("INSERT INTO trainers (id, user_id, specialization, phone, status, experience_years, rating, certifications, schedule_days, schedule_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
        // Map untuk menyimpan ID trainer lama -> baru
        $trainer_id_map = [];
    
        foreach ($old_trainers as $ot) {
            // Generate email berdasarkan inisial atau nama
            $email_prefix = strtolower(str_replace(' ', '', $ot['name']));
            $email = $email_prefix . '@gym.com';
            
            // Simpan user baru
            $user_stmt->execute([$ot['name'], $email, $trainer_default_pass, 'trainer', 'aktif']);
            $new_user_id = $pdo->lastInsertId();
            
            // Simpan trainer baru dengan ID yang sama agar relasinya tidak rusak
            $status = $ot['status'] == 'aktif' ? 'aktif' : 'cuti';
            $specialization = $ot['specialty'];
            $phone = '081234567' . $ot['id']; // dummy phone
            
            $experience_years = isset($ot['experience_years']) ? $ot['experience_years'] : 0;
            $rating = isset($ot['rating']) ? $ot['rating'] : 0.0;
            $certifications = isset($ot['certifications']) ? $ot['certifications'] : NULL;
            $schedule_days = isset($ot['schedule_days']) ? $ot['schedule_days'] : NULL;
            $schedule_time = isset($ot['schedule_time']) ? $ot['schedule_time'] : NULL;
            
            $new_trainer_stmt->execute([
                $ot['id'], $new_user_id, $specialization, $phone, $status,
                $experience_years, $rating, $certifications, $schedule_days, $schedule_time
            ]);
            $new_trainer_db_id = $pdo->lastInsertId();
        $trainer_id_map[$ot['id']] = $new_trainer_db_id;
        
        echo "Trainer '{$ot['name']}' dimigrasikan dengan akun: $email\n";
    }

    // 4. Migrasi Members
    echo "Migrasi data keanggotaan...\n";
    $old_member_stmt = $pdo->query("SELECT * FROM backup_members");
    $old_members = $old_member_stmt->fetchAll();

    $member_insert = $pdo->prepare("INSERT INTO members (member_code, name, email, photo, phone, address, birth_date, gender, join_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $membership_insert = $pdo->prepare("INSERT INTO memberships (member_id, package_id, start_date, end_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $payment_insert = $pdo->prepare("INSERT INTO payments (member_id, membership_id, amount, payment_date, payment_method, status, received_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $assignment_insert = $pdo->prepare("INSERT INTO trainer_assignments (member_id, trainer_id, start_date, status, assigned_by) VALUES (?, ?, ?, ?, ?)");

    // Map untuk menyimpan member_code -> new_member_id (INT)
    $member_id_map = [];

    foreach ($old_members as $om) {
        // Tentukan data tambahan default
        $photo = NULL;
        $birth_date = date('Y-m-d', strtotime('-25 years')); // dummy
        $gender = ($om['id'] === 'M002' || $om['id'] === 'M004' || $om['id'] === 'M006') ? 'P' : 'L'; // sample mapping
        $email = isset($om['email']) ? $om['email'] : null;
        
        // Simpan member
        $member_insert->execute([$om['id'], $om['name'], $email, $photo, $om['phone'], $om['address'], $birth_date, $gender, $om['join_date']]);
        $new_member_id = $pdo->lastInsertId();
        $member_id_map[$om['id']] = $new_member_id;

        // Buat data membership
        $start_date = $om['join_date'];
        $end_date = $om['expiry_date'];
        $m_status = $om['status'] == 'aktif' ? 'aktif' : 'expired';
        
        // Dapatkan harga paket
        $p_price_stmt = $pdo->prepare("SELECT price FROM packages WHERE id = ?");
        $p_price_stmt->execute([$om['package_id']]);
        $price = $p_price_stmt->fetchColumn() ?: 200000;

        $membership_insert->execute([$new_member_id, $om['package_id'], $start_date, $end_date, $m_status, $admin_id]);
        $new_membership_id = $pdo->lastInsertId();

        // Buat data pembayaran lunas untuk membership ini
        $payment_insert->execute([$new_member_id, $new_membership_id, $price, $start_date, 'Cash', 'lunas', $admin_id]);

        // Buat penugasan trainer jika ada
        if (!empty($om['trainer_id'])) {
            $assignment_insert->execute([$new_member_id, $om['trainer_id'], $om['join_date'], 'aktif', $admin_id]);
        }
    }
    echo "Members dimigrasikan: " . count($old_members) . " orang.\n";

    // 5. Migrasi Attendance (Presensi)
    echo "Migrasi data presensi...\n";
    $old_att_stmt = $pdo->query("SELECT * FROM backup_attendance");
    $old_attendances = $old_att_stmt->fetchAll();

    $att_insert = $pdo->prepare("INSERT INTO attendances (member_id, date, check_in, check_out, recorded_by) VALUES (?, ?, ?, ?, ?)");
    $migrated_att = 0;
    foreach ($old_attendances as $oa) {
        if (isset($member_id_map[$oa['member_id']])) {
            $new_m_id = $member_id_map[$oa['member_id']];
            $att_insert->execute([$new_m_id, $oa['date'], $oa['check_in'], $oa['check_out'], $admin_id]);
            $migrated_att++;
        }
    }
    echo "Data presensi berhasil dimigrasikan: $migrated_att log.\n";

    // 6. Migrasi Physical Measurements
    echo "Migrasi data pengukuran fisik...\n";
    $old_phys_stmt = $pdo->query("SELECT * FROM backup_physical_measurements");
    $old_phys = $old_phys_stmt->fetchAll();

    $phys_insert = $pdo->prepare("INSERT INTO physical_progress (member_id, trainer_id, measurement_date, weight, height, bmi, body_fat, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $migrated_phys = 0;
    foreach ($old_phys as $op) {
        if (isset($member_id_map[$op['member_id']])) {
            $new_m_id = $member_id_map[$op['member_id']];
            
            // Cari trainer binaan dari member saat ini
            $t_stmt = $pdo->prepare("SELECT trainer_id FROM trainer_assignments WHERE member_id = ? AND status = 'aktif' LIMIT 1");
            $t_stmt->execute([$new_m_id]);
            $trainer_id = $t_stmt->fetchColumn() ?: NULL;

            $phys_insert->execute([$new_m_id, $trainer_id, $op['date'], $op['weight'], $op['height'], $op['bmi'], $op['body_fat'], $op['notes']]);
            $migrated_phys++;
        }
    }
    echo "Data fisik berhasil dimigrasikan: $migrated_phys log.\n";

    // 7. Migrasi PT Appointments
    echo "Migrasi janji temu PT...\n";
    $old_appt_stmt = $pdo->query("SELECT * FROM backup_pt_appointments");
    $old_appts = $old_appt_stmt->fetchAll();

    $appt_insert = $pdo->prepare("INSERT INTO pt_appointments (member_id, appointment_date, appointment_time, topic, status) VALUES (?, ?, ?, ?, ?)");
    $migrated_appt = 0;
    foreach ($old_appts as $oap) {
        if (isset($member_id_map[$oap['member_id']])) {
            $new_m_id = $member_id_map[$oap['member_id']];
            $appt_insert->execute([$new_m_id, $oap['appointment_date'], $oap['appointment_time'], $oap['topic'], $oap['status']]);
            $migrated_appt++;
        }
    }
    echo "Data janji temu PT berhasil dimigrasikan: $migrated_appt janji.\n";

    echo "\n=== MIGRASI BERHASIL SELESAI DENGAN SUKSES! ===\n";

} catch (Exception $e) {
    echo "\n!!! ERROR MIGRASI TERJADI !!!\n";
    echo "Pesan: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>
