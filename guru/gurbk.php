<?php
// =========================================================
// gurbk.php: DASHBOARD KHUSUS GURU BK (DENGAN LAYOUT SIDEBAR)
// =========================================================
session_start();

require_once '../koneksi/db_config.php'; 

// --- 1. Verifikasi Sesi dan Role ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Guru_BK') {
    header('Location: login.php?status=error&msg=' . urlencode('Akses ditolak. Silakan login sebagai Guru BK.'));
    exit;
}

$user_id = $_SESSION['user_id'];
$username = 'Guru BK'; 
$nip = 'N/A';

// --- 2. Ambil Data Pengguna dari Database ---
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT nama, nip FROM admin WHERE user_id = ? AND role = 'Guru_BK'");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $username = htmlspecialchars($user_data['nama']);
            $nip = htmlspecialchars($user_data['nip']);
        }
    } catch (PDOException $e) {
        error_log("Database Error di gurbk.php: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru BK</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Warna Aksen untuk BK */
            --primary-color: #3498db; /* Biru terang */
            --primary-dark: #2980b9; /* Biru gelap */
            
            --secondary-color: #ffffff;
            --bg-page: #ecf0f1;
            --text-dark: #2c3e50;
            --border-radius: 8px;
            --sidebar-width: 250px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            line-height: 1.6;
            display: flex; /* Kontainer utama Flexbox */
            min-height: 100vh;
        }
        
        /* ======================================================= */
        /* SIDEBAR (NAVIGASI) */
        /* ======================================================= */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-dark);
            color: var(--secondary-color);
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            position: fixed; /* Membuat sidebar tetap */
            height: 100%;
            overflow-y: auto;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.5em;
            font-weight: 700;
            color: var(--secondary-color);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding-bottom: 20px;
        }
        .user-panel {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }
        .user-panel strong {
            display: block;
            margin-bottom: 5px;
            font-size: 1.1em;
        }
        .user-panel p {
            font-size: 0.8em;
            color: #ccc;
        }
        .sidebar-nav ul {
            list-style: none;
            padding: 0;
        }
        .sidebar-nav li a {
            display: block;
            padding: 12px 15px;
            text-decoration: none;
            color: var(--secondary-color);
            border-radius: 5px;
            margin-bottom: 5px;
            transition: background-color 0.3s, padding-left 0.3s;
            font-weight: 500;
        }
        .sidebar-nav li a i {
            margin-right: 10px;
        }
        .sidebar-nav li a:hover,
        .sidebar-nav li a.active {
            background-color: var(--primary-color);
            padding-left: 20px;
        }
        .logout-btn-sidebar {
            margin-top: auto; /* Dorong ke bawah */
            text-align: center;
            padding: 15px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        .logout-btn-sidebar a {
            display: block;
            padding: 10px;
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .logout-btn-sidebar a:hover {
            background-color: #c0392b;
        }

        /* ======================================================= */
        /* MAIN CONTENT */
        /* ======================================================= */
        .main-content {
            margin-left: var(--sidebar-width); /* Mengimbangi sidebar fixed */
            flex-grow: 1; /* Mengisi sisa ruang */
            padding: 20px;
        }
        .header-top {
            background-color: var(--secondary-color);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--primary-color);
        }
        .header-top h1 {
            color: var(--primary-dark);
            font-size: 1.8em;
            margin: 0;
        }
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .card {
            background-color: var(--secondary-color);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--primary-color);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card h3 {
            color: var(--primary-dark);
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .card a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        /* Responsif Sederhana: Sembunyikan sidebar di layar kecil */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                border-bottom: 3px solid var(--primary-color);
            }
            .main-content {
                margin-left: 0;
            }
            .user-panel { display: none; } /* Sembunyikan info user di sidebar mobile */
            .sidebar-nav ul { display: flex; flex-wrap: wrap; justify-content: space-around; }
            .sidebar-nav li { flex: 1 1 45%; }
            .logout-btn-sidebar { margin-top: 10px; border-top: none; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i> DASHBOARD BK
        </div>

        <div class="user-panel">
            <strong><?php echo $username; ?></strong>
            <p><?php echo $nip; ?></p>
        </div>
        
        <nav class="sidebar-nav">
            <ul>
                <li><a href="gurbk.php" class="active"><i class="fas fa-home"></i> Beranda</a></li>
                <li><a href="bk_siswa.php"><i class="fas fa-users"></i> Data Siswa</a></li>
                <li><a href="bk_pelanggaran.php"><i class="fas fa-gavel"></i> Catat Pelanggaran</a></li>
                <li><a href="bk_konseling.php"><i class="fas fa-comments"></i> Sesi Konseling</a></li>
                <li><a href="bk_laporan.php"><i class="fas fa-clipboard-list"></i> Laporan</a></li>
            </ul>
        </nav>

        <div class="logout-btn-sidebar">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <header class="header-top">
            <h1>Selamat Datang di Sistem Bimbingan Konseling</h1>
        </header>

        <section class="card-grid">
            <div class="card">
                <h3><i class="fas fa-users"></i> Total Siswa</h3>
                <p>250 Siswa</p>
                <p><a href="bk_siswa.php">Kelola Siswa &raquo;</a></p>
            </div>
            <div class="card">
                <h3><i class="fas fa-gavel"></i> Kasus Hari Ini</h3>
                <p>3 Kasus Baru</p>
                <p><a href="bk_pelanggaran.php">Lihat Pelanggaran &raquo;</a></p>
            </div>
            <div class="card">
                <h3><i class="fas fa-comments"></i> Konseling Aktif</h3>
                <p>5 Sesi</p>
                <p><a href="bk_konseling.php">Jadwal Konseling &raquo;</a></p>
            </div>
            <div class="card">
                <h3><i class="fas fa-chart-bar"></i> Statistik</h3>
                <p>Lihat tren dan analisis data.</p>
                <p><a href="bk_statistik.php">Lihat Detail &raquo;</a></p>
            </div>
        </section>
        
        <footer style="margin-top: 50px;">
            <p style="text-align: center; font-size: 0.8em; color: #7f8c8d;">&copy; <?php echo date('Y'); ?> SMK JTI 1. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>