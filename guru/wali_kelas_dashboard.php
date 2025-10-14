<?php
// ====================================================================
// wali_kelas_dashboard.php: DASHBOARD KHUSUS WALI KELAS (VERSI POWERFUL)
// ====================================================================

session_start();

// Pastikan file db_config.php ada dan berisi koneksi PDO ($pdo)
require_once '../koneksi/db_config.php';

// --- PERIKSA AUTENTIKASI DAN ROLE ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// **HANYA IZINKAN ROLE WALI_KELAS**
if ($_SESSION['role'] !== 'Wali_Kelas') {
    // Hancurkan sesi sebelum redirect jika role salah
    session_unset();
    session_destroy();
    header('Location: login.php?access=denied_role');
    exit;
}

// ==============================================================================
// 🔑 PERBAIKAN FINAL ANTI-BENTROK: AMBIL DATA KRITIS DAN TUTUP SESI
// ==============================================================================
$logged_in_user_id = $_SESSION['user_id'];
$logged_in_role = $_SESSION['role']; 

// 🚨 TINDAKAN TEGAS: Pastikan kunci 'nama' di sesi DIBERSIHKAN
// Ini mencegah bentrok data jika ada sisa sesi dari user lain.
if (isset($_SESSION['nama'])) {
    unset($_SESSION['nama']);
}

// Tutup sesi SEGERA. Melepaskan kunci sesi untuk mencegah tabrakan/penundaan I/O sesi.
session_write_close(); 
// Setelah baris ini, JANGAN PERNAH mengakses $_SESSION lagi.
// ==============================================================================

$message = '';
$message_type = '';
$alerts = []; 

// Data Utama Wali Kelas (dari tabel 'admin')
$current_user_name = "Pengguna Tidak Dikenal"; // Nama akan diambil dari DB
$current_user_role = "Undefined Role";
$current_user_profile_pic = "../img/logosmkjt1.png"; // PATH GAMBAR DEFAULT

// Data Penugasan Kelas Wali Kelas
$assigned_class_name = "Belum Ditugaskan";
$tahun_ajaran = "N/A";
$students_data = []; // Untuk menampung data siswa

// --- INICIALISASI VARIABEL STATISTIK BARU ---
$total_students = 0;
$male_students = 0;
$female_students = 0;
$raport_status = "N/A"; 
$raport_color = "warning"; 
$raport_icon = "question-circle";
$students_missing_data = 0; 
$students_absent_today = 3; 

