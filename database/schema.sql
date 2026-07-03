CREATE DATABASE IF NOT EXISTS `novi_gym`;
USE `novi_gym`;

-- 1. Table users (Admin)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'Administrator'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Table packages
DROP TABLE IF EXISTS `packages`;
CREATE TABLE `packages` (
  `id` VARCHAR(50) PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `price` INT NOT NULL,
  `period` VARCHAR(50) DEFAULT 'per bulan',
  `features` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Table trainers
DROP TABLE IF EXISTS `trainers`;
CREATE TABLE `trainers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `initials` VARCHAR(5) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `specialty` VARCHAR(255) NOT NULL,
  `status` ENUM('aktif', 'cuti') DEFAULT 'aktif',
  `clients_count` INT DEFAULT 0,
  `experience_years` INT DEFAULT 0,
  `rating` DECIMAL(2,1) DEFAULT 0.0,
  `certifications` TEXT,
  `schedule_days` VARCHAR(100),
  `schedule_time` VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Table members
DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` VARCHAR(50) PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `address` TEXT,
  `package_id` VARCHAR(50) NOT NULL,
  `status` ENUM('aktif', 'expired') DEFAULT 'aktif',
  `join_date` DATE NOT NULL,
  `expiry_date` DATE NOT NULL,
  `trainer_id` INT DEFAULT NULL,
  `pt_total_sessions` INT DEFAULT 0,
  `pt_remaining_sessions` INT DEFAULT 0,
  `pt_schedule_days` VARCHAR(100) DEFAULT NULL,
  `pt_schedule_time` VARCHAR(100) DEFAULT NULL,
  FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON UPDATE CASCADE,
  FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Table physical_measurements
DROP TABLE IF EXISTS `physical_measurements`;
CREATE TABLE `physical_measurements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` VARCHAR(50) NOT NULL,
  `date` DATE NOT NULL,
  `height` INT NOT NULL,
  `weight` INT NOT NULL,
  `bmi` DECIMAL(4,2) NOT NULL,
  `body_fat` DECIMAL(4,1) NOT NULL,
  `notes` TEXT,
  FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Table attendance
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` VARCHAR(50) NOT NULL,
  `date` DATE NOT NULL,
  `check_in` TIME NOT NULL,
  `check_out` TIME DEFAULT NULL,
  FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Table pt_appointments
DROP TABLE IF EXISTS `pt_appointments`;
CREATE TABLE `pt_appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` VARCHAR(50) NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` VARCHAR(100) NOT NULL,
  `topic` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'Terjadwal',
  FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- INSERT SEED DATA
-- ==========================================

-- Admin User (admin / admin123)
INSERT INTO `users` (`username`, `password`, `name`, `role`) VALUES
('admin', '$2y$10$TIAqT2q9TV/hmkf/jNNDMOCUfE0fcebSbxYyb.sixMUqRW35rIC0.', 'Admin', 'Administrator');

-- Membership Packages
INSERT INTO `packages` (`id`, `name`, `price`, `period`, `features`) VALUES
('silver', 'Silver', 200000, 'per bulan', 'Akses gym area,Locker harian,Air minum gratis,Jam operasional standar'),
('gold', 'Gold', 350000, 'per bulan', 'Akses gym area,Locker permanen,Air minum gratis,Jam operasional penuh,4x sesi Personal Trainer'),
('platinum', 'Platinum', 500000, 'per bulan', 'Akses gym area,Locker premium,Air & handuk gratis,Akses 24 jam,8x sesi Personal Trainer,Semua kelas grup');

-- Trainers
INSERT INTO `trainers` (`id`, `initials`, `name`, `specialty`, `status`, `clients_count`, `experience_years`, `rating`, `certifications`, `schedule_days`, `schedule_time`) VALUES
(1, 'RK', 'Rudi Kurniawan', 'Strength Training & Body Building', 'aktif', 12, 5, 4.8, 'ISSA CPT', 'Sen-Jum', '06:00 - 15:00'),
(2, 'MA', 'Maya Anggraini', 'Yoga & Pilates', 'aktif', 18, 7, 4.9, 'RYT-200', 'Sen-Sab', '07:00 - 16:00'),
(3, 'DA', 'Denny Arta', 'Cardio & HIIT', 'aktif', 15, 4, 4.7, 'ACE CPT', 'Sel-Sab', '08:00 - 17:00'),
(4, 'LS', 'Linda Susanti', 'Functional Training & CrossFit', 'aktif', 10, 3, 4.6, 'CrossFit L2', 'Sen-Jum', '09:00 - 18:00'),
(5, 'BW', 'Bagus Wicaksono', 'Weight Loss & Nutrition', 'cuti', 8, 6, 4.8, 'NASM CPT', 'Sen-Sab (cuti)', '-');

-- Members
INSERT INTO `members` (`id`, `name`, `email`, `phone`, `address`, `package_id`, `status`, `join_date`, `expiry_date`, `trainer_id`, `pt_total_sessions`, `pt_remaining_sessions`, `pt_schedule_days`, `pt_schedule_time`) VALUES
('M001', 'Budi Santoso', 'budi@email.com', '081234567890', 'Jl. Merdeka No. 10', 'gold', 'aktif', '2026-01-15', '2026-07-15', 1, 12, 3, 'Sen, Rab, Jum', '07:00 - 08:00'),
('M002', 'Rina Wati', 'rina@email.com', '081234567891', 'Jl. Sudirman No. 5', 'platinum', 'aktif', '2026-02-20', '2026-08-20', NULL, 0, 0, NULL, NULL),
('M003', 'Ahmad Surya', 'ahmad@email.com', '081234567892', 'Jl. Gatot Subroto No. 8', 'silver', 'expired', '2026-03-05', '2026-04-05', NULL, 0, 0, NULL, NULL),
('M004', 'Dewi Lestari', 'dewi@email.com', '081234567893', 'Jl. Diponegoro No. 3', 'gold', 'aktif', '2026-03-10', '2026-09-10', NULL, 0, 0, NULL, NULL),
('M005', 'Fajar Hidayat', 'fajar@email.com', '081234567894', 'Jl. Ahmad Yani No. 12', 'platinum', 'aktif', '2026-04-01', '2026-10-01', NULL, 0, 0, NULL, NULL),
('M006', 'Siti Permata', 'siti@email.com', '081234567895', 'Jl. Pahlawan No. 7', 'silver', 'aktif', '2026-04-08', '2026-05-08', NULL, 0, 0, NULL, NULL),
('M007', 'Hendra Rahmat', 'hendra@email.com', '081234567896', 'Jl. Kartini No. 15', 'gold', 'expired', '2026-01-12', '2026-02-12', NULL, 0, 0, NULL, NULL),
('M008', 'Yoga Pratama', 'yoga@email.com', '081234567897', 'Jl. Pemuda No. 22', 'silver', 'aktif', '2026-04-18', '2026-05-18', NULL, 0, 0, NULL, NULL),
('M009', 'Andi Rahman', 'andi@email.com', '081234567898', 'Jl. Kebon Jeruk No. 4', 'silver', 'aktif', '2026-04-15', '2026-05-15', NULL, 0, 0, NULL, NULL);

-- Physical Measurements (Budi Santoso - M001)
INSERT INTO `physical_measurements` (`member_id`, `date`, `height`, `weight`, `bmi`, `body_fat`, `notes`) VALUES
('M001', '2026-01-15', 175, 78, 25.50, 22.0, 'Pengukuran awal'),
('M001', '2026-02-27', 175, 76, 24.80, 20.8, 'Awal program diet'),
('M001', '2026-03-27', 175, 74, 24.20, 19.5, 'Perlu kurangi karbo'),
('M001', '2026-04-27', 175, 72, 23.50, 18.2, 'Progres bagus');

-- Attendance Logs (27 April 2026)
INSERT INTO `attendance` (`member_id`, `date`, `check_in`, `check_out`) VALUES
('M001', '2026-04-27', '06:30:00', '08:15:00'),
('M002', '2026-04-27', '07:00:00', '08:30:00'),
('M004', '2026-04-27', '08:00:00', '09:30:00'),
('M005', '2026-04-27', '09:00:00', '10:45:00'),
('M006', '2026-04-27', '10:15:00', '11:45:00'),
('M008', '2026-04-27', '14:30:00', NULL),
('M009', '2026-04-27', '15:00:00', NULL);

-- PT Appointments (Budi Santoso - M001)
INSERT INTO `pt_appointments` (`member_id`, `appointment_date`, `appointment_time`, `topic`, `status`) VALUES
('M001', '2026-04-28', '07:00 - 08:00', 'Strength Training', 'Terjadwal'),
('M001', '2026-04-30', '07:00 - 08:00', 'Cardio & Core', 'Terjadwal'),
('M001', '2026-05-02', '07:00 - 08:00', 'Full Body', 'Terjadwal');
