<?php
// config/database.php

$host = 'localhost';
$db   = 'novi_gym';
$user = 'root';
$pass = ''; // Default password untuk XAMPP/Laragon
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Jangan tampilkan detail error mentah ke user jika di produksi
     die("Koneksi database gagal: " . $e->getMessage());
}
?>
