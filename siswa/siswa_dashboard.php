<?php
// Tentukan zona waktu
date_default_timezone_set('Asia/Jakarta');

// ====================================================================
// 0. KONFIGURASI DAN OTENTIKASI
// ====================================================================
session_start();

// Cek apakah user sudah login dan perannya adalah Siswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Siswa') {
    // Jika belum login atau bukan siswa, redirect ke halaman login
    header('Location: login.php');
    exit;
}

// Data Siswa yang sudah ada di sesi
$nis_siswa = $_SESSION['nis'] ?? null;
$nama_siswa = $_SESSION['user_name'] ?? 'Siswa';
$kelas_siswa = $_SESSION['kelas'] ?? 'N/A';

// Konfigurasi koneksi database (Sama dengan login.php)
$host = 'localhost';
$user = 'root';
$password = ''; 
$dbname = 'smkjt1'; 
const DB_TABLE_SISWA = 'akunsiswa'; 

// Fungsi koneksi database (diambil dari login.php)
function connectDB($host, $user, $password, $dbname) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false, 
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

$pdo = connectDB($host, $user, $password, $dbname);
$data_siswa_lengkap = [];

// ====================================================================
// 1. AMBIL DATA SISWA LENGKAP DARI DATABASE
//    Mengambil NIS, Nama Lengkap, Kelas, dan Jurusan (EMAIL DIHILANGKAN)
// ====================================================================
if ($pdo && $nis_siswa) {
    try {
        // PERBAIKAN: Hapus 'email' dari SELECT statement
        $stmt = $pdo->prepare("SELECT nis, nama_lengkap, kelas, jurusan FROM " . DB_TABLE_SISWA . " WHERE nis = ?");
        $stmt->execute([$nis_siswa]);
        $data_siswa_lengkap = $stmt->fetch();
        
        // Update data sesi jika data lengkap berhasil diambil
        if ($data_siswa_lengkap) {
             $_SESSION['kelas'] = $data_siswa_lengkap['kelas'];
             // Mengisi nilai default jika kolom 'jurusan' kosong (walaupun harusnya ada)
             $data_siswa_lengkap['jurusan'] = $data_siswa_lengkap['jurusan'] ?? 'Belum Ditentukan';
        }

    } catch (PDOException $e) {
        error_log("Error fetching student data: " . $e->getMessage());
        // Handle error (bisa menampilkan pesan ke user)
        $data_siswa_lengkap = [
            'nis' => $nis_siswa,
            'nama_lengkap' => $nama_siswa,
            'kelas' => $kelas_siswa,
            'jurusan' => 'Gagal Ambil Data', // Nilai default saat error
            'error_message' => 'Gagal mengambil data lengkap siswa dari database (Cek koneksi dan nama kolom).'
        ];
    }
} else {
    // Koneksi database gagal total
    $data_siswa_lengkap = [
        'nis' => $nis_siswa,
        'nama_lengkap' => $nama_siswa,
        'kelas' => $kelas_siswa,
        'jurusan' => 'Koneksi Gagal',
        'error_message' => 'Koneksi database gagal dibuat.'
    ];
}

