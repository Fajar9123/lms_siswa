<?php
// Tentukan zona waktu untuk fungsi tanggal
date_default_timezone_set('Asia/Jakarta');

// ====================================================================
// 0. AUTENTIKASI DAN SETUP PROFIL
// ====================================================================
session_start();

// Asumsikan user_id dan role disimpan di session saat login
// Jika user belum login, redirect
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Ganti dengan halaman login Anda
    exit;
}

// **PERHATIAN: Halaman ini seharusnya hanya diakses oleh Admin atau Role tertentu yang berwenang**
$allowed_roles = ['Admin', 'Tata_Usaha', 'Wali_Kelas']; 
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: unauthorized.php'); // Halaman akses ditolak
    exit;
}

// Data Utama Profil (Akan diambil dari DB)
$logged_in_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'] ?? "Pengguna Tidak Dikenal"; // Gunakan default dari session
$current_user_role = str_replace('_', ' ', ($_SESSION['role'] ?? 'Undefined Role')); 
$current_user_profile_pic = "../img/logosmkjt1.png"; // PATH GAMBAR DEFAULT
$assigned_class_name = ""; 

// ====================================================================
// 1. KONFIGURASI DAN FUNGSI KONEKSI DATABASE (PDO)
// ====================================================================

// --- KONFIGURASI KONEKSI DATABASE ---
$host = 'localhost';
$user = 'root';
$password = ''; 
$dbname = 'smkjt1'; 

const DB_TABLE = 'akunsiswa'; 
const SELF_URL = 'akunsiswa.php';

/**
 * Membuat koneksi ke database menggunakan PDO.
 */
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

// Inisialisasi koneksi global
$pdo = connectDB($host, $user, $password, $dbname);

