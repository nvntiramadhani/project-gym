<?php
// helpers/upload_helper.php

function upload_member_photo($file, $target_dir = __DIR__ . '/../uploads/') {
    // Cek apakah file diupload
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Tidak ada file atau terjadi kesalahan unggah.'];
    }

    // Pastikan direktori tujuan ada
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // Batasi ukuran file (2MB)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Ukuran file foto melebihi batas maksimal 2MB.'];
    }

    // Validasi tipe MIME
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $file_info = getimagesize($file['tmp_name']);
    if (!$file_info) {
        return ['success' => false, 'message' => 'File yang diunggah bukan gambar valid.'];
    }
    
    $mime = $file_info['mime'];
    if (!in_array($mime, $allowed_types)) {
        return ['success' => false, 'message' => 'Format foto tidak valid. Hanya JPG, JPEG, dan PNG yang diperbolehkan.'];
    }

    // Dapatkan ekstensi file
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($ext)) {
        $ext = ($mime === 'image/png') ? 'png' : 'jpg';
    }

    // Nama file acak dan aman
    $filename = 'member_' . uniqid() . '.' . strtolower($ext);
    $target_file = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'filename' => $filename, 'path' => $target_file];
    } else {
        return ['success' => false, 'message' => 'Gagal memindahkan file ke direktori tujuan.'];
    }
}
?>
