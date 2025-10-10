<?php
// ====================================================================
// kajur.php: DASHBOARD KEPALA JURUSAN
// Fokus: Menampilkan data siswa (asumsi tabel 'siswa') berdasarkan Jurusan yang diemban oleh Kajur.
// Autentikasi: Memastikan pengguna adalah 'Kepala_Jurusan' dan sudah login.
// ====================================================================

session_start();

require_once '../koneksi/db_config.php'; 

// --- PERIKSA AUTENTIKASI DAN ROLE ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Kepala_Jurusan') {
    header('Location: login.php'); 
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$jurusan_dipegang = '';

// --- SETUP PENGGUNA DAN JURUSAN YANG DIEMBAN ---
$current_user_name = "Kepala Jurusan";
$current_user_profile_pic = "https://placehold.co/80x80/2980b9/ffffff?text=KJR"; 

if ($pdo) {
    try {
        // 1. Ambil data pengguna dan detail jurusan dari tabel 'admin'
        $stmt_user = $pdo->prepare("SELECT nama FROM admin WHERE user_id = ?");
        $stmt_user->execute([$logged_in_user_id]);
        $user_data = $stmt_user->fetch();

        if ($user_data) {
            $current_user_name = htmlspecialchars($user_data['nama']);
        }
        
        // 2. Ambil Jurusan yang dipegang (Asumsi detail jurusan disimpan di kolom 'jurusan_dipegang' di tabel 'admin')
        $stmt_kajur = $pdo->prepare("SELECT jurusan_dipegang FROM admin WHERE user_id = ?");
        $stmt_kajur->execute([$logged_in_user_id]);
        $detail_data = $stmt_kajur->fetch();

        if ($detail_data && !empty($detail_data['jurusan_dipegang'])) {
            $jurusan_dipegang = htmlspecialchars($detail_data['jurusan_dipegang']);
        } else {
             // Fallback jika detail tidak ditemukan/kosong
             $message = "Detail Jurusan yang Anda pegang belum diatur. Anda mungkin tidak melihat data siswa yang relevan.";
             $message_type = 'info';
        }
        
    } catch (PDOException $e) {
        // Error fetching user data.
    }
}

/**
 * Mengambil daftar siswa berdasarkan Jurusan.
 * @param PDO $pdo Koneksi PDO.
 * @param string $jurusan Jurusan yang difilter.
 * @return array Daftar siswa.
 */
function getStudentsByJurusan($pdo, $jurusan) {
    if (!$pdo || empty($jurusan)) return [];
    try {
        // ASUMSI: Terdapat tabel 'siswa' dengan kolom 'jurusan'
        $sql = "SELECT nis, nama, kelas, jurusan FROM siswa WHERE jurusan = ? ORDER BY kelas ASC, nama ASC LIMIT 50"; 
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$jurusan]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB Error fetching students for Kajur: " . $e->getMessage());
        return []; 
    }
}