// --- SETUP PROFIL ---
if ($pdo) {
    try {
        $stmt_user = $pdo->prepare("SELECT nama, role, profile_pic FROM admin WHERE user_id = ?");
        $stmt_user->execute([$logged_in_user_id]);
        $current_user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($current_user_data) {
            $current_user_name = htmlspecialchars($current_user_data['nama']);
            $current_user_role = str_replace('_', ' ', htmlspecialchars($current_user_data['role']));
            if (!empty($current_user_data['profile_pic'])) {
                $current_user_profile_pic = htmlspecialchars($current_user_data['profile_pic']);
            }
        }
        
        // Ambil penugasan kelas jika dia Wali Kelas
        if ($_SESSION['role'] === 'Wali_Kelas') {
             $stmt_assignment = $pdo->prepare("SELECT kelas_dipegang FROM wali_kelas_assignment WHERE user_id = ? ORDER BY tahun_ajaran DESC LIMIT 1");
             $stmt_assignment->execute([$logged_in_user_id]);
             $assignment_data = $stmt_assignment->fetch(PDO::FETCH_ASSOC);
             $assigned_class_name = $assignment_data ? htmlspecialchars($assignment_data['kelas_dipegang']) : "";
        }

    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}
// --- END SETUP PROFIL ---

// ====================================================================
// 2. DATA REFERENSI
// ====================================================================
$valid_jurusan = ['MP', 'TKJ', 'BR', 'BD', 'AK'];
$jurusan_map = [
    'MP' => 'Manajemen Perkantoran', 
    'TKJ' => 'Teknik Komputer Jaringan',
    'BR' => 'Bisnis Ritel',
    'BD' => 'Bisnis Digital',
    'AK' => 'Akuntansi'
];

$message_type = ''; 
$message = '';
$form_data = []; 
$errors = [];

// ====================================================================
// 3. FUNGSI CRUD LOGIC
// ====================================================================

/**
 * Menangani proses CREATE data siswa baru.
 */
function handleCreate(PDO $pdo, array $postData, array $validJurusan, array &$errors) {
    $nis = trim($postData['nis'] ?? '');
    $nama_lengkap = trim($postData['nama_lengkap'] ?? '');
    $kelas = substr(trim($postData['kelas'] ?? ''), 0, 100); 
    $jurusan = $postData['jurusan'] ?? '';
    $password_awal = $postData['password_awal'] ?? '';
    $username = $nis; 

    $formData = ['nis' => $nis, 'nama_lengkap' => $nama_lengkap, 'kelas' => $kelas, 'jurusan' => $jurusan];

    // --- Validasi Input ---
    if (empty($nis) || !preg_match('/^[0-9]+$/', $nis) || strlen($nis) < 8) {
        $errors['nis'] = "NIS wajib diisi, hanya angka, dan minimal 8 digit.";
    }
    if (empty($nama_lengkap)) {
        $errors['nama_lengkap'] = "Nama lengkap wajib diisi.";
    }
    if (empty($kelas)) {
        $errors['kelas'] = "Nama kelas wajib diisi (Contoh: X TKJ 1 atau XII AK 3).";
    }
    if (!in_array($jurusan, $validJurusan)) {
        $errors['jurusan'] = "Pilihan jurusan tidak valid.";
    }
    if (strlen($password_awal) < 6) {
        $errors['password_awal'] = "Password minimal 6 karakter.";
    }

    // --- Cek Keunikan NIS/Username ---
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_TABLE . " WHERE nis = ? OR username = ?");
            $stmt->execute([$nis, $username]);
            if ($stmt->fetchColumn() > 0) {
                $errors['nis'] = "NIS atau Username ($nis) sudah terdaftar dalam database.";
            }
        } catch (PDOException $e) {
            $errors['db'] = "Terjadi kesalahan saat memeriksa keunikan data."; 
        }
    }
    
    // --- Simpan Data Jika Tidak Ada Error ---
    if (empty($errors)) {
        $password_hash = password_hash($password_awal, PASSWORD_DEFAULT);
        
        try {
            $sql = "INSERT INTO " . DB_TABLE . " (nis, username, nama_lengkap, kelas, jurusan, password_hash) 
                             VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nis, $username, $nama_lengkap, $kelas, $jurusan, $password_hash]);

            return ['type' => 'success', 'message' => "🎉 Akun siswa **$nama_lengkap** dengan NIS/Username **$nis** berhasil didaftarkan!", 'data' => []];
        } catch (PDOException $e) {
            return ['type' => 'error', 'message' => "❌ Gagal menyimpan data siswa. Cek kembali koneksi dan struktur tabel. Error: " . htmlspecialchars($e->getMessage()), 'data' => $formData];
        }
    } else {
        return ['type' => 'error', 'message' => "⚠️ Silakan periksa kembali data. Terdapat kesalahan validasi.", 'data' => $formData];
    }
}

/**
 * Menangani proses DELETE data siswa.
 */
function handleDelete(PDO $pdo, $nis) {
    $nis_to_delete = trim($nis);
    try {
        if (!preg_match('/^[0-9]+$/', $nis_to_delete)) {
            throw new Exception("NIS tidak valid.");
        }
        
        $stmt = $pdo->prepare("DELETE FROM " . DB_TABLE . " WHERE nis = ?");
        $stmt->execute([$nis_to_delete]);

        if ($stmt->rowCount() > 0) {
            return ['type' => 'success', 'message' => "🗑️ Akun siswa dengan NIS **$nis_to_delete** berhasil dihapus."];
        } else {
            return ['type' => 'error', 'message' => "❌ Gagal menghapus. Akun siswa dengan NIS **$nis_to_delete** tidak ditemukan."];
        }
    } catch (Exception $e) {
        return ['type' => 'error', 'message' => "❌ Terjadi kesalahan saat menghapus data: " . htmlspecialchars($e->getMessage())];
    }
}

/**
 * Mengambil semua data siswa dari database.
 */
function getSiswaData(PDO $pdo, array $jurusanMap) {
    try {
        $stmt = $pdo->query("SELECT nis, username, nama_lengkap, kelas, jurusan, created_at FROM " . DB_TABLE . " ORDER BY created_at DESC");
        $data_siswa = $stmt->fetchAll();
        return ['data' => $data_siswa, 'error' => ''];
    } catch (PDOException $e) {
        return ['data' => [], 'error' => "Gagal mengambil data siswa. Cek nama tabel dan koneksi."];
    }
}


