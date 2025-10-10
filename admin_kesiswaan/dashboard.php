<?php
// ====================================================================
// dashboard.php: HALAMAN UTAMA DASHBOARD ADMIN (Versi Desain & Mobile Friendly)
// Tata Letak Statistik: 3 Kartu Atas, 3 Kartu Bawah
// ====================================================================

session_start();

// Asumsi: File ini berada di folder yang sama dengan admin.php
// Pastikan file db_config.php ada dan berisi koneksi PDO ($pdo)
require_once '../koneksi/db_config.php';

// --- PERIKSA AUTENTIKASI DAN ROLE ---
if (!isset($_SESSION['user_id'])) {
    header('Location: admin_login.php');
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];

// --- DYNAMIC LOGGED-IN USER SETUP ---
$current_user_name = "Pengguna Tidak Dikenal";
$current_user_role = "Undefined Role";
$current_user_profile_pic = "../img/logosmkjt1.png"; // Default image

if ($pdo && $logged_in_user_id) {
    try {
        $stmt = $pdo->prepare("SELECT nama, role, profile_pic FROM admin WHERE user_id = ?");
        $stmt->execute([$logged_in_user_id]);
        $current_user_data = $stmt->fetch();

        if ($current_user_data) {
            $current_user_name = htmlspecialchars($current_user_data['nama']);
            // Mengganti underscore dengan spasi untuk tampilan role yang lebih rapi
            $current_user_role = str_replace('_', ' ', htmlspecialchars($current_user_data['role']));
            if (!empty($current_user_data['profile_pic'])) {
                $current_user_profile_pic = htmlspecialchars($current_user_data['profile_pic']);
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching current user data: " . $e->getMessage());
    }
}
// --- END DYNAMIC LOGGED-IN USER SETUP ---


// --- PENGAMBILAN DATA UNTUK 6 KARTU STATISTIK (ADMIN ROLES + SISWA) ---
$total_pengguna = 0; // Total semua di tabel admin
$total_admin_full = 0; // Admin dengan role 'Admin'
$total_guru_mapel = 0; 
$total_wali_kelas = 0;
$total_guru_bk = 0; 
$total_siswa = 0; // Dari tabel siswa

$role_counts = []; // Untuk Chart.js

if ($pdo) {
    try {
        // 1. Hitung total semua pengguna di tabel 'admin'
        $stmt_pengguna = $pdo->query("SELECT COUNT(*) FROM admin");
        $total_pengguna = $stmt_pengguna->fetchColumn();

        // 2. Hitung total Admin (full access)
        $stmt_admin_full = $pdo->query("SELECT COUNT(*) FROM admin WHERE role = 'Admin'");
        $total_admin_full = $stmt_admin_full->fetchColumn();
        
        // 3. Hitung total Guru Mapel
        $stmt_guru = $pdo->query("SELECT COUNT(*) FROM admin WHERE role = 'Guru_Mapel'");
        $total_guru_mapel = $stmt_guru->fetchColumn();
        
        // 4. Hitung total Wali Kelas
        $stmt_wali = $pdo->query("SELECT COUNT(*) FROM admin WHERE role = 'Wali_Kelas'");
        $total_wali_kelas = $stmt_wali->fetchColumn();

        // 5. Hitung total Guru BK
        $stmt_bk = $pdo->query("SELECT COUNT(*) FROM admin WHERE role = 'Guru_BK'");
        $total_guru_bk = $stmt_bk->fetchColumn();

        // 6. Hitung total siswa (ASUMSI ada tabel 'siswa')
        try {
            $stmt_siswa = $pdo->query("SELECT COUNT(*) FROM siswa");
            $total_siswa = $stmt_siswa->fetchColumn();
        } catch (PDOException $e) {
            $total_siswa = 0; 
            error_log("Tabel 'siswa' tidak ditemukan. Dianggap 0. Pesan: " . $e->getMessage());
        }

        // Query untuk mendapatkan jumlah setiap role (untuk grafik)
        $stmt_role_counts = $pdo->query("SELECT role, COUNT(*) as count FROM admin GROUP BY role");
        while ($row = $stmt_role_counts->fetch(PDO::FETCH_ASSOC)) {
            $role_name = str_replace('_', ' ', $row['role']);
            $role_counts[$role_name] = $row['count'];
        }

    } catch (PDOException $e) {
        error_log("Error fetching dashboard stats: " . $e->getMessage());
    }
}
// --- END PENGAMBILAN DATA ---

// Variabel PHP untuk diteruskan ke JavaScript (untuk grafik)
$role_labels_json = json_encode(array_keys($role_counts));
$role_data_json = json_encode(array_values($role_counts));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SMK JTI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

    <style>
        /* ====================================================================
            CSS LENGKAP (Diatur untuk layout 3x2 di desktop)
            ==================================================================== */
        :root {
            --primary-color: #1a8917; /* Hijau Daun */
            --primary-dark: #146312;  /* Hijau Lebih Gelap */
            --secondary-color: #ffffff;
            --bg-page: #f7fcf7;       /* Latar Belakang Putih Sangat Muda */
            --bg-container: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --border-color: #e0e0e0;
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

        .main-content-and-sidebar {
              display: flex;
              flex-grow: 1;
        }

        /* --- Sidebar & Navigasi (Tampilan Desktop) --- */
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
            width: 100px; height: 100px;
            border-radius: var(--border-radius-md);
            border: 4px solid var(--secondary-color);
            object-fit: contain;
            margin-bottom: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            background-color: #fff;
        }
        .user-profile .name { font-size: 1.1em; font-weight: 600; }
        .user-profile .role { font-size: 0.8em; color: rgba(255, 255, 255, 0.8); text-transform: capitalize; }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: var(--secondary-color); text-decoration: none; font-size: 0.95em; transition: background-color 0.3s ease, padding-left 0.3s ease; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255, 255, 255, 0.15); border-left: 4px solid var(--secondary-color); font-weight: 600; }
        .sidebar-menu i { margin-right: 15px; font-size: 1.1em; width: 20px; text-align: center; }
        
        .menu-toggle { display: none; position: fixed; top: 15px; left: 15px; z-index: 1010; background-color: var(--primary-dark); color: white; border: none; padding: 10px 15px; border-radius: var(--border-radius-md); cursor: pointer; font-size: 1.2em; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 999; opacity: 0; transition: opacity 0.3s ease-in-out; }
        .sidebar-overlay.active { display: block; opacity: 1; }
        
        .main-content {
            flex-grow: 1;
            padding: 30px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - var(--sidebar-width));
            min-height: calc(100vh - 80px);
        }
        
        .container {
            width: 100%;
            background: var(--bg-container);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-medium);
            margin-bottom: 30px;
        }
        
        h1 { font-size: 2em; font-weight: 700; display: flex; align-items: center; gap: 15px; border-bottom: 2px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem; }
        h1 i { color: var(--primary-color); }
        h2 { font-size: 1.5em; font-weight: 600; margin-bottom: 1.5rem; color: var(--primary-dark); }
        
        /* --- CSS UNTUK 6 KARTU STATISTIK DASHBOARD (DESKTOP) --- */
        .stats-grid {
            display: grid;
            /* **PERUBAHAN UTAMA**: Eksplisit 3 kolom lebar yang sama (3x2 layout) */
            grid-template-columns: repeat(3, 1fr); 
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background-color: var(--bg-container);
            padding: 20px;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-medium);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 6px solid #1a8917; /* Default: Hijau */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card .icon {
            font-size: 2.2em;
            color: #1a8917; /* Default: Hijau */
            background-color: #eaf7ed;
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .stat-card .info .number {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .stat-card .info .label {
            font-size: 0.9em;
            color: var(--text-light);
            font-weight: 500;
            line-height: 1.2;
        }

        /* Variasi warna untuk 6 kartu */
        .stat-card.admin-total { border-color: var(--primary-color); }
        .stat-card.admin-total .icon { color: var(--primary-color); background-color: #eaf7ed; }

        .stat-card.admin-role { border-color: #5d6d7e; } /* Abu-abu Biru untuk Role Admin */
        .stat-card.admin-role .icon { color: #5d6d7e; background-color: #e9ecef; }
        
        .stat-card.blue { border-color: #3498db; } /* Guru Mapel */
        .stat-card.blue .icon { color: #3498db; background-color: #e0f2fe; }

        .stat-card.orange { border-color: #f39c12; } /* Wali Kelas */
        .stat-card.orange .icon { color: #f39c12; background-color: #fef3c7; }
        
        .stat-card.red { border-color: #e74c3c; } /* Guru BK */
        .stat-card.red .icon { color: #e74c3c; background-color: #fae0df; }

        .stat-card.purple { border-color: #8e44ad; } /* Total Siswa */
        .stat-card.purple .icon { color: #8e44ad; background-color: #f3e5f5; }


        /* --- CSS untuk Area Grafik --- */
        .chart-container {
            width: 100%;
            height: 350px; 
            margin-top: 20px;
            padding: 15px;
            background: var(--bg-page);
            border-radius: var(--border-radius-md);
            border: 1px solid var(--border-color);
        }

        
        /* --- Footer Styling --- */
        footer {
            text-align: center;
            padding: 25px 30px;
            background-color: var(--primary-color); /* Latar belakang hijau */
            color: var(--secondary-color); /* Teks putih agar kontras */
            font-size: 0.9em;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
        }
        footer p {
            margin: 0;
            margin-bottom: 5px;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9); /* Sedikit transparan untuk p */
        }
        footer a {
            color: #87CEFA; /* Warna biru muda untuk link */
            text-decoration: none;
            font-weight: 600;
        }
        footer a:hover {
            text-decoration: underline;
        }
        
        /* Penyesuaian untuk tampilan mobile */
        @media (max-width: 992px) {
            
            /* Sembunyikan sidebar dan main content bergerak */
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { 
                transform: translateX(0); 
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            }
            
            /* Main Content mengambil seluruh lebar saat sidebar tertutup */
            .main-content, footer { 
                margin-left: 0; 
                width: 100%; 
                padding: 20px; 
            }
            
            .menu-toggle { display: block; }
            
            .container {
                padding: 20px;
                border-radius: var(--border-radius-md); 
            }

            h1 {
                font-size: 1.6em;
                padding-bottom: 0.5rem;
                margin-bottom: 0.5rem;
            }

            h2 {
                font-size: 1.3em;
            }

            /* --- Optimasi Kartu Statistik (Mobile/Tablet) --- */
            .stats-grid {
                /* Di mobile/tablet, kembali ke 2 kolom agar tidak terlalu sempit */
                grid-template-columns: repeat(2, 1fr); 
                gap: 15px;
            }
            
            .stat-card {
                flex-direction: column; 
                text-align: center;
                align-items: center;
                padding: 15px 10px;
            }

            .stat-card .icon {
                font-size: 1.8em;
                width: 45px;
                height: 45px;
                margin-bottom: 5px;
            }
            
            .stat-card .info .number {
                font-size: 1.5em;
            }
            
            .stat-card .info .label {
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>

    <div class="main-content-and-sidebar">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="../img/logosmkjt1.png" alt="Logo SMK" style="width:40px; height:40px;">
                <span>Admin Panel</span>
            </div>
            <div class="user-profile">
                <img src="<?php echo $current_user_profile_pic; ?>" alt="Foto Profil">
                <div class="name"><?php echo $current_user_name; ?></div>
                <div class="role"><?php echo $current_user_role; ?></div>
            </div>
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): // Hanya tampilkan jika role adalah Admin (Full Access) ?>
                    <a href="admin.php"><i class="fas fa-users-cog"></i> Manajemen Pengguna</a>
                <?php endif; ?>
                
                <a href="#"><i class="fas fa-user-graduate"></i> Manajemen Siswa</a>
                <a href="#"><i class="fas fa-chalkboard-teacher"></i> Manajemen Kelas</a>
                <a href="#"><i class="fas fa-book"></i> Manajemen Mapel</a>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 25px;">
                <a href="#"><i class="fas fa-cog"></i> Pengaturan</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>

        <div class="page-content-wrapper">
            <main class="main-content">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <div class="container">
                    <h2>Selamat Datang Kembali, <?php echo htmlspecialchars($current_user_name); ?>!</h2>
                    <p>Ini adalah halaman ringkasan sistem. Anda dapat melihat statistik utama di bawah ini.</p>
                    
                    <div class="stats-grid">
                        
                        <div class="stat-card admin-total">
                            <div class="icon"><i class="fas fa-users"></i></div>
                            <div class="info">
                                <div class="number"><?php echo number_format($total_pengguna); ?></div>
                                <div class="label">Total Pengguna Sistem</div>
                            </div>
                        </div>

                        <div class="stat-card admin-role">
                            <div class="icon"><i class="fas fa-user-shield"></i></div>
                            <div class="info">
                                <div class="number"><?php echo number_format($total_admin_full); ?></div>
                                <div class="label">Administrator Penuh</div>
                            </div>
                        </div>

                        <div class="stat-card purple">
                            <div class="icon"><i class="fas fa-user-graduate"></i></div>
                            <div class="info">
                                <div class="number"><?php echo number_format($total_siswa); ?></div>
                                <div class="label">Total Siswa</div>
                            </div>
                        </div>
                        
                        <div class="stat-card blue">
                            <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                            <div class="info">
                                <div class="number"><?php echo number_format($total_guru_mapel); ?></div>
                                <div class="label">Guru Mata Pelajaran</div>
                            </div>
                        </div>

                        <div class="stat-card orange">
                            <div class="icon"><i class="fas fa-user-tie"></i></div>
                            <div class="info">
                                <div class="number"><?php echo number_format($total_wali_kelas); ?></div>
                                <div class="label">Wali Kelas</div>
                            </div>
                        </div>
                        
                        <div class="stat-card red">
                            <div class="icon"><i class="fas fa-hands-helping"></i></div>
                            <div class="info">
                                <div class="number"><?php echo number_format($total_guru_bk); ?></div>
                                <div class="label">Guru Bimbingan Konseling</div>
                            </div>
                        </div>
                    </div> 
                    </div>

                <div class="container">
                    <h2>Distribusi Role Pengguna (Admin Only)</h2>
                    <div class="chart-container">
                        <canvas id="userRoleChart"></canvas>
                    </div>
                </div>
              
            </main>

            <footer>
                <p>&copy; <?php echo date('Y'); ?> SMK Jakarta Timur 1. Hak Cipta Dilindungi.</p>
                 <p>Dikembangkan oleh <a href="http://portfoliofajar.vercel.app/">Muhammad Fajarudin</a></p>
            </footer>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <button class="menu-toggle" id="menu-toggle"><i class="fas fa-bars"></i></button>

    <script>
        // --- JAVASCRIPT UNTUK TOGGLE SIDEBAR MOBILE ---
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            // Mengubah ikon pada tombol toggle
            const icon = menuToggle.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times'); // Ikon X saat terbuka
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars'); // Ikon Hamburger saat tertutup
            }
        }

        if (menuToggle) {
            menuToggle.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', toggleSidebar);
        }
        
        // ==========================================================
        // JAVASCRIPT UNTUK MENGGAMBAR GRAFIK DENGAN CHART.JS
        // ==========================================================

        document.addEventListener('DOMContentLoaded', function() {
            // 1. Ambil data dari PHP yang sudah di-encode ke JSON
            const labelsJson = '<?php echo trim($role_labels_json); ?>';
            const dataJson = '<?php echo trim($role_data_json); ?>';

            if (labelsJson === '[]' || dataJson === '[]' || labelsJson === '' || dataJson === '') {
                const container = document.querySelector('.chart-container');
                if (container) {
                    container.innerHTML = '<p style="color: var(--text-light); text-align: center; padding: 20px;">Tidak ada data role pengguna untuk ditampilkan.</p>';
                }
                return;
            }

            const labels = JSON.parse(labelsJson);
            const dataCounts = JSON.parse(dataJson);

            const ctx = document.getElementById('userRoleChart').getContext('2d');

            const chartData = {
                labels: labels, 
                datasets: [{
                    label: 'Jumlah Pengguna',
                    data: dataCounts, 
                    backgroundColor: [ 
                        'rgba(26, 137, 23, 0.8)', 
                        'rgba(52, 152, 219, 0.8)', 
                        'rgba(243, 156, 18, 0.8)', 
                        'rgba(231, 76, 60, 0.8)',  
                        'rgba(142, 68, 173, 0.8)' 
                    ],
                    borderColor: [
                        'rgba(26, 137, 23, 1)',
                        'rgba(52, 152, 219, 1)',
                        'rgba(243, 156, 18, 1)',
                        'rgba(231, 76, 60, 1)',
                        'rgba(142, 68, 173, 1)'
                    ],
                    borderWidth: 1
                }]
            };

            const chartConfig = {
                type: 'bar', 
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false, 
                    plugins: {
                        legend: {
                            display: false 
                        },
                        title: {
                            display: true,
                            text: 'Distribusi Pengguna Berdasarkan Role',
                            font: {
                                size: 16
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    if (Number.isInteger(value)) {
                                        return value;
                                    }
                                },
                                stepSize: 1 
                            }
                        }
                    }
                }
            };

            new Chart(ctx, chartConfig);
        });
    </script>

</body>
</html>