// Tentukan data yang akan ditampilkan (gunakan data lengkap jika tersedia)
$nis = $data_siswa_lengkap['nis'] ?? $nis_siswa;
$nama_lengkap = $data_siswa_lengkap['nama_lengkap'] ?? $nama_siswa;
$kelas = $data_siswa_lengkap['kelas'] ?? $kelas_siswa;
$jurusan = $data_siswa_lengkap['jurusan'] ?? 'N/A'; // Jika kolom jurusan tidak terambil
$error_db = $data_siswa_lengkap['error_message'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa | <?php echo htmlspecialchars($nama_lengkap); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #1A8917; /* Hijau Utama */
            --primary-light: #28a745; /* Hijau Terang */
            --primary-bg: #eaf7ed; /* Latar Belakang Hijau Muda */
            --text-dark: #2c3e50;
            --bg-white: #ffffff;
            --border-light: #e0e0e0;
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.05);
            --border-radius: 10px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* -------------------------------------------------------------------------- */
        /* --- LAYOUT UTAMA (LSM) --- */
        /* -------------------------------------------------------------------------- */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Hijau (Navigation) */
        .sidebar {
            width: 250px;
            background-color: var(--primary-color);
            color: var(--bg-white);
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100%;
            transition: width 0.3s;
        }
        .sidebar-header {
            text-align: center;
            padding: 10px 20px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-header h3 {
            font-size: 1.4em;
            font-weight: 700;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: background-color 0.3s, color 0.3s;
            font-weight: 500;
        }
        .sidebar-menu a i {
            margin-right: 15px;
            font-size: 1.1em;
        }
        .sidebar-menu a:hover,
        .sidebar-menu .active {
            background-color: var(--primary-light);
            color: var(--bg-white);
        }
        .sidebar-menu .logout-link {
            position: absolute;
            bottom: 0;
            width: 100%;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Main Content (Dashboard Area) */
        .main-content {
            flex-grow: 1;
            margin-left: 250px; /* Harus sama dengan lebar sidebar */
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        /* Top Bar */
        .topbar {
            background-color: var(--bg-white);
            padding: 15px 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar-info h1 {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--primary-color);
        }
        .user-profile {
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        .user-profile i {
            margin-left: 10px;
            font-size: 1.2em;
            color: var(--primary-light);
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 2fr; /* 1 kolom kecil, 1 kolom besar */
            gap: 20px;
        }
        
        /* Card Styling */
        .card {
            background-color: var(--bg-white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }
        .card h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.1em;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-bg);
            padding-bottom: 8px;
        }

        /* Profile Card Specific */
        .profile-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border-light);
            font-size: 0.95em;
        }
        .profile-info-item:last-child {
            border-bottom: none;
        }
        .profile-info-item span:first-child {
            font-weight: 500;
            color: var(--text-dark);
        }
        .profile-info-item span:last-child {
            color: #555;
            font-weight: 400;
        }
        
        /* Data Akademik Placeholder */
        .academic-data-content {
            padding: 10px 0;
            text-align: center;
        }
        .academic-data-content p {
            color: #777;
            margin-top: 20px;
        }
        .academic-data-content button {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 15px;
            transition: background-color 0.3s;
        }
        .academic-data-content button:hover {
            background-color: var(--primary-light);
        }

        /* Responsiveness */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px; /* Sidebar kecil di mobile/tablet */
                overflow: hidden;
            }
            .sidebar-header h3 {
                display: none;
            }
            .sidebar-menu a {
                justify-content: center;
            }
            .sidebar-menu a i {
                margin-right: 0;
            }
            .sidebar-menu a span {
                display: none;
            }
            .main-content {
                margin-left: 80px;
                padding: 15px; /* Kurangi padding di mobile */
            }
            .dashboard-grid {
                grid-template-columns: 1fr; /* Kolom tunggal di layar kecil */
            }
            .topbar h1 {
                font-size: 1.3em;
            }
            .topbar small {
                display: block;
            }
        }
        /* Style untuk Pesan Error Database */
        .db-error-message {
            color: #c0392b; 
            background: #f8d7da; 
            padding: 10px; 
            border-radius: 5px; 
            font-size: 0.9em;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>SISWA DASHBOARD</h3>
        </div>
        <div class="sidebar-menu">
            <a href="#" class="active">
                <i class="fas fa-home"></i>
                <span>Beranda</span>
            </a>
            <a href="#">
                <i class="fas fa-book"></i>
                <span>Nilai Akademik</span>
            </a>
            <a href="#">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Pelajaran</span>
            </a>
            <a href="#">
                <i class="fas fa-file-invoice"></i>
                <span>Pembayaran</span>
            </a>
            <a href="#">
                <i class="fas fa-user-cog"></i>
                <span>Pengaturan Akun</span>
            </a>
            
            <a href="logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <div class="main-content">
        
        <div class="topbar">
            <div class="topbar-info">
                <h1>Selamat Datang, <?php echo htmlspecialchars(explode(' ', $nama_lengkap)[0]); ?>!</h1>
                <small style="color: #6c757d;">Dashboard Siswa SMK JTI</small>
            </div>
            <div class="user-profile">
                <span style="color: var(--text-dark);"><?php echo htmlspecialchars($nama_lengkap); ?></span>
                <i class="fas fa-graduation-cap"></i>
            </div>
        </div>
        
        <div class="dashboard-grid">
            
            <div class="card profile-card">
                <h4><i class="fas fa-user-circle"></i> Data Siswa</h4>
                
                <?php if ($error_db): ?>
                    <div class="db-error-message">
                        ⚠️ **ERROR:** <?php echo htmlspecialchars($error_db); ?>
                    </div>
                <?php endif; ?>

                <div class="profile-info-item">
                    <span>Nama Lengkap</span>
                    <span><?php echo htmlspecialchars($nama_lengkap); ?></span>
                </div>
                <div class="profile-info-item">
                    <span>NIS</span>
                    <span><?php echo htmlspecialchars($nis); ?></span>
                </div>
                <div class="profile-info-item">
                    <span>Kelas</span>
                    <span><?php echo htmlspecialchars($kelas); ?></span>
                </div>
                <div class="profile-info-item">
                    <span>Jurusan</span>
                    <span><?php echo htmlspecialchars($jurusan); ?></span>
                </div>
                <!-- PERBAIKAN: Item E-mail Dihapus karena tidak ada di DB -->
            </div>
            
            <div class="card academic-overview-card">
                <h4><i class="fas fa-chart-line"></i> Ringkasan Akademik</h4>
                
                <div class="academic-data-content">
                    <p>Konten Ringkasan Akademik akan ditampilkan di sini.</p>
                    <p style="font-size: 0.9em; margin-top: 5px; color: #aaa;">Data ini akan terisi setelah integrasi modul akademik.</p>
                    <button onclick="alert('Ini akan menuju ke halaman Nilai Akademik.');">Lihat Nilai Lengkap</button>
                </div>
            </div>

            <div class="card" style="grid-column: 1 / -1;">
                <h4><i class="fas fa-bullhorn"></i> Pengumuman Terbaru</h4>
                <p style="color: #666; font-size: 0.9em;">**14 Oktober 2025:** Ujian Tengah Semester akan dimulai minggu depan. Harap persiapkan diri.</p>
                <p style="color: #666; font-size: 0.9em;">**01 Oktober 2025:** Pembayaran SPP Bulan Oktober telah dibuka.</p>
            </div>
            
        </div>
        
    </div>
    
</div>

</body>
</html>
