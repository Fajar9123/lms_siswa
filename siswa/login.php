<?php
// Tentukan zona waktu
date_default_timezone_set('Asia/Jakarta');

// ====================================================================
// 0. AUTENTIKASI DAN KONFIGURASI
// ====================================================================
session_start();

// Jika user sudah login (sebagai siswa), redirect ke dashboard siswa
if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'Siswa')) {
    header('Location: siswa_dashboard.php'); // Ganti dengan dashboard siswa Anda
    exit;
}

// Konfigurasi koneksi database (Sama dengan akunsiswa.php)
$host = 'localhost';
$user = 'root';
$password = ''; 
$dbname = 'smkjt1'; 
const DB_TABLE_SISWA = 'akunsiswa'; 
const LOGIN_URL = 'login.php';

// Fungsi koneksi database (ambil dari akunsiswa.php)
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

$message_type = ''; 
$message = '';

// ====================================================================
// 1. LOGIC OTENTIKASI
// ====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? ''); // NIS digunakan sebagai username
    $password_input = $_POST['password'] ?? '';

    // Cek koneksi database
    if (!$pdo) {
        $message_type = 'error';
        $message = "🚨 Koneksi database gagal! Silakan coba lagi nanti.";
    } elseif (empty($username) || empty($password_input)) {
        $message_type = 'error';
        $message = "⚠️ Username (NIS) dan Password wajib diisi.";
    } else {
        try {
            // Ambil data siswa berdasarkan NIS/Username
            $stmt = $pdo->prepare("SELECT nis, username, nama_lengkap, kelas, jurusan, password_hash FROM " . DB_TABLE_SISWA . " WHERE username = ?");
            $stmt->execute([$username]);
            $user_data = $stmt->fetch();

            if ($user_data) {
                // Verifikasi Password
                if (password_verify($password_input, $user_data['password_hash'])) {
                    // Login Berhasil!
                    $_SESSION['user_id'] = $user_data['nis']; // ID bisa pakai NIS
                    $_SESSION['user_name'] = $user_data['nama_lengkap'];
                    $_SESSION['role'] = 'Siswa'; 
                    $_SESSION['nis'] = $user_data['nis'];
                    $_SESSION['kelas'] = $user_data['kelas'];
                    
                    // Redirect ke dashboard siswa
                    header('Location: siswa_dashboard.php'); 
                    exit;

                } else {
                    // Password salah
                    $message_type = 'error';
                    $message = "❌ Password salah untuk NIS/Username: **$username**.";
                }
            } else {
                // User tidak ditemukan (NIS tidak terdaftar)
                $message_type = 'error';
                $message = "❌ NIS/Username **$username** tidak ditemukan.";
            }

        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $message_type = 'error';
            $message = "❌ Terjadi kesalahan sistem saat proses login. Mohon hubungi admin.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Siswa | SMK JTI</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #1a8917;
            --primary-highlight: #28a745;
            --bg-page: #eaf7ed; /* Latar belakang agak hijau muda */
            --bg-card: #ffffff;
            --text-dark: #2c3e50;
            --error-color: #c0392b;
            --input-border: #ced4da;
            --border-radius-md: 8px;
            --box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* --- LOGIN CARD --- */
        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--bg-card);
            padding: 40px;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            border-top: 5px solid var(--primary-highlight);
        }

        .header-login {
            text-align: center;
            margin-bottom: 30px;
        }
        .header-login h2 {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        .header-login p {
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        /* Ikon Font Awesome asli dinonaktifkan/diganti dengan .logo-img */
        /* .header-login i {
            font-size: 2.5em;
            color: var(--primary-highlight);
            margin-bottom: 15px;
        } */
        
        /* --- STYLE BARU UNTUK LOGO.PNG (UKURAN DIPERBESAR MENJADI 130px) --- */
        .header-login .logo-img {
            display: block;
            width: 130px; /* Diperbesar menjadi 130px */
            height: 130px; /* Diperbesar menjadi 130px */
            margin: 0 auto 15px auto; /* Posisikan di tengah */
            object-fit: contain; /* Memastikan gambar logo terlihat baik */
        }
        /* ---------------------------------------------------------------------- */
        
        /* --- FORM STYLING --- */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.95em;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--input-border);
            border-radius: var(--border-radius-md);
            font-size: 1em;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: var(--primary-highlight);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25); 
            outline: none;
        }
        
        .alert { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: var(--border-radius-md); 
            font-weight: 500; 
            border: 1px solid; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .alert-error { 
            background-color: #f8d7da; 
            border-color: #f5c6cb; 
            color: var(--error-color); 
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: var(--border-radius-md);
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
            margin-top: 10px;
        }
        .btn-login:hover {
            background-color: var(--primary-highlight);
            transform: translateY(-1px);
        }

        .toggle-password {
            background-color: var(--bg-page);
            border: 1px solid var(--input-border);
            border-left: none;
            border-top-right-radius: var(--border-radius-md);
            border-bottom-right-radius: var(--border-radius-md);
            padding: 0 15px;
            cursor: pointer;
            color: var(--text-dark);
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
        }
        .input-group { display: flex; align-items: stretch; width: 100%; }
        .input-group .form-control { border-top-right-radius: 0; border-bottom-right-radius: 0; flex-grow: 1; }

        .footer-links {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .footer-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }

    </style>
</head>
<body>
    
    <div class="login-card">
        
        <div class="header-login">
            <img src="../img/logojt1.png" alt="Logo SMK JTI" class="logo-img"> 
            
            <h2>Selamat Datang!</h2>
            <p>Sistem Informasi Akademik Siswa</p>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <i class="fas fa-exclamation-triangle"></i>
            <div><?php echo $message; ?></div>
        </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars(LOGIN_URL); ?>" method="POST">
            
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> NIS / Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan NIS Anda" required 
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" autofocus>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <div class="input-group">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Password minimal 6 karakter" required minlength="6">
                    <span class="toggle-password" onclick="togglePasswordVisibility()"><i class="fas fa-eye"></i></span>
                </div>
            </div>

            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> MASUK
            </button>
        </form>

        <div class="footer-links">
            <p>Bukan siswa? Login Guru <a href="../guru/login.php">Login</a></p>
        </div>

    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>