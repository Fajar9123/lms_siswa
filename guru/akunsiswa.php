<?php
// ====================================================================
// 1. db_config.php: KONFIGURASI DATABASE DAN FUNGSI KONEKSI (PDO)
// ====================================================================

$host = 'localhost';
$user = 'root';
$password = ''; 
$dbname = 'smkjt1'; 

const DB_TABLE = 'akunsiswa'; 

/**
 * Membuat koneksi ke database menggunakan PDO.
 * @param string $host Nama host database.
 * @param string $user Username database.
 * @param string $password Password database.
 * @param string $dbname Nama database.
 * @return PDO|null Objek PDO jika koneksi berhasil, null jika gagal.
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
        // Dalam lingkungan nyata, log error ini dan jangan tampilkan ke user!
        return null;
    }
}

// Inisialisasi koneksi global
$pdo = connectDB($host, $user, $password, $dbname);

// ====================================================================
// 2. LOGIKA PHP UNTUK MENANGANI SUBMISI FORMULIR (DENGAN PERUBAHAN VALIDASI KELAS)
// ====================================================================

$message_type = ''; 
$message = '';
$form_data = []; 
$errors = [];

$valid_jurusan = ['MP', 'TKJ', 'BR', 'BD', 'AK'];
$jurusan_map = [
    'MP' => 'Manajemen Perkantoran', 
    'TKJ' => 'Teknik Komputer Jaringan',
    'BR' => 'Bisnis Ritel',
    'BD' => 'Bisnis Digital',
    'AK' => 'Akuntansi'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo) {
        $message_type = 'error';
        $message = "Koneksi database gagal. Silakan periksa konfigurasi database Anda.";
    } else {
        // Ambil dan bersihkan data POST
        $nis = trim($_POST['nis'] ?? '');
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        // Mengambil dan membersihkan input kelas (sekarang VARCHAR bebas)
        $kelas = substr(trim($_POST['kelas'] ?? ''), 0, 100); // Batasi hingga 100 karakter sesuai DB
        $jurusan = $_POST['jurusan'] ?? '';
        $password_awal = $_POST['password_awal'] ?? '';
        $username = $nis; // Username disamakan dengan NIS

        $form_data = ['nis' => $nis, 'nama_lengkap' => $nama_lengkap, 'kelas' => $kelas, 'jurusan' => $jurusan];

        // --- Validasi Input ---
        if (empty($nis) || !preg_match('/^[0-9]+$/', $nis) || strlen($nis) < 8) {
            $errors['nis'] = "NIS wajib diisi, hanya angka, dan minimal 8 digit.";
        }
        if (empty($nama_lengkap)) {
            $errors['nama_lengkap'] = "Nama lengkap wajib diisi.";
        }
        
        // **VALIDASI KELAS BARU (UNTUK VARCHAR)**
        if (empty($kelas)) {
            $errors['kelas'] = "Nama kelas wajib diisi (Contoh: X TKJ 1 atau XII AK 3).";
        }
        
        if (!in_array($jurusan, $valid_jurusan)) {
            $errors['jurusan'] = "Pilihan jurusan tidak valid.";
        }
        if (strlen($password_awal) < 6) {
            $errors['password_awal'] = "Password minimal 6 karakter.";
        }

        // --- Cek Keunikan NIS/Username ---
        if (empty($errors) && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_TABLE . " WHERE nis = ? OR username = ?");
                $stmt->execute([$nis, $username]);
                if ($stmt->fetchColumn() > 0) {
                    $errors['nis'] = "NIS atau Username ($nis) sudah terdaftar dalam database.";
                }
            } catch (PDOException $e) {
                // Jangan tampilkan pesan error DB sensitif ini di produksi.
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
                // $kelas di-insert sebagai string/VARCHAR
                $stmt->execute([$nis, $username, $nama_lengkap, $kelas, $jurusan, $password_hash]);

                $message_type = 'success';
                $message = "🎉 Akun siswa **$nama_lengkap** dengan NIS/Username **$nis** berhasil didaftarkan!";
                $form_data = []; // Bersihkan data form setelah berhasil
            } catch (PDOException $e) {
                $message_type = 'error';
                $message = "❌ Gagal menyimpan data siswa. Cek kembali koneksi dan struktur tabel.";
            }
        } else {
            $message_type = 'error';
            $message = "⚠️ Silakan periksa kembali data. Terdapat kesalahan validasi.";
        }
    }
}

// ====================================================================
// 3. LOGIKA PHP UNTUK MENARIK DATA TABEL SISWA
// ====================================================================

$data_siswa = [];
$table_error = '';

if ($pdo) {
    try {
        // Ambil semua data (kelas sudah pasti VARCHAR)
        $stmt = $pdo->query("SELECT nis, username, nama_lengkap, kelas, jurusan, created_at FROM " . DB_TABLE . " ORDER BY created_at DESC");
        $data_siswa = $stmt->fetchAll();
    } catch (PDOException $e) {
        $table_error = "Gagal mengambil data siswa. Cek nama tabel dan koneksi.";
    }
} else {
    $table_error = "Koneksi database tidak tersedia untuk menampilkan data.";
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Akun Siswa Baru | Admin Panel SMK JTI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">

    <style>
        /* [STYLES UTAMA & SIDEBAR] */
        :root {
            --primary-color: #007bff; /* Biru Profesional/Sekolah */
            --primary-dark: #0056b3; 
            --primary-light: #50a0ff; 
            --secondary-color: #ffffff; 
            --bg-page: #f4f7fa; /* Background lebih cerah */
            --bg-container: #ffffff;
            --text-dark: #343a40; /* Hitam gelap */
            --text-light: #6c757d; 
            --success-color: #28a745; 
            --error-color: #dc3545; 
            --warning-color: #ffc107; 
            --border-color: #e9ecef; /* Border lembut */
            --sidebar-width: 250px; 
            --box-shadow-light: 0 0 15px rgba(0, 0, 0, 0.08); 
            --box-shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.1); 
            --border-radius-md: 6px; 
            --border-radius-lg: 12px;
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

        /* --- Layout Utama --- */
        .page-content-wrapper { 
            display: flex; 
            flex-direction: column; 
            flex-grow: 1; 
            width: 100%; 
        }
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
            box-shadow: var(--box-shadow-light); 
            border: 1px solid var(--border-color); 
            margin-bottom: 30px; 
        }

        /* --- Header & Typography --- */
        .page-title { 
            font-size: 1.8em; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            border-bottom: 3px solid var(--primary-light); 
            padding-bottom: 0.5rem; 
            margin-bottom: 1.5rem; 
            color: var(--text-dark); 
        }
        .page-title i { 
            color: var(--primary-color); 
            font-size: 1em;
        }
        h2 { 
            font-size: 1.3em; 
            font-weight: 600; 
            margin-bottom: 20px; 
            color: var(--primary-dark); 
            display: flex; 
            align-items: center; 
            gap: 10px;
        }

        /* --- Sidebar Style --- */
        .sidebar { 
            width: var(--sidebar-width); 
            background: var(--primary-dark);
            color: var(--secondary-color); 
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); 
            position: fixed; 
            height: 100%; 
            transition: transform 0.3s ease-in-out; 
            z-index: 1000; 
            overflow-y: auto; 
            border-right: 1px solid #004d99;
        }
        .sidebar-header {
            padding: 20px;
            font-size: 1.3em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: var(--primary-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .user-profile {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .user-profile img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 2px solid var(--primary-light);
            margin-bottom: 8px;
            object-fit: cover;
        }
        .user-profile .name {
            font-weight: 500;
            font-size: 1em;
            opacity: 0.9;
        }
        .user-profile .role {
            font-size: 0.8em;
            opacity: 0.7;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.95em;
            transition: background-color 0.2s, color 0.2s;
        }
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--secondary-color);
        }
        .sidebar-menu a.active {
            background-color: var(--primary-light);
            color: var(--text-dark);
            font-weight: 600;
            box-shadow: inset 4px 0 0 var(--success-color);
        }

        /* --- Alerts --- */
        .alert { 
            padding: 15px 20px; 
            margin-bottom: 25px; 
            border-radius: var(--border-radius-md); 
            font-weight: 500; 
            border: 1px solid; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }
        .alert-success { 
            background-color: #d4edda; 
            border-color: #c3e6cb; 
            color: #155724; 
        }
        .alert-error { 
            background-color: #f8d7da; 
            border-color: #f5c6cb; 
            color: #721c24; 
        }

        /* --- Form Styling --- */
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 25px; 
        }
        #group-nama, #group-password { 
            grid-column: 1 / -1; 
        }
        .kelas-jurusan-wrapper { 
            grid-column: 2 / -1; 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 0 20px; 
        }
        #group-nis {
            grid-column: 1 / 2;
        }

        .form-group label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 6px; 
            font-size: 0.9em; 
            color: var(--text-dark);
        }
        .form-control, select.form-control { 
            width: 100%; 
            padding: 12px 15px; 
            border: 1px solid var(--border-color); 
            border-radius: var(--border-radius-md); 
            font-size: 1em; 
            transition: border-color 0.2s, box-shadow 0.2s; 
            background-color: #ffffff;
        }
        .form-control:focus, select.form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
            outline: none;
        }
        .is-invalid { 
            border-color: var(--error-color) !important; 
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15) !important; 
        }
        .error-message { 
            color: var(--error-color); 
            font-size: 0.8em; 
            margin-top: 5px; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            font-weight: 500; 
        }
        
        /* Password Toggle Styling */
        .input-group {
            display: flex;
            align-items: stretch;
            position: relative;
        }
        .input-group input {
            padding-right: 50px;
        }
        .toggle-password {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            background: none;
            border: none;
            color: var(--text-light);
            padding: 0 15px;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: var(--primary-color);
        }

        /* --- Form Actions --- */
        .form-actions { 
            grid-column: 1 / -1; 
            margin-top: 30px; 
            display: flex; 
            gap: 15px; 
            justify-content: flex-end; 
            padding-top: 20px; 
            border-top: 1px solid var(--border-color); 
        }
        .btn { 
            padding: 10px 25px; 
            border-radius: var(--border-radius-md); 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: background-color 0.3s, box-shadow 0.3s; 
            border: none; 
            cursor: pointer; 
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); 
        }
        .btn-submit {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-submit:hover {
            background-color: var(--primary-dark);
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
        }
        .btn-cancel {
            background-color: var(--border-color);
            color: var(--text-dark);
        }
        .btn-cancel:hover {
            background-color: #d1d9e0;
        }

        /* --- Footer --- */
        footer { 
            text-align: center; 
            padding: 20px 30px; 
            background-color: var(--text-dark); 
            color: rgba(255, 255, 255, 0.7); 
            font-size: 0.85em; 
            margin-left: var(--sidebar-width); 
            width: calc(100% - var(--sidebar-width)); 
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; 
        }

        /* --- DataTables & Table Styling --- */
        #dataSiswa_wrapper { 
            padding-top: 10px;
        }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            padding: 8px 12px;
            margin-left: 5px;
        }
        table.dataTable thead th {
            background-color: var(--bg-page);
            color: var(--text-dark);
            border-bottom: 2px solid var(--primary-light);
            font-weight: 600;
        }
        table.dataTable {
            border-collapse: collapse !important;
        }
        table.dataTable tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* --- Responsive Design --- */
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
                padding-top: 70px; 
            } 
            .menu-toggle { 
                display: block; 
            } 
            footer { 
                margin-left: 0; 
                width: 100%; 
            } 
            .form-grid { 
                grid-template-columns: 1fr; 
                gap: 20px; 
            } 
            .kelas-jurusan-wrapper { 
                grid-template-columns: 1fr 1fr; 
                grid-column: 1 / -1; 
            } 
            #group-nis {
                grid-column: 1 / -1;
            }
        }
        @media (max-width: 600px) { 
            .main-content { 
                padding: 15px; 
            } 
            .kelas-jurusan-wrapper { 
                grid-template-columns: 1fr; 
            } 
            .form-actions { 
                flex-direction: column; 
                gap: 10px; 
            } 
            .container {
                padding: 20px;
            }
            .menu-toggle {
                top: 10px;
                left: 10px;
            }
        }
        /* Mobile Toggles */
        .menu-toggle { 
            display: none; 
            position: fixed; 
            top: 15px; 
            left: 15px; 
            z-index: 1010; 
            background-color: var(--primary-color); 
            color: white; 
            border: none; 
            padding: 8px 12px; 
            border-radius: var(--border-radius-md); 
            cursor: pointer; 
            font-size: 1em; 
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); 
            transition: background-color 0.3s; 
        }
        .sidebar-overlay { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5); 
            z-index: 999; 
            opacity: 0; 
            transition: opacity 0.3s ease-in-out; 
        }
        .sidebar-overlay.active { 
            display: block; 
            opacity: 1; 
        }
    </style>
