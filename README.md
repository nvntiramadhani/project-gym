## project gym

Nama: Novianti Ramadhani

NIM : (24260011)

PRODI: Teknik Informatika

===================================================

# Sistem Manajemen Gym - Novi Prime Athletics (Dynamic Version)

Sistem informasi manajemen keanggotaan gym "Novi Prime Athletics" yang diubah dari halaman statis menjadi web dinamis menggunakan **PHP (PDO)** dan basis data **MySQL**. Antarmuka dikembangkan secara dinamis dengan AJAX Fetch API untuk menjaga performa cepat, visual premium, dan notifikasi interaktif tanpa reload halaman.

## Teknologi yang Digunakan
- **Backend/Logic**: PHP 8.x (dengan standard keamanan database PDO)
- **Database**: MySQL
- **Frontend**: HTML5, Vanilla CSS, Bootstrap 5.3.3, Bootstrap Icons
- **Visualisasi Grafik**: Chart.js
- **Interaksi Data**: Vanilla Javascript AJAX (Fetch API)

---

## Persyaratan Sistem
- Web Server Lokal (sangat direkomendasikan menggunakan **Laragon** atau **XAMPP**).
- PHP Versi 8.0 atau yang lebih baru.
- Layanan database MySQL / MariaDB aktif.

---

## Struktur Folder Proyek
```text
project-gym-main/
├── api/                     # AJAX API PHP (Login, Member, Presensi, Data Fisik)
├── assets/                  # File Gambar dan Logo
├── config/                  # Pengaturan Koneksi Database
│   └── database.php
├── css/                     # Custom Styling CSS utama
├── database/                # Skema Database SQL (.sql)
├── includes/                # Template bersama (Header, Sidebar, Topbar, Footer)
├── pages/                   # Halaman Utama Aplikasi (.php)
│   ├── dashboard.php
│   ├── members.php
│   ├── member-detail.php
│   ├── packages.php
│   ├── attendance.php
│   └── trainers.php
├── index.php                # Halaman Login Admin (Entrypoint di root)
├── logout.php               # Script Logout
├── setup.bat                # Script otomatis untuk impor database
└── README.md
```

---

## Tata Cara Deploy & Setup Lokal

### Langkah 1: Pindahkan Folder Proyek
Pindahkan seluruh folder `project-gym-main` ke direktori root web server lokal Anda:
- **Laragon**: `C:\laragon\www\`
- **XAMPP**: `C:\xampp\htdocs\`

### Langkah 2: Setup Database (Pilih Salah Satu Cara)

#### Cara A: Menggunakan Script Otomatis (Sangat Mudah)
1. Pastikan program Laragon Anda sudah dibuka dan tombol **Start All** sudah diklik (MySQL berjalan).
2. Buka folder proyek Anda di File Explorer: `C:\laragon\www\project-gym-main\`.
3. Klik ganda (double-click) file **`setup.bat`**.
4. Script akan secara otomatis membuat database `novi_gym` dan mengimpor tabel serta data contohnya.

#### Cara B: Menggunakan phpMyAdmin (Manual)
1. Buka browser dan pergi ke `http://localhost/phpmyadmin`.
2. Buat database baru dengan nama `novi_gym`.
3. Klik database `novi_gym` tersebut, lalu pilih tab **Import**.
4. Pilih file database yang terletak di `project-gym-main/database/schema.sql`.
5. Klik **Go / Kirim** di bagian bawah halaman.

### Langkah 3: Konfigurasi Database (Opsional)
Buka file `config/database.php` menggunakan text editor Anda. Jika MySQL server lokal Anda menggunakan password (bukan default kosong), sesuaikan isinya pada variabel `$pass`:
```php
$user = 'root';
$pass = 'PASSWORD_MYSQL_ANDA'; // Default Laragon/XAMPP adalah kosong ''
```

### Langkah 4: Buka Aplikasi
1. Buka browser Anda.
2. Akses alamat berikut:
   `http://localhost/project-gym-main/index.php`
3. Masuk menggunakan akun admin demo:
   - **Username**: `admin`
   - **Password**: `admin123`