// ====================================================================
// 4. MAIN CONTROLLER / ROUTER LOGIC
// ====================================================================

if ($pdo) {
    // 4.1. Handle POST (CREATE)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
        $result = handleCreate($pdo, $_POST, $valid_jurusan, $errors);
        $message_type = $result['type'];
        $message = $result['message'];
        $form_data = $result['data'];
    }

    // 4.2. Handle GET (DELETE)
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['nis'])) {
        $result = handleDelete($pdo, $_GET['nis']);
        
        // Redirect with message to prevent resubmission (Post/Redirect/Get pattern)
        $redirect_url = SELF_URL . "?msg_type=" . $result['type'] . "&msg=" . urlencode($result['message']) . "&t=" . time() . "#data-siswa-table";
        header("Location: " . $redirect_url);
        exit();
    }
    
    // 4.3. Handle Pesan dari Redirect (PRG)
    if (isset($_GET['msg_type']) && isset($_GET['msg'])) {
        $message_type = $_GET['msg_type'];
        // Pastikan pesan aman dari XSS
        $message = htmlspecialchars(urldecode($_GET['msg'])); 
    }

} else {
    // Database connection failed handler
    $message_type = 'error';
    $message = "🚨 Koneksi database gagal! Periksa `\$host`, `\$user`, `\$password`, `\$dbname` di awal kode.";
}