if ($pdo && $logged_in_user_id) {
    try {
        // 1. Ambil data profil Wali Kelas (Menggunakan ID yang sudah diamankan $logged_in_user_id)
        $stmt_user = $pdo->prepare("SELECT nama, role, profile_pic FROM admin WHERE user_id = ?");
        $stmt_user->execute([$logged_in_user_id]);
        $current_user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($current_user_data) {
            // ✅ Nama Diambil dari DATABASE, BUKAN dari sesi. Ini adalah kunci fix.
            $current_user_name = htmlspecialchars($current_user_data['nama']);
            // Menggunakan $logged_in_role yang sudah disimpan dari sesi awal
            $current_user_role = str_replace('_', ' ', htmlspecialchars($logged_in_role)); 
            if (!empty($current_user_data['profile_pic'])) {
                $current_user_profile_pic = htmlspecialchars($current_user_data['profile_pic']);
            }
        } else {
            // Logika jika ID di sesi tidak valid di DB (seharusnya tidak terjadi)
            // Hancurkan sesi dan redirect ke login
            session_start();
            session_unset();
            session_destroy();
            header('Location: login.php?error=user_not_found');
            exit;
        }

        // 2. Ambil Penugasan Kelas dari tabel 'wali_kelas_assignment'
        $stmt_assignment = $pdo->prepare("SELECT kelas_dipegang, tahun_ajaran FROM wali_kelas_assignment WHERE user_id = ? ORDER BY tahun_ajaran DESC LIMIT 1");
        $stmt_assignment->execute([$logged_in_user_id]);
        $assignment_data = $stmt_assignment->fetch(PDO::FETCH_ASSOC);

        if ($assignment_data) {
            $assigned_class_name = htmlspecialchars($assignment_data['kelas_dipegang']);
            $tahun_ajaran = htmlspecialchars($assignment_data['tahun_ajaran']);

            // 3. Ambil Daftar Siswa dari kelas yang dipegang
            $stmt_students = $pdo->prepare("SELECT nis, nama_siswa, jenis_kelamin, alamat, nama_ayah FROM siswa WHERE kelas = ? ORDER BY nama_siswa ASC");
            $stmt_students->execute([$assigned_class_name]);
            $students_data = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
            
            // --- LOGIKA STATISTIK SISWA ---
            $total_students = count($students_data);
            
            if ($total_students > 0) {
                $raport_status = "Perlu Aksi"; 

                foreach ($students_data as $student) {
                    $jk = strtoupper(trim($student['jenis_kelamin']));
                    if ($jk == 'L' || $jk == 'LAKI-LAKI') {
                        $male_students++;
                    } elseif ($jk == 'P' || $jk == 'PEREMPUAN') {
                        $female_students++;
                    }
                    
                    if (empty($student['alamat']) || empty($student['nama_ayah'])) {
                        $students_missing_data++;
                    }
                }
                
                // Logika Status Raport
                if ($total_students >= 20) { 
                    $raport_status = "Siap Diperiksa"; 
                    $raport_color = "success"; 
                    $raport_icon = "check-circle";
                } elseif ($total_students > 0) {
                    $raport_status = "Input Sikap (30%)";
                    $raport_color = "info"; 
                    $raport_icon = "edit";
                }

                // Tambahkan ALERTS
                if ($students_missing_data > 0) {
                    $alerts[] = [
                        'type' => 'warning',
                        'msg' => "⚠️ **{$students_missing_data} siswa** memiliki data penting (Alamat/Ortu) yang masih kosong. Mohon perbarui profil mereka."
                    ];
                }
                if ($students_absent_today > 0) {
                    $alerts[] = [
                        'type' => 'error',
                        'msg' => "🚨 Terdapat **{$students_absent_today} siswa** tidak masuk hari ini. Klik 'Cek Absensi' untuk tindak lanjut cepat."
                    ];
                }

            } else {
                 $message = "Kelas $assigned_class_name masih kosong. Segera input data siswa."; 
                 $message_type = 'info';
            }

        } else {
            $message = "Anda belum ditugaskan sebagai wali kelas untuk kelas manapun. Hubungi Administrator.";
            $message_type = 'error';
        }

    } catch (PDOException $e) {
        error_log("Error fetching Wali Kelas data: " . $e->getMessage());
        $message = "Terjadi kesalahan database: " . $e->getMessage();
        $message_type = 'error';
    }
} else if (!$pdo) {
     $message = "Koneksi database gagal. Hubungi administrator sistem.";
     $message_type = 'error';
}
// --- END DYNAMIC DATA SETUP ---