</head>
<body>

    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-graduation-cap"></i>
            <span>ADMIN SMK JTI</span>
        </div>
        <div class="user-profile">
            <img src="https://via.placeholder.com/70/007bff/ffffff?text=AD" alt="Profil Admin">
            <div class="name">Administrator</div>
            <div class="role">Management Data</div>
        </div>
        <nav class="sidebar-menu">
            <a href="#"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="#" class="active"><i class="fas fa-user-plus"></i> Tambah Akun Siswa</a>
            <a href="#siswa"><i class="fas fa-users"></i> Data Semua Siswa</a>
            <a href="#pengaturan"><i class="fas fa-cogs"></i> Pengaturan Sistem</a>
            <hr style="border: 0; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 15px 20px;">
            <a href="#logout" style="color: #ffdddd;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>
    
    <div class="sidebar-overlay" onclick="toggleSidebar()" id="overlay"></div>

    <div class="page-content-wrapper">
        <main class="main-content" id="main-content">
            
            <h1 class="page-title">
                <i class="fas fa-plus-circle"></i>
                Input Akun Siswa Baru
            </h1>

            <div class="container">
                <p style="color: var(--text-light); margin-bottom: 25px; border-bottom: 1px dashed var(--border-color); padding-bottom: 15px;">
                    Gunakan formulir ini untuk membuat akun login siswa. **Nama Kelas** sekarang dapat diisi bebas (*VARCHAR*), cth: **X TKJ 1**.
                </p>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo ($message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'); ?>"></i>
                    <div><?php echo $message; ?></div>
                </div>
                <?php endif; ?>

                <form action="" method="POST" id="formSiswa">
                    
                    <div class="form-grid">
                        
                        <div class="form-group <?php echo isset($errors['nama_lengkap']) ? 'has-error' : ''; ?>" id="group-nama">
                            <label for="nama_lengkap"><i class="fas fa-user"></i> Nama Lengkap Siswa</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control <?php echo isset($errors['nama_lengkap']) ? 'is-invalid' : ''; ?>" placeholder="Masukkan nama lengkap siswa" value="<?php echo htmlspecialchars($form_data['nama_lengkap'] ?? ''); ?>" required>
                            <?php if (isset($errors['nama_lengkap'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errors['nama_lengkap']; ?></div><?php endif; ?>
                        </div>

                        <div class="form-group <?php echo isset($errors['nis']) ? 'has-error' : ''; ?>" id="group-nis">
                            <label for="nis"><i class="fas fa-id-card"></i> Nomor Induk Siswa (NIS) / Username</label>
                            <input type="text" id="nis" name="nis" class="form-control <?php echo isset($errors['nis']) ? 'is-invalid' : ''; ?>" placeholder="Contoh: 20240001 (Minimal 8 Angka)" value="<?php echo htmlspecialchars($form_data['nis'] ?? ''); ?>" required pattern="[0-9]{8,}">
                            <?php if (isset($errors['nis'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errors['nis']; ?></div><?php endif; ?>
                        </div>
                        
                        <div class="kelas-jurusan-wrapper">
                            
                            <div class="form-group <?php echo isset($errors['kelas']) ? 'has-error' : ''; ?>">
                                <label for="kelas"><i class="fas fa-school"></i> Nama Kelas Lengkap</label>
                                <input 
                                    type="text" 
                                    id="kelas" 
                                    name="kelas" 
                                    class="form-control <?php echo isset($errors['kelas']) ? 'is-invalid' : ''; ?>" 
                                    placeholder="Contoh: X TKJ 1" 
                                    value="<?php echo htmlspecialchars($form_data['kelas'] ?? ''); ?>" 
                                    required
                                    maxlength="100"
                                >
                                <?php if (isset($errors['kelas'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errors['kelas']; ?></div><?php endif; ?>
                            </div>
                            
                            <div class="form-group <?php echo isset($errors['jurusan']) ? 'has-error' : ''; ?>">
                                <label for="jurusan"><i class="fas fa-cogs"></i> Jurusan</label>
                                <select id="jurusan" name="jurusan" class="form-control <?php echo isset($errors['jurusan']) ? 'is-invalid' : ''; ?>" required>
                                    <option value="" disabled <?php echo empty($form_data['jurusan']) ? 'selected' : ''; ?>>Pilih Jurusan</option>
                                    <?php foreach ($jurusan_map as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (isset($form_data['jurusan']) && $form_data['jurusan'] == $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label) . " (" . htmlspecialchars($key) . ")"; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['jurusan'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errors['jurusan']; ?></div><?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group <?php echo isset($errors['password_awal']) ? 'has-error' : ''; ?>" id="group-password">
                            <label for="password_awal"><i class="fas fa-key"></i> Password Awal</label>
                            <div class="input-group">
                                <input type="password" id="password_awal" name="password_awal" class="form-control <?php echo isset($errors['password_awal']) ? 'is-invalid' : ''; ?>" placeholder="Minimal 6 karakter" required minlength="6">
                                <button type="button" id="togglePassword" class="toggle-password" title="Tampilkan/Sembunyikan Password"><i class="fas fa-eye"></i></button>
                            </div>
                            <?php if (isset($errors['password_awal'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errors['password_awal']; ?></div><?php endif; ?>
                        </div>

                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-cancel"><i class="fas fa-undo"></i> Reset Form</button>
                        <button type="submit" class="btn btn-submit" name="submit"><i class="fas fa-save"></i> Simpan & Buat Akun</button>
                    </div>

                </form>
            </div>

            <h1 class="page-title" style="margin-top: 50px;">
                <i class="fas fa-table"></i>
                Data Akun Siswa Terdaftar
            </h1>

            <div class="container">
                <h2><i class="fas fa-database"></i> Daftar Siswa (Total: <?php echo count($data_siswa); ?>)</h2>
                
                <?php if (isset($table_error) && !empty($table_error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> **ERROR DATABASE:** <?php echo htmlspecialchars($table_error); ?>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto; padding-top: 10px;">
                        <table id="dataSiswa" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>NIS / Username</th>
                                    <th>Nama Lengkap</th>
                                    <th>Kelas</th>
                                    <th>Jurusan</th>
                                    <th>Tanggal Dibuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($data_siswa as $siswa): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nis']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    <td>**<?php echo htmlspecialchars($siswa['kelas']); ?>**</td> 
                                    <td><?php echo htmlspecialchars($jurusan_map[$siswa['jurusan']] ?? $siswa['jurusan']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($siswa['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        
        <footer>
            <p>&copy; 2024 **SMK JTI Admin Panel**. Dibuat dengan semangat.</p>
        </footer>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawR/h/n/d0bY+fWz/X/A5B7Pz9O8UaM0zQo=" crossorigin="anonymous"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>

    <script>
        // Inisialisasi DataTables
        $(document).ready(function() {
            if ($('#dataSiswa').length) {
                $('#dataSiswa').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/2.0.8/i18n/id.json" 
                    },
                    "lengthMenu": [
                        [10, 25, 50, -1],
                        [10, 25, 50, "Semua"]
                    ], 
                    "pageLength": 10
                });
            }
        });

        // --- Sidebar Toggles ---
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('overlay').classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
        
        // --- Fungsi Show/Hide Password ---
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password_awal');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>

</body>
</html>