// 4.4. Load Data for Table
$siswa_data_result = $pdo ? getSiswaData($pdo, $jurusan_map) : ['data' => [], 'error' => 'Koneksi database tidak tersedia.'];
$data_siswa = $siswa_data_result['data'];
$table_error = $siswa_data_result['error'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Akun Siswa | <?php echo $current_user_name; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/3.0.0/css/responsive.dataTables.min.css">

    <style>
        /* ====================================================================
            STYLES TEMA HIJAU PUTIH (DIOPTIMALKAN UNTUK DESKTOP)
            ==================================================================== */
        :root {
            --primary-color: #1a8917; /* Hijau Daun (Untuk Sidebar) */
            --primary-dark: #146312;  /* Hijau Lebih Gelap (Untuk Background Sidebar) */
            --primary-highlight: #28a745; /* Hijau Cerah (Untuk Konten Utama) */
            --secondary-color: #ffffff;
            --bg-page: #f7fcf7;       
            --bg-container: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --success-color: #27ae60;
            --error-color: #c0392b;
            --info-color: #3498db;
            --warning-color: #f39c12;
            --border-color: #e0e0e0; 
            --input-border: #ced4da;
            --box-shadow-light: 0 4px 20px rgba(0, 0, 0, 0.05);
            --border-radius-sm: 4px;
            --border-radius-md: 8px;
            --border-radius-lg: 16px;
            --sidebar-width: 280px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* --- SIDEBAR (Fixed for Desktop) --- */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--secondary-color);
            padding: 24px 0;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.15);
            position: fixed;
            height: 100%;
            top: 0; /* Pastikan menempel di atas */
            left: 0; /* Pastikan menempel di kiri */
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-header { text-align: center; padding: 10px 20px 20px; font-size: 1.5em; font-weight: 700; letter-spacing: 1px; border-bottom: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .user-profile { text-align: center; padding: 15px 20px; margin-bottom: 25px; }
        .user-profile img {
            width: 80px; 
            height: 80px;
            border-radius: var(--border-radius-md);
            border: 3px solid var(--secondary-color);
            object-fit: contain;
            margin-bottom: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            background-color: #fff;
        }
        .user-profile .name { font-size: 1.1em; font-weight: 600; }
        .user-profile .role { font-size: 0.8em; color: rgba(255, 255, 255, 0.8); }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: var(--secondary-color); text-decoration: none; font-size: 0.95em; transition: background-color 0.3s ease, padding-left 0.3s ease; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255, 255, 255, 0.15); border-left: 4px solid var(--secondary-color); font-weight: 600; }
        .sidebar-menu i { margin-right: 15px; font-size: 1.1em; width: 20px; text-align: center; }
        .sidebar-menu hr { border: 0; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 15px 25px; }


        /* --- MAIN CONTENT & CONTAINERS (Desktop Spacing) --- */
        .page-content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-content {
            flex-grow: 1;
            padding: 30px;
            margin-left: var(--sidebar-width); /* KUNCI DESKTOP: Geser konten */
            width: calc(100% - var(--sidebar-width));
            max-width: 1200px; 
            margin-right: auto;
            transition: margin-left 0.3s ease-in-out;
        }
        
        .container { 
            width: 100%; 
            background: var(--bg-container); 
            padding: 30px; 
            border-radius: var(--border-radius-lg); 
            box-shadow: var(--box-shadow-light); 
            border: 1px solid var(--border-color); 
            margin-bottom: 30px; 
        }
        
        /* --- TYPOGRAPHY & HEADERS --- */
        .page-title { 
            font-size: 2em; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            border-bottom: 2px solid var(--primary-highlight); 
            padding-bottom: 0.75rem; 
            margin-bottom: 2rem; 
            color: var(--text-dark); 
        }
        .page-title i { 
            color: var(--primary-highlight); 
            font-size: 1.1em;
        }
        h2 { 
            font-size: 1.5em; 
            font-weight: 600; 
            margin-bottom: 25px; 
            color: var(--text-dark); 
            display: flex; 
            align-items: center; 
            gap: 10px;
        }
        
        /* --- ALERTS --- */
        .alert { 
            padding: 18px 25px; 
            margin-bottom: 30px; 
            border-radius: var(--border-radius-md); 
            font-weight: 500; 
            border: 1px solid; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .alert-success { background-color: #eaf7ed; border-color: #b1dfbb; color: var(--success-color); }
        .alert-error { background-color: #f8d7da; border-color: #f5c6cb; color: var(--error-color); }
        
        /* --- FORM STYLING (GRID LAYOUT for Desktop) --- */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }
        .kelas-jurusan-wrapper {
            grid-column: span 2;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.95em;
            color: var(--text-dark);
        }
        .form-group label i {
            color: var(--primary-highlight);
            margin-right: 5px;
        }
        .form-control, select.form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--input-border);
            border-radius: var(--border-radius-sm);
            font-size: 1em;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus, select.form-control:focus {
            border-color: var(--primary-highlight);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25); 
            outline: none;
            background-color: #ffffff;
        }
        .form-group.has-error .form-control,
        .form-group.has-error select.form-control {
            border-color: var(--error-color);
        }
        .error-message {
            color: var(--error-color);
            font-size: 0.85em;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Input Group untuk Password Toggle */
        .input-group { display: flex; align-items: stretch; width: 100%; }
        .input-group .form-control { border-top-right-radius: 0; border-bottom-right-radius: 0; flex-grow: 1; }
        .toggle-password {
            background-color: var(--border-color);
            border: 1px solid var(--input-border);
            border-left: 0;
            border-top-right-radius: var(--border-radius-sm);
            border-bottom-right-radius: var(--border-radius-sm);
            padding: 0 15px;
            cursor: pointer;
            color: var(--text-light);
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Form Actions */
        .form-actions {
            grid-column: span 2;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            margin-top: 10px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-submit { background-color: var(--primary-highlight); color: white; box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2); }
        .btn-submit:hover { background-color: var(--success-color); box-shadow: 0 6px 15px rgba(40, 167, 69, 0.4); transform: translateY(-2px); }
        .btn-cancel { background-color: var(--text-light); color: white; }
        .btn-cancel:hover { background-color: #6c757d; }
        
        /* --- DATATABLES STYLING --- */
        .table-responsive { overflow-x: auto; }
        table.dataTable { width: 100% !important; border-collapse: collapse; }
        table.dataTable thead th {
            background-color: #eaf7ed; 
            color: var(--text-dark);
            border-bottom: 3px solid var(--primary-highlight);
            font-weight: 600;
            padding: 15px 12px;
            text-align: left;
        }
        table.dataTable tbody tr { border-bottom: 1px solid var(--border-color); }
        table.dataTable tbody td { padding: 12px; vertical-align: middle; font-size: 0.95em; }
        table.dataTable tbody tr:nth-child(even) { background-color: #fcfcfc; }

        /* Style for Action Buttons in Table */
        .action-btns {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
            min-width: 120px;
        }
        .btn-action {
            padding: 6px 12px;
            font-size: 0.85em;
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            transition: opacity 0.2s, transform 0.2s;
            white-space: nowrap;
        }
        .btn-edit { background-color: var(--info-color); color: white; }
        .btn-delete { background-color: var(--error-color); color: white; }

        /* Footer (Desktop Spacing) */
        footer { 
            text-align: center; 
            padding: 25px 30px; 
            background-color: var(--primary-dark); 
            color: rgba(255, 255, 255, 0.95); 
            font-size: 0.9em; 
            width: calc(100% - var(--sidebar-width)); /* KUNCI DESKTOP: Sesuaikan lebar footer */
            margin-top: auto; 
            margin-left: var(--sidebar-width); 
            transition: margin-left 0.3s ease-in-out;
        }

        /* --- RESPONSIVE DESIGN (Mobile adjustments) --- */
        .menu-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            padding: 10px 15px;
            background-color: var(--primary-color);
            color: var(--secondary-color);
            border: none;
            border-radius: 5px;
            font-size: 1.2em;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            display: none; /* Sembunyikan di desktop */
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
            transition: opacity 0.3s;
        }
        .sidebar-overlay.active { display: block; }
        
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                left: 0;
            }
            .sidebar.active { transform: translateX(0); }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding-top: 80px;
            }
            .menu-toggle { display: block; }
            footer {
                margin-left: 0;
                width: 100%;
            }
        }
        @media (max-width: 768px) {
            .page-title { font-size: 1.6em; }
            .container { padding: 20px; }
            .form-grid { 
                grid-template-columns: 1fr; 
                gap: 20px;
            } 
            .kelas-jurusan-wrapper { 
                grid-template-columns: 1fr; 
                gap: 20px;
                grid-column: span 1;
            } 
            .form-actions {
                grid-column: span 1;
                justify-content: space-between;
                flex-direction: column; 
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .action-btns {
                flex-direction: column; 
                gap: 5px;
                align-items: flex-start;
                min-width: 80px;
            }
            .btn-action {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-school"></i>
            <span>SMK JTI</span>
        </div>
        <div class="user-profile">
            <img src="<?php echo $current_user_profile_pic; ?>" alt="Profil <?php echo $current_user_name; ?>">
            <div class="name"><?php echo $current_user_name; ?></div>
            <div class="role"><?php echo $current_user_role; ?></div>
            <?php if ($_SESSION['role'] === 'Wali_Kelas' && !empty($assigned_class_name)): ?>
                 <div class="role" style="font-style: italic; color: #ffdddd;">(Wali Kelas: <?php echo $assigned_class_name; ?>)</div>
            <?php endif; ?>
        </div>
        
        <nav class="sidebar-menu">
            <?php if ($_SESSION['role'] === 'Wali_Kelas'): ?>
                <a href="wali_kelas_dashboard.php"><i class="fas fa-chalkboard-teacher"></i> Dashboard Kelas</a>
                <a href="<?php echo htmlspecialchars(SELF_URL); ?>" class="active"><i class="fas fa-user-friends"></i> Management Siswa</a>
                <a href="#absensi"><i class="fas fa-user-check"></i> Kelola Absensi</a>
                <a href="#nilai"><i class="fas fa-clipboard-list"></i> Input Nilai Sikap</a>
            <?php else: // Menu untuk Admin/TU ?>
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard Admin</a>
                <a href="<?php echo htmlspecialchars(SELF_URL); ?>" class="active"><i class="fas fa-user-friends"></i> Management Siswa</a>
                <a href="akun_guru.php"><i class="fas fa-users-cog"></i> Management Guru</a>
                <a href="setting_ta.php"><i class="fas fa-calendar-alt"></i> Setting T.A.</a>
            <?php endif; ?>
            
            <hr>
            <a href="profil.php"><i class="fas fa-cog"></i> Pengaturan Profil</a>
            <a href="logout.php" style="color: #ffdddd;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>
    <div class="sidebar-overlay" onclick="toggleSidebar()" id="overlay"></div>

    <div class="page-content-wrapper">
        <main class="main-content">
            
            <a id="input-akun"></a>
            <header>
                <h1 class="page-title">
                    <i class="fas fa-user-plus"></i>
                    Input Akun Siswa Baru
                </h1>
            </header>

            <div class="container">
                <p style="color: var(--text-light); margin-bottom: 25px; border-bottom: 1px dashed var(--border-color); padding-bottom: 15px;">
                    📝 Formulir pembuatan akun login siswa. **NIS** akan digunakan sebagai **Username**.
                </p>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                    <i class="fas <?php echo ($message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'); ?>"></i>
                    <div><?php echo $message; ?></div>
                </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars(SELF_URL); ?>" method="POST" id="formSiswa">
                    
                    <div class="form-grid">
                        
                        <div class="form-group <?php echo isset($errors['nama_lengkap']) ? 'has-error' : ''; ?>" id="group-nama">
                            <label for="nama_lengkap"><i class="fas fa-user"></i> Nama Lengkap Siswa</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" placeholder="Masukkan nama lengkap siswa" value="<?php echo htmlspecialchars($form_data['nama_lengkap'] ?? ''); ?>" required>
                            <?php if (isset($errors['nama_lengkap'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['nama_lengkap']); ?></div><?php endif; ?>
                        </div>

                        <div class="form-group <?php echo isset($errors['nis']) ? 'has-error' : ''; ?>" id="group-nis">
                            <label for="nis"><i class="fas fa-id-card"></i> Nomor Induk Siswa (NIS) / Username</label>
                            <input type="text" id="nis" name="nis" class="form-control" placeholder="Contoh: 20240001 (Minimal 8 Angka)" value="<?php echo htmlspecialchars($form_data['nis'] ?? ''); ?>" required pattern="[0-9]{8,}">
                            <?php if (isset($errors['nis'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['nis']); ?></div><?php endif; ?>
                        </div>
                        
                        <div class="kelas-jurusan-wrapper">
                            
                            <div class="form-group <?php echo isset($errors['kelas']) ? 'has-error' : ''; ?>">
                                <label for="kelas"><i class="fas fa-school"></i> Nama Kelas Lengkap</label>
                                <input 
                                    type="text" 
                                    id="kelas" 
                                    name="kelas" 
                                    class="form-control" 
                                    placeholder="Contoh: X TKJ 1" 
                                    value="<?php echo htmlspecialchars($form_data['kelas'] ?? ''); ?>" 
                                    required
                                    maxlength="100"
                                >
                                <?php if (isset($errors['kelas'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['kelas']); ?></div><?php endif; ?>
                            </div>
                            
                            <div class="form-group <?php echo isset($errors['jurusan']) ? 'has-error' : ''; ?>">
                                <label for="jurusan"><i class="fas fa-building"></i> Jurusan</label>
                                <select id="jurusan" name="jurusan" class="form-control" required>
                                    <option value="">-- Pilih Jurusan --</option>
                                    <?php foreach ($jurusan_map as $code => $name): ?>
                                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo (isset($form_data['jurusan']) && $form_data['jurusan'] == $code) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?> (<?php echo htmlspecialchars($code); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['jurusan'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['jurusan']); ?></div><?php endif; ?>
                            </div>
                            
                        </div>
                        
                        <div class="form-group <?php echo isset($errors['password_awal']) ? 'has-error' : ''; ?>" style="grid-column: span 2;">
                            <label for="password_awal"><i class="fas fa-lock"></i> Password Awal</label>
                            <div class="input-group">
                                <input type="password" id="password_awal" name="password_awal" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                                <span class="toggle-password" onclick="togglePasswordVisibility('password_awal')">
                                    <i class="fas fa-eye-slash" id="toggleIcon_password_awal"></i>
                                </span>
                            </div>
                            <?php if (isset($errors['password_awal'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['password_awal']); ?></div><?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="reset" class="btn btn-cancel"><i class="fas fa-eraser"></i> Reset</button>
                            <button type="submit" name="submit" class="btn btn-submit"><i class="fas fa-save"></i> Daftarkan Siswa</button>
                        </div>

                    </div>
                </form>
            </div>

            <a id="data-siswa-table"></a>
            <h1 class="page-title" style="margin-top: 50px;">
                <i class="fas fa-table"></i>
                Data Akun Siswa Terdaftar
            </h1>

            <div class="container">
                <h2><i class="fas fa-list-alt"></i> Daftar Akun Siswa (Total: <?php echo count($data_siswa); ?>)</h2>
                
                <?php if (!empty($table_error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><?php echo $table_error; ?></div>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table id="siswaTable" class="dataTable display responsive nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NIS/Username</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Jurusan</th>
                                <th>Didaftarkan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php foreach ($data_siswa as $siswa): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><span style="font-weight: 600;"><?php echo htmlspecialchars($siswa['nis']); ?></span></td>
                                <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                <td><?php echo htmlspecialchars($siswa['kelas']); ?></td>
                                <td><?php echo htmlspecialchars($jurusan_map[$siswa['jurusan']] ?? $siswa['jurusan']); ?></td>
                                <td><?php echo date('d-m-Y H:i', strtotime($siswa['created_at'])); ?></td>
                                <td class="action-btns">
                                    <a href="edit_siswa.php?nis=<?php echo htmlspecialchars($siswa['nis']); ?>" class="btn-action btn-edit" title="Edit Data">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="<?php echo htmlspecialchars(SELF_URL); ?>?action=delete&nis=<?php echo htmlspecialchars($siswa['nis']); ?>" 
                                       class="btn-action btn-delete" 
                                       title="Hapus Akun"
                                       onclick="return confirm('❗ Yakin ingin menghapus akun siswa: <?php echo htmlspecialchars($siswa['nama_lengkap']); ?> (NIS: <?php echo htmlspecialchars($siswa['nis']); ?>)? Tindakan ini tidak dapat dibatalkan.');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </main>

        <footer>
            Hak Cipta &copy; <?php echo date('Y'); ?> SMK JATI. Dikelola oleh Tim <?php echo $current_user_role; ?>.
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.0/js/dataTables.responsive.min.js"></script>
    <script>
        // INISIALISASI DATATABLES
        $(document).ready(function() {
            $('#siswaTable').DataTable({
                responsive: true,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/id.json' 
                },
                columnDefs: [
                    { responsivePriority: 1, targets: 2 }, // Nama Lengkap prioritas tinggi
                    { responsivePriority: 2, targets: 6 }, // Aksi prioritas tinggi
                    { responsivePriority: 3, targets: 1 }, // NIS prioritas sedang
                    { orderable: false, targets: 6 } // Kolom Aksi tidak bisa diurutkan
                ]
            });

            // SCROLL TO MESSAGE
            <?php if (!empty($message)): ?>
                // Scroll ke bagian atas halaman untuk melihat pesan
                $('html, body').animate({
                    scrollTop: 0
                }, 'slow');
            <?php endif; ?>
        });

        // FUNGSI TOGGLE SIDEBAR (UNTUK MOBILE)
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // FUNGSI TOGGLE PASSWORD VISIBILITY
        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const icon = document.getElementById('toggleIcon_' + id);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
    </script>
</body>
</html>