// Ambil pesan dari Redirect-After-Post (jika ada)
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $message_type = $_GET['status'];
    $message = htmlspecialchars(urldecode($_GET['msg']));
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Wali Kelas | Kelas: <?php echo $assigned_class_name; ?> - SMK JTI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* ====================================================================
            MODIFIKASI CSS UNTUK TAMPILAN PUTIH HIJAU
            ==================================================================== */
        :root {
            --primary-color: #1a8917; /* Hijau Daun */
            --primary-dark: #146312;  /* Hijau Lebih Gelap */
            --secondary-color: #ffffff;
            --bg-page: #f7fcf7;       /* Latar Belakang Putih Sangat Muda */
            --bg-container: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --success-color: #27ae60;
            --error-color: #c0392b;
            --info-color: #3498db;
            --warning-color: #f39c12; /* Warna Peringatan */
            --border-color: #e0e0e0; /* Border Abu-abu Muda */
            --sidebar-width: 280px;
            --box-shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.08);
            --border-radius-md: 8px;
            --border-radius-lg: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .page-content-wrapper {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            width: 100%;
        }

        /* --- Sidebar & Navigasi (DEKSTOP) --- */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--secondary-color);
            padding: 24px 0;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.15);
            position: fixed;
            height: 100%;
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
            overflow-y: auto;
            flex-shrink: 0;
        }
        
        .sidebar-header { text-align: center; padding: 10px 20px 20px; font-size: 1.5em; font-weight: 700; letter-spacing: 1px; border-bottom: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .user-profile { text-align: center; padding: 15px 20px; margin-bottom: 25px; }
        .user-profile img {
            width: 100px; 
            height: 100px;
            border-radius: var(--border-radius-md);
            border: 4px solid var(--secondary-color);
            object-fit: contain;
            margin-bottom: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            background-color: #fff;
        }
        .user-profile .name { font-size: 1.1em; font-weight: 600; }
        .user-profile .role { font-size: 0.8em; color: rgba(255, 255, 255, 0.8); }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: var(--secondary-color); text-decoration: none; font-size: 0.95em; transition: background-color 0.3s ease, padding-left 0.3s ease; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255, 255, 255, 0.15); border-left: 4px solid var(--secondary-color); font-weight: 600; }
        .sidebar-menu i { margin-right: 15px; font-size: 1.1em; width: 20px; text-align: center; }

        /* Main Content dan Container (Desktop Base) */
        .main-content {
            flex-grow: 1;
            padding: 30px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - var(--sidebar-width));
        }
        
        .container {
            width: 100%;
            background: var(--bg-container);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-medium);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        h1 { font-size: 2em; font-weight: 700; display: flex; align-items: center; gap: 15px; border-bottom: 2px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem; }
        h1 i { color: var(--primary-color); }
        h2 { font-size: 1.5em; font-weight: 600; margin-top: 2rem; margin-bottom: 1.5rem; color: var(--primary-dark); }
        p { color: var(--text-light); margin-bottom: 2rem; line-height: 1.6; }

        /* Alert Styling */
        .alert { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: var(--border-radius-md); 
            font-weight: 500; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            transition: opacity 0.5s ease; 
            animation: fadeIn 0.5s;
            line-height: 1.4;
        }
        .alert strong { font-weight: 700; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert.success { background-color: #eaf7ed; color: #206b32; border-left: 5px solid var(--success-color); }
        .alert.error { background-color: #f9e4e2; color: #8c2a20; border-left: 5px solid var(--error-color); }
        .alert.info { background-color: #e0f2fe; color: #075985; border-left: 5px solid var(--info-color); }
        .alert.warning { background-color: #fff7e6; color: #8a6d3b; border-left: 5px solid var(--warning-color); }
        
        /* Box Info Kelas */
        .class-info-box {
            background-color: var(--primary-color);
            color: var(--secondary-color);
            padding: 20px 30px;
            border-radius: var(--border-radius-md);
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .class-info-box .detail h3 {
            margin: 0 0 5px 0;
            font-size: 1.8em;
            font-weight: 700;
        }
        .class-info-box .detail p {
            margin: 0;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.0em;
        }
        .class-info-box .icon {
            font-size: 3em;
            opacity: 0.8;
        }

        /* --- CSS UNTUK KONTEN BARU --- */

        /* Card Statistik (Info Box Grid) */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--bg-container);
            padding: 20px;
            border-radius: var(--border-radius-md);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card .main-stat {
            display: flex;
            align-items: center;
            width: 100%;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .stat-card .icon-box {
            font-size: 1.8em;
            padding: 10px;
            border-radius: var(--border-radius-md);
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-color);
        }
        .stat-card.total .icon-box { background-color: var(--primary-color); }
        .stat-card.male .icon-box { background-color: #3498db; }
        .stat-card.female .icon-box { background-color: #e74c3c; }
        .stat-card.absensi .icon-box { background-color: var(--error-color); }
        .stat-card.raport .icon-box { background-color: var(--warning-color); }
        .stat-card.raport.success .icon-box { background-color: var(--success-color); }
        .stat-card.raport.info .icon-box { background-color: var(--info-color); }
        .stat-card.data_quality .icon-box { background-color: #9b59b6; } /* Ungu */


        .stat-card .data .number {
            font-size: 2.2em;
            font-weight: 700;
            line-height: 1;
            color: var(--text-dark);
        }

        .stat-card .data .label {
            font-size: 0.9em;
            color: var(--text-light);
            margin-top: 5px;
            line-height: 1.2;
        }
        .stat-card .quick-link {
            font-size: 0.8em;
            text-decoration: none;
            color: var(--info-color);
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px dashed var(--border-color);
            width: 100%;
            text-align: right;
            display: block;
        }
        .stat-card .quick-link:hover {
            color: var(--primary-dark);
        }
        
        /* Styling Tombol Aksi di Tabel */
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            color: var(--secondary-color);
            font-size: 0.85em;
            transition: background-color 0.3s, opacity 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        .action-btn:hover {
            opacity: 0.9;
        }

        /* Styling Progress Bar Contoh */
        .progress-bar {
            background-color: #eee;
            border-radius: 5px;
            overflow: hidden;
            height: 10px;
            width: 100px;
            margin: 5px 0 0;
        }
        .progress-bar-fill {
            height: 100%;
            transition: width 0.5s ease;
        }
        .progress-bar-fill.high { background-color: var(--success-color); }
        .progress-bar-fill.medium { background-color: var(--warning-color); }
        .progress-bar-fill.low { background-color: var(--error-color); }

        
        /* Tampilan Tabel (Siswa) */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        table th, table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; background-color: var(--bg-container); }
        table th { background-color: #eaf7ed; color: var(--primary-dark); font-weight: 600; text-transform: uppercase; font-size: 0.9em; border-bottom: none; }
        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:hover { background-color: #f0fff0; }


        /* ====================================================================
            RESPONSIVE DESIGN
            ==================================================================== */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                left: 0;
                top: 0;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding-top: 80px; 
            }
            .menu-toggle {
                display: block;
            }
            footer {
                margin-left: 0;
                width: 100%;
            }
        }

        @media (max-width: 600px) {
            .container { padding: 20px; border-radius: var(--border-radius-md); }
            .main-content { padding: 15px; }
            h1 { font-size: 1.5em; flex-direction: column; align-items: flex-start; gap: 10px; }
            .class-info-box { flex-direction: column; align-items: flex-start; gap: 10px; }
            .class-info-box .detail h3 { font-size: 1.5em; }
            .class-info-box .icon { display: none; } 

            /* Penyesuaian Mobile untuk Card Statistik */
            .stats-grid { gap: 10px; }
            .stat-card { padding: 15px; }
            .stat-card .data .number { font-size: 1.8em; }
            .stat-card .main-stat { flex-direction: row; justify-content: space-between; }
            
            /* Table Responsive Card Fallback */
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            table tr { border: 1px solid var(--border-color); margin-bottom: 10px; border-radius: var(--border-radius-md); overflow: hidden; background: var(--bg-container); }
            table td { 
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
                font-size: 0.9em;
            }
            table td:before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 45%;
                padding-left: 15px;
                font-weight: 600;
                text-align: left;
                color: var(--primary-dark);
            }
            table td.action-buttons {
                justify-content: flex-end; 
                padding-top: 5px;
                padding-bottom: 5px;
                padding-right: 15px;
            }
        }
    </style>
</head>
<body>

    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-school"></i>
            <span>SMK JTI</span>
        </div>
        <div class="user-profile">
            <img src="<?php echo $current_user_profile_pic; ?>" alt="Profil <?php echo $current_user_name; ?>">
            <div class="name"><?php echo $current_user_name; ?></div>
            <div class="role"><?php echo $current_user_role; ?></div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="wali_kelas_dashboard.php" class="active"><i class="fas fa-chalkboard-teacher"></i> Dashboard Kelas</a>
            <a href="akunsiswa.php"><i class="fas fa-users-cog"></i> Management Pengguna</a>
            <a href="#absensi"><i class="fas fa-user-check"></i> Kelola Absensi</a>
            <a href="#nilai"><i class="fas fa-clipboard-list"></i> Input Nilai Sikap</a>
            <a href="#catatan"><i class="fas fa-book-open"></i> Catatan Siswa</a>
            <a href="#laporan"><i class="fas fa-chart-line"></i> Laporan Kelas</a>
            <hr style="border: 0; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 15px 25px;">
            <a href="logout.php" style="color: #ffdddd;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>
    
    <div class="sidebar-overlay" onclick="toggleSidebar()" id="overlay"></div>

    <div class="page-content-wrapper">
        <main class="main-content" id="main-content">
            
            <h1>
                <i class="fas fa-graduation-cap"></i>
                Dashboard Kelas Wali
            </h1>

            <div class="class-info-box">
                <div class="detail">
                    <h3>Kelas <?php echo $assigned_class_name; ?></h3>
                    <p>Wali Kelas: <?php echo $current_user_name; ?></p>
                    <p>Tahun Ajaran: **<?php echo $tahun_ajaran; ?>**</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users-class"></i>
                </div>
            </div>
            
            <?php 
            if (!empty($message)) {
                $icon = $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'times-circle' : 'info-circle');
                echo "<div class='alert {$message_type}'><i class='fas fa-{$icon}'></i> {$message}</div>";
            }
            foreach ($alerts as $alert): ?>
                <div class="alert <?php echo $alert['type']; ?>">
                    <?php echo $alert['msg']; ?>
                </div>
            <?php endforeach; ?>
            <p>Selamat datang, **<?php echo $current_user_name; ?>**. Sebagai Wali Kelas, Anda adalah mentor utama bagi siswa. Dashboard ini menyediakan ringkasan cepat dan jalur aksi untuk memonitor perkembangan dan kesejahteraan kelas Anda.</p>

            <h2><i class="fas fa-cogs"></i> Prioritas dan Aksi Cepat</h2>
            <div class="stats-grid">

                <div class="stat-card total">
                    <div class="main-stat">
                        <div class="icon-box"><i class="fas fa-users"></i></div>
                        <div class="data">
                            <div class="number"><?php echo $total_students; ?></div>
                        </div>
                    </div>
                    <div class="label">Total Siswa Kelas</div>
                    <a href="#daftar_siswa" class="quick-link">Lihat Daftar Lengkap <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="stat-card absensi">
                    <div class="main-stat">
                        <div class="icon-box"><i class="fas fa-user-slash"></i></div>
                        <div class="data">
                            <div class="number"><?php echo $students_absent_today; ?></div>
                        </div>
                    </div>
                    <div class="label">Siswa Absen Hari Ini</div>
                    <a href="#absensi" class="quick-link">Kelola Absensi Harian <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="stat-card raport <?php echo $raport_color; ?>">
                    <div class="main-stat">
                        <div class="icon-box"><i class="fas fa-<?php echo $raport_icon; ?>"></i></div>
                        <div class="data">
                            <div class="number" style="font-size: 1.5em;"><?php echo $raport_status; ?></div>
                        </div>
                    </div>
                    <div class="label">Status Pengisian Raport</div>
                    <a href="#nilai" class="quick-link">Lanjutkan Input Nilai Sikap <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="stat-card data_quality">
                    <div class="main-stat">
                        <div class="icon-box"><i class="fas fa-database"></i></div>
                        <div class="data">
                            <div class="number"><?php echo $students_missing_data; ?></div>
                        </div>
                    </div>
                    <div class="label">Siswa Perlu Update Data</div>
                    <a href="#profil_siswa" class="quick-link">Periksa Detail Profil <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="container">
                <h2 id="daftar_siswa"><i class="fas fa-user-graduate"></i> Daftar Siswa Kelas <?php echo $assigned_class_name; ?></h2>
                <p>Tabel ini menyediakan detail siswa Anda. Gunakan kolom **Aksi** untuk melakukan intervensi atau penginputan data nilai penting secara spesifik.</p>

                <div class="table-container">
                    <?php if (!empty($students_data)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>J.K.</th>
                                    <th>Alamat (Ringkas)</th>
                                    <th>Progress (Contoh)</th>
                                    <th>Aksi Cepat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($students_data as $student): 
                                    // LOGIKA CONTOH PROGRESS BAR
                                    $progress = 100; // Asumsi 100%
                                    $progress_color = 'high';
                                    if (empty($student['alamat']) || empty($student['nama_ayah'])) {
                                        $progress = 60;
                                        $progress_color = 'medium';
                                    }
                                    if ($student['jenis_kelamin'] === 'P') { // Contoh tambahan logika
                                        $progress += 10;
                                        if ($progress > 100) $progress = 100;
                                    }
                                    if ($progress < 70) $progress_color = 'low';
                                ?>
                                    <tr>
                                        <td data-label="#"><?php echo $no++; ?></td>
                                        <td data-label="NIS"><?php echo htmlspecialchars($student['nis']); ?></td>
                                        <td data-label="Nama Siswa"><?php echo htmlspecialchars($student['nama_siswa']); ?></td>
                                        <td data-label="J.K."><?php echo htmlspecialchars($student['jenis_kelamin']); ?></td>
                                        <td data-label="Alamat (Ringkas)"><?php echo substr(htmlspecialchars($student['alamat'] ?? 'N/A'), 0, 30) . (strlen($student['alamat'] ?? '') > 30 ? '...' : ''); ?></td>
                                        <td data-label="Progress (Contoh)">
                                            <div style="font-size: 0.8em; color: var(--text-light);">Data <?php echo $progress; ?>%</div>
                                            <div class="progress-bar">
                                                <div class="progress-bar-fill <?php echo $progress_color; ?>" style="width: <?php echo $progress; ?>%;"></div>
                                            </div>
                                        </td>
                                        <td data-label="Aksi Cepat" class="action-buttons">
                                            <a href="#input_sikap?nis=<?php echo htmlspecialchars($student['nis']); ?>" class="action-btn btn-input" style="background-color: var(--success-color);">
                                                <i class="fas fa-pencil-alt"></i> Nilai
                                            </a>
                                            <a href="#detail?nis=<?php echo htmlspecialchars($student['nis']); ?>" class="action-btn btn-info" style="background-color: var(--info-color);">
                                                <i class="fas fa-eye"></i> Profil
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert info">
                            <i class="fas fa-exclamation-triangle"></i>
                            Tidak ada data siswa ditemukan untuk kelas **<?php echo $assigned_class_name; ?>**.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> SMK JTI. Semua Hak Dilindungi. Dikelola oleh **<?php echo $current_user_name; ?>**.</p>
            <p>Halaman Wali Kelas (Role: <?php echo $current_user_role; ?>)</p>
        </footer>
    </div>
    
    <script>
        // Fungsi untuk mengelola tampilan sidebar di mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Untuk layar yang lebih besar, tutup sidebar jika terbuka
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('overlay').classList.remove('active');
            }
        });
    </script>
</body>
</html>