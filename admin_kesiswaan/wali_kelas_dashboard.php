<?php
// ====================================================================
// wali_kelas_dashboard.php: DASHBOARD KHUSUS WALI KELAS
// ====================================================================

session_start();
require_once 'db_config.php'; 

// --- PERIKSA AUTENTIKASI DAN ROLE ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Wali_Kelas') {
    header('Location: login.php?status=error&msg=Akses ditolak.');
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];
$current_user_name = "Wali Kelas";
$current_user_role = "Wali Kelas";
$current_user_profile_pic = "https://placehold.co/80x80/92400e/ffffff?text=WALI"; 
$wali_detail = null;

if ($pdo && $logged_in_user_id) {
    try {
        // Ambil data user utama
        $stmt_user = $pdo->prepare("SELECT nama, profile_pic FROM users WHERE user_id = ?");
        $stmt_user->execute([$logged_in_user_id]);
        $user_data = $stmt_user->fetch();

        if ($user_data) {
            $current_user_name = htmlspecialchars($user_data['nama']);
            if (!empty($user_data['profile_pic'])) {
                $current_user_profile_pic = htmlspecialchars($user_data['profile_pic']);
            }
        }
        
        // Ambil detail Wali Kelas
        $stmt_detail = $pdo->prepare("SELECT kelas_dipegang, tahun_ajaran FROM wali_kelas_assignment WHERE user_id = ?");
        $stmt_detail->execute([$logged_in_user_id]);
        $wali_detail = $stmt_detail->fetch();

    } catch (PDOException $e) {
        // Handle error
    }
}

$kelas_dipegang = htmlspecialchars($wali_detail['kelas_dipegang'] ?? 'Belum Ditugaskan');
$tahun_ajaran = htmlspecialchars($wali_detail['tahun_ajaran'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Wali Kelas - SMK JTI 1</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #92400e; /* Coklat Wali Kelas */
            --primary-dark: #7c2d12;
            --secondary-color: #ffffff;
            --bg-page: #fff7ed;      
            --bg-container: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --sidebar-width: 280px;
            --box-shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.08); 
            --border-radius-lg: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-page); color: var(--text-dark); display: flex; min-height: 100vh; }
        
        /* Layout */
        .page-content-wrapper { display: flex; flex: 1; width: 100%; }
        .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%); color: var(--secondary-color); padding: 24px 0; box-shadow: 5px 0 15px rgba(0, 0, 0, 0.15); position: fixed; height: 100%; overflow-y: auto; }
        .main-content { flex-grow: 1; padding: 40px; margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); }
        .container { max-width: 100%; background: var(--bg-container); padding: 40px; border-radius: var(--border-radius-lg); box-shadow: var(--box-shadow-medium); margin-bottom: 30px; }
        
        /* Header dan Profil */
        .sidebar-header { text-align: center; padding: 10px 20px 20px; font-size: 1.5em; font-weight: 700; border-bottom: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 20px; }
        .user-profile { text-align: center; padding: 15px 20px; margin-bottom: 25px; }
        .user-profile img { width: 80px; height: 80px; border-radius: 50%; border: 4px solid var(--secondary-color); object-fit: cover; margin-bottom: 10px; }
        .user-profile .name { font-size: 1.1em; font-weight: 600; }
        .user-profile .role { font-size: 0.8em; color: rgba(255, 255, 255, 0.8); }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: var(--secondary-color); text-decoration: none; transition: background-color 0.3s; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255, 255, 255, 0.15); border-left: 4px solid var(--secondary-color); font-weight: 600; }
        .sidebar-menu i { margin-right: 15px; width: 20px; text-align: center; }

        /* Content */
        h1 { font-size: 2em; font-weight: 700; color: var(--primary-dark); border-bottom: 2px solid #eee; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        h2 { font-size: 1.5em; font-weight: 600; margin-top: 2.5rem; margin-bottom: 1rem; color: var(--primary-color); }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin-top: 20px; }
        .info-card { background: #fffdf9; border-radius: 10px; padding: 25px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05); border-left: 5px solid var(--primary-color); }
        .info-card i { font-size: 2em; color: var(--primary-color); margin-bottom: 10px; }
        .info-card .title { font-size: 1em; font-weight: 600; color: var(--text-dark); }
        .info-card .value { font-size: 1.8em; font-weight: 700; color: var(--primary-dark); margin-top: 5px; }
        .value.kelas { font-size: 2.2em; }
        .value.tahun { font-size: 1.2em; }
        .action-card { border: 1px solid #f9e4e2; background: #fef0ef; cursor: pointer; transition: transform 0.2s; }
        .action-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1); }


        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 100%; position: fixed; z-index: 1000; transition: transform 0.3s ease; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; width: 100%; }
        }
    </style>
</head>
<body>
<div class="page-content-wrapper">
    <!-- Sidebar (Disesuaikan untuk Wali Kelas) -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header"><i class="fas fa-graduation-cap"></i><span>Wali Kelas</span></div>
        <div class="user-profile">
            <img src="<?php echo $current_user_profile_pic; ?>" alt="Profil Wali Kelas" onerror="this.onerror=null;this.src='https://placehold.co/80x80/92400e/ffffff?text=WALI';">
            <div class="name"><?php echo $current_user_name; ?></div>
            <div class="role"><?php echo $current_user_role; ?></div>
        </div>
        <nav class="sidebar-menu">
            <a href="wali_kelas_dashboard.php" class="active"><i class="fas fa-columns"></i> Dashboard</a>
            <a href="#"><i class="fas fa-users"></i> Data Siswa</a>
            <a href="#"><i class="fas fa-file-alt"></i> Kelola Rapor</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <main class="main-content">
        <div class="container">
            <h1><i class="fas fa-user-tie"></i> Selamat Datang, <?php echo $current_user_name; ?>!</h1>
            <p>Anda adalah wali kelas untuk **<?php echo $kelas_dipegang; ?>** pada tahun ajaran **<?php echo $tahun_ajaran; ?>**.</p>

            <h2>Ringkasan Kelas Anda</h2>
            <div class="card-grid">
                <div class="info-card">
                    <i class="fas fa-home"></i>
                    <div class="title">Kelas yang Diampu</div>
                    <div class="value kelas"><?php echo $kelas_dipegang; ?></div>
                    <div class="value tahun"><?php echo $tahun_ajaran; ?></div>
                </div>
                <div class="info-card">
                    <i class="fas fa-user-graduate"></i>
                    <div class="title">Jumlah Siswa</div>
                    <div class="value">32</div> <!-- Contoh data statis -->
                </div>
                <div class="info-card">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="title">Siswa Bermasalah (Peringatan)</div>
                    <div class="value" style="color: #c0392b;">3 Siswa</div>
                </div>
            </div>

            <h2>Tugas Utama</h2>
            <div class="card-grid">
                 <div class="info-card action-card" style="border-left: 5px solid #2980b9;">
                    <i class="fas fa-clipboard-list" style="color: #2980b9;"></i>
                    <div class="title">Verifikasi Nilai Rapor</div>
                    <div class="value" style="font-size: 1em; color: var(--text-light);">Cek dan finalisasi semua nilai mata pelajaran.</div>
                </div>
                <div class="info-card action-card" style="border-left: 5px solid #27ae60;">
                    <i class="fas fa-comments" style="color: #27ae60;"></i>
                    <div class="title">Catatan Wali Kelas</div>
                    <div class="value" style="font-size: 1em; color: var(--text-light);">Input catatan sikap dan perilaku siswa.</div>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
</html>