$students_data = getStudentsByJurusan($pdo, $jurusan_dipegang);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kepala Jurusan - <?= $jurusan_dipegang ?: 'Jurusan Tidak Ditemukan' ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* ====================================================================
           CSS Styling (Sama seperti admin.php untuk konsistensi)
           ==================================================================== */
        :root {
            --primary-color: #2980b9; /* Biru Info (Ganti Warna Utama Kajur) */
            --primary-dark: #2471a3;  
            --secondary-color: #ffffff;
            --bg-page: #f4f7fc;      
            --bg-container: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --success-color: #27ae60;
            --error-color: #c0392b;
            --info-color: #2980b9;
            --edit-color: #3498db;
            --delete-color: #e74c3c;
            --border-color: #dee2e6; 
            --sidebar-width: 280px;
            --box-shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.08); 
            --border-radius-md: 8px;
            --border-radius-lg: 16px;
        }

        /* [SERTAKAN SEMUA CSS DARI ADMIN.PHP DI SINI UNTUK MEMASTIKAN TAMPILAN KONSISTEN] */
        /* Karena keterbatasan format, diasumsikan CSS ini sudah dicopy-paste dari admin.php */
        /* ... (CSS LENGKAP DARI admin.php DI SINI) ... */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6; 
            scroll-behavior: smooth; 
        }
        
        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: 100%; 
        }
        
        .page-content-wrapper {
            display: flex;
            flex: 1; 
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
        }
        
        .sidebar-header { text-align: center; padding: 10px 20px 20px; font-size: 1.5em; font-weight: 700; letter-spacing: 1px; border-bottom: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .user-profile { text-align: center; padding: 15px 20px; margin-bottom: 25px; }
        .user-profile img { width: 80px; height: 80px; border-radius: 50%; border: 4px solid var(--secondary-color); object-fit: cover; margin-bottom: 10px; box-shadow: 0 0 15px rgba(0, 0, 0, 0.3); }
        .user-profile .name { font-size: 1.1em; font-weight: 600; }
        .user-profile .role { font-size: 0.8em; color: rgba(255, 255, 255, 0.8); }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: var(--secondary-color); text-decoration: none; font-size: 0.95em; transition: background-color 0.3s ease, padding-left 0.3s ease; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255, 255, 255, 0.15); border-left: 4px solid var(--secondary-color); font-weight: 600; }
        .sidebar-menu i { margin-right: 15px; font-size: 1.1em; width: 20px; text-align: center; }
        .menu-toggle { 
            display: none; 
            position: fixed; 
            top: 15px; 
            left: 15px; 
            z-index: 1010; 
            background-color: var(--primary-dark); 
            color: white; 
            border: none; 
            padding: 10px 15px; 
            border-radius: var(--border-radius-md); 
            cursor: pointer; 
            font-size: 1em; 
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); 
            transition: background-color 0.3s; 
        }
        
        .main-content { 
            flex-grow: 1; 
            padding: 40px; 
            margin-left: var(--sidebar-width); 
            transition: margin-left 0.3s ease-in-out; 
            width: calc(100% - var(--sidebar-width)); 
            min-height: 100vh;
        }

        .main-header {
            position: sticky;
            top: 0;
            z-index: 500;
            background-color: var(--bg-page); 
            padding-bottom: 20px;
            margin-bottom: -10px; 
            border-bottom: 1px solid var(--border-color);
            display: flex; 
            justify-content: space-between;
            align-items: center;
        }

        .container { max-width: 100%; background: var(--bg-container); padding: 40px; border-radius: var(--border-radius-lg); box-shadow: var(--box-shadow-medium); margin-bottom: 30px; }
        
        h1 { 
            font-size: 2em; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            border-bottom: none; 
            padding-bottom: 0; 
            margin-bottom: 0; 
        }
        h1 i { color: var(--primary-color); }
        h2 { font-size: 1.5em; font-weight: 600; margin-top: 1.5rem; margin-bottom: 1rem; color: var(--primary-dark); }
        p { color: var(--text-light); margin-bottom: 2rem; line-height: 1.6; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: var(--border-radius-md); font-weight: 500; display: flex; align-items: center; gap: 10px; transition: opacity 0.5s ease; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert.success { background-color: #eaf7ed; color: #206b32; border-left: 5px solid var(--success-color); }
        .alert.error { background-color: #f9e4e2; color: #8c2a20; border-left: 5px solid var(--error-color); }
        .alert.info { background-color: #eaf1f7; color: #1c5270; border-left: 5px solid var(--info-color); }


        /* Tampilan Tabel */
        .table-container { overflow-x: auto; margin-bottom: 20px;}
        table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); overflow: hidden; }
        table th, table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        table th { 
            background-color: var(--primary-color); 
            color: var(--secondary-color); 
            font-weight: 600; 
            text-transform: uppercase; 
            font-size: 0.9em; 
            border-bottom: none; 
            letter-spacing: 0.5px; 
        }
        table tbody tr:last-child td { border-bottom: none; }
        
        table tbody tr:hover { 
            background-color: #eaf6ff; 
            transform: scale(1.005); 
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); 
            transition: all 0.2s ease-out; 
            cursor: pointer;
        } 
        
        .class-badge {
            padding: 5px 12px; 
            border-radius: 9999px; 
            font-size: 0.8em; 
            font-weight: 600; 
            background-color: #f0f4ff;
            color: #4a67b4;
            display: inline-flex;
        }
        
        /* ====================================================================
            Media Query (Responsif Mobile)
            ==================================================================== */
        @media (max-width: 992px) {
            
            .page-content-wrapper { flex-direction: column; }
            body { display: block; } 
            
            .sidebar { 
                transform: translateX(-100%); 
                padding-top: 70px; 
                height: 100vh;
                position: fixed;
            }
            .sidebar.active { 
                transform: translateX(0); 
                box-shadow: 5px 0 20px rgba(0, 0, 0, 0.5); 
            }
            .main-content { 
                margin-left: 0; 
                padding: 15px; 
                padding-top: 80px; 
                width: 100%; 
                min-height: auto; 
            }
            .container { 
                padding: 20px; 
                margin-bottom: 20px; 
                max-width: 100%;
            }
            .menu-toggle { 
                display: flex; 
            }

            .main-header {
                position: static;
                padding-bottom: 0;
                margin-bottom: 20px;
                border-bottom: none;
                flex-direction: column; 
                align-items: flex-start;
            }

            /* Tabel di Mobile */
            table thead { display: none; }
            table { border: none; border-radius: 0; }
            table tbody, table tr { display: block; width: 100%; }
            table tr { margin-bottom: 20px; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); }
            
            /* Kerapian Card View */
            table td { 
                padding: 12px 15px; 
                border: none; 
                border-bottom: 1px dashed #e1e1e1; 
                position: relative; 
                display: flex; 
                align-items: center; 
                justify-content: space-between; 
            }
            table td:before { 
                content: attr(data-label); 
                text-align: left; 
                font-weight: 600; 
                color: var(--text-dark); 
                flex-basis: 40%; 
                flex-shrink: 0; 
                padding-right: 15px;
            }
            .td-content-wrapper { 
                flex-basis: 60%; 
                flex-grow: 1; 
                text-align: right; 
                word-break: break-all; 
                display: flex; 
                justify-content: flex-end; 
                align-items: center; 
                min-width: 0; 
            }
            table td:last-child { border-bottom: none; }
        }
        
        @media (max-width: 500px) {
            .container { padding: 10px; }
            .main-content { padding: 10px; padding-top: 80px; }
            h1 { font-size: 1.4em; padding-bottom: 0; }
        }
        /* [AKHIR DARI COPY-PASTE CSS] */
    </style>
