<?php
// ====================================================================
// admin_login.php: LOGIKA AUTENTIKASI KHUSUS ADMIN
// MENGGUNAKAN PREPARED STATEMENTS (PDO) UNTUK MENCEGAH SQL INJECTION
// ====================================================================

session_start();
// Jika sudah login, cek role. Jika bukan Admin, paksa log out atau redirect.
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'Admin') {
        header('Location: dashboard.php');
        exit;
    } else {
        // Jika Guru atau Wali Kelas mencoba mengakses URL ini, hancurkan sesi dan redirect
        session_destroy();
        // Menggunakan urlencode untuk pesan yang aman saat redirect
        header('Location: admin_login.php?status=error&msg=' . urlencode('Akses ditolak. Silakan login sebagai Admin.'));
        exit;
    }
}

// Sertakan konfigurasi database
// Pastikan file db_config.php sudah tersedia dan menginisialisasi $pdo
require_once '../koneksi/db_config.php';

$message = '';
$message_type = '';
$nip = ''; // Inisialisasi variabel untuk mengisi kembali form

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip = trim($_POST['nip'] ?? '');
    $password_plain = $_POST['password'] ?? '';

    if (empty($nip) || empty($password_plain)) {
        $message = "NIP dan Password wajib diisi!";
        $message_type = 'error';
    } elseif (!isset($pdo) || $pdo === null) { 
        $message = "Koneksi database gagal. Silakan coba lagi.";
        $message_type = 'error';
    } else {
        try {
            // PERUBAHAN UTAMA DI SINI: Ganti 'users' menjadi 'admin'
            $stmt = $pdo->prepare("SELECT user_id, password, role FROM admin WHERE nip = ?");
            // Data pengguna ($nip) dieksekusi terpisah, menjadikannya aman.
            $stmt->execute([$nip]); 
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifikasi Password menggunakan password_verify()
            if ($user && password_verify($password_plain, $user['password'])) {
                
                // 2. CEK ROLE PENTING: Hanya izinkan role Admin
                if ($user['role'] === 'Admin') {
                    // Login Berhasil
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role'] = $user['role'];

                    // Redirect ke Dashboard Admin
                    header('Location: dashboard.php');
                    exit;

                } else {
                    // Ditemukan pengguna, tapi bukan Admin
                    $message = "Anda tidak memiliki hak akses Admin.";
                    $message_type = 'error';
                }

            } else {
                // Login Gagal (NIP atau Password salah)
                $message = "NIP atau Password salah!";
                $message_type = 'error';
            }

        } catch (PDOException $e) {
            // Catat error database untuk debugging internal
            error_log("Database Error in admin_login: " . $e->getMessage());
            $message = "Terjadi kesalahan sistem. Coba lagi nanti.";
            $message_type = 'error';
        }
    }
}

// Ambil pesan dari Redirect (jika ada)
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $message_type = $_GET['status'];
    // htmlspecialchars() mencegah XSS dari pesan error di URL
    $message = htmlspecialchars($_GET['msg']); 
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Sistem Sekolah</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #0d6efd; /* Warna biru untuk Admin */
            --primary-dark: #0a58ca;
            --secondary-color: #ffffff;
            --bg-page: #f0f4f8;
            --text-dark: #2c3e50;
            --success-color: #27ae60;
            --error-color: #c0392b;
            --border-radius-md: 10px;
            --box-shadow-login: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-container {
            background-color: var(--secondary-color);
            padding: 40px;
            border-radius: var(--border-radius-md);
            box-shadow: var(--box-shadow-login);
            width: 100%;
            max-width: 400px;
            transition: all 0.3s ease;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .header p {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.9em;
            color: var(--text-dark);
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: var(--border-radius-md);
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2); /* Sesuai warna Admin */
        }
        .password-toggle-icon {
            position: absolute;
            right: 15px;
            top: 60%; 
            transform: translateY(-50%); 
            color: #ccc;
            cursor: pointer;
            transition: color 0.3s;
        }
        .password-toggle-icon:hover { color: var(--primary-color); }

        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: var(--secondary-color);
            border: none;
            border-radius: var(--border-radius-md);
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s, box-shadow 0.3s;
            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3); /* Sesuai warna Admin */
            margin-top: 10px;
        }
        button[type="submit"]:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(13, 110, 253, 0.4);
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius-md);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s;
        }
        .alert.error { background-color: #f9e4e2; color: var(--error-color); border-left: 5px solid var(--error-color); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <h1><i class="fas fa-user-shield" style="color:var(--primary-dark);"></i> PANEL ADMIN</h1>
            <p>Sistem Informasi Sekolah</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $message_type; ?>">
                <i class="fas fa-times-circle"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php">
            <div class="form-group">
                <label for="nip">NIP Admin</label>
                <input type="text" id="nip" name="nip" placeholder="Masukkan NIP Admin" required value="<?php echo htmlspecialchars($nip); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div style="position: relative;">
                    <input type="password" id="password" name="password" placeholder="Masukkan Password" required>
                    <i class="fas fa-eye password-toggle-icon" id="togglePassword"></i>
                </div>
            </div>
            
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> MASUK SEBAGAI ADMIN
            </button>
        </form>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function (e) {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
            this.classList.toggle('fa-eye');
        });
    </script>
</body>
</html>