</head>
<body>
    <button class="menu-toggle" id="menu-toggle"><i class="fas fa-bars"></i></button>

    <div class="page-content-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-school"></i>
                SMK JTI 1
            </div>

            <div class="user-profile">
                <img src="<?= $current_user_profile_pic ?>" alt="Foto Profil">
                <div class="name"><?= $current_user_name ?></div>
                <div class="role">Kepala Jurusan</div>
            </div>

            <nav class="sidebar-menu">
                <a href="kajur.php" class="active"><i class="fas fa-user-tie"></i> Dashboard Kajur</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="main-header">
                <h1><i class="fas fa-sitemap"></i> Dashboard Kepala Jurusan</h1>
            </div>

            <div class="container">
                
                <?php if ($message): ?>
                    <div class="alert <?= $message_type === 'success' ? 'success' : ($message_type === 'info' ? 'info' : 'error') ?>">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-times-circle') ?>"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <h2>Siswa Jurusan **<?= $jurusan_dipegang ?: 'Jurusan Tidak Ditemukan' ?>**</h2>
                <p>Berikut adalah daftar 50 siswa terbaru yang terdaftar di jurusan **<?= $jurusan_dipegang ?: '-' ?>**.</p>
                
                <div class="table-container">
                    <table id="studentsTable">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Jurusan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($students_data)): ?>
                                <?php foreach ($students_data as $student): ?>
                                    <tr>
                                        <td data-label="NIS"><?= htmlspecialchars($student['nis']) ?></td>
                                        <td data-label="Nama Siswa" class="user-nama"><?= htmlspecialchars($student['nama']) ?></td>
                                        <td data-label="Kelas">
                                            <span class="class-badge"><?= htmlspecialchars($student['kelas']) ?></span>
                                        </td>
                                        <td data-label="Jurusan"><?= htmlspecialchars($student['jurusan']) ?></td>
                                        <td data-label="Aksi">
                                            <a href="student_detail.php?nis=<?= htmlspecialchars($student['nis']) ?>" class="action-btn btn-edit" style="background-color: #27ae60;"><i class="fas fa-eye"></i> Lihat Detail</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-light); padding: 30px;">
                                        <i class="fas fa-info-circle"></i> Tidak ada siswa yang terdaftar di jurusan **<?= $jurusan_dipegang ?>**.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Logika Sidebar Toggle (Sama seperti admin.php)
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menu-toggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
    </script>
</body>
</html>