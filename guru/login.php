<?php
// ====================================================================
// login.php: HALAMAN LOGIN KHUSUS UNTUK USER NON-ADMIN
// ====================================================================

session_start();

// Sertakan file koneksi database
require_once '../koneksi/db_config.php'; 

// Fungsi untuk mengarahkan ke dashboard yang benar berdasarkan role
function redirectToDashboard($role) {
    switch ($role) {
        case 'Guru_Mapel':
            header('Location: guru_dashboard.php'); // Path untuk Guru Mapel
            exit;
        case 'Wali_Kelas':
            header('Location: wali_kelas_dashboard.php'); // Path untuk Wali Kelas
            exit;
        case 'Kepala_Jurusan':
            header('Location: kajur.php'); // Path untuk Kepala Jurusan
            exit;
        case 'Guru_BK':
            header('Location: gurbk.php'); // Path untuk Guru BK
            exit;
        default:
            session_destroy();
            header('Location: login.php?error=invalid_role');
            exit;
    }
}


$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($pdo) || !$pdo) {
        $error_message = "Koneksi database gagal. Silakan coba lagi.";
    } else {
        $nip = trim($_POST['nip'] ?? '');
        $password = $_POST['password'] ?? ''; 

        if (empty($nip) || empty($password)) {
            $error_message = "NIP dan Password wajib diisi.";
        } else {
            try {
                // MENGGUNAKAN PREPARED STATEMENTS (ANTI SQL INJECTION)
                $stmt = $pdo->prepare("SELECT user_id, nama, password, role FROM admin WHERE nip = ?");
                $stmt->execute([$nip]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) { 
                    
                    $non_admin_roles = ['Guru_Mapel', 'Wali_Kelas', 'Kepala_Jurusan', 'Guru_BK'];
                    if (!in_array($user['role'], $non_admin_roles)) {
                        $error_message = "NIP atau Password salah. Mohon periksa kembali.";
                        goto end_login;
                    }

                    // Login Berhasil
                    session_regenerate_id(true); 
                    
                    // **KUNCI PERBAIKAN DI SINI:** Nama di-set langsung ke Sesi.
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['nama'] = htmlspecialchars($user['nama']); 
                    $_SESSION['role'] = $user['role'];
                    
                    redirectToDashboard($user['role']);

                } else {
                    $error_message = "NIP atau Password salah. Mohon periksa kembali.";
                }
            } catch (PDOException $e) {
                error_log("Login DB Error: " . $e->getMessage());
                $error_message = "Terjadi kesalahan server saat mencoba login. Silakan coba lagi nanti.";
            }
        }
    }
}

end_login:
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Guru dan Staf</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* CSS tetap sama */
        :root {
            --primary-color: #28A745; /* Hijau Standar (Bootstrap Success) */
            --primary-dark: #218838;  /* Hijau Lebih Gelap untuk Hover */
            --bg-page: #f8f9fa;       /* Latar Belakang Putih Sangat Terang */
            --text-dark: #343a40;     /* Teks Hitam Keabu-abuan */
            --error-color: #DC3545;   /* Merah untuk Error (Bootstrap Danger) */
            --border-radius-lg: 8px; 
            --box-shadow-login: 0 4px 15px rgba(0, 0, 0, 0.08); /* Bayangan Lebih Lembut */
            --input-border: #ced4da;  /* Warna border input abu-abu */
            --transition-speed: 0.3s;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-dark);
        }

        .login-container {
            background-color: #ffffff;
            padding: 30px; 
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-login);
            width: 100%;
            max-width: 380px; 
            text-align: center;
            animation: fadeInScale 0.5s ease-out;
            border-top: 5px solid var(--primary-color); /* Garis hijau di atas */
        }
        
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }


        .header h1 {
            font-size: 1.6em; 
            color: var(--primary-dark);
            margin-bottom: 5px;
            font-weight: 600;
        }

        .header p {
            color: #6c757d; /* Abu-abu sedikit lebih gelap */
            margin-bottom: 25px; 
            font-size: 0.9em;
        }

        .logo {
            font-size: 3em;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px; 
            font-weight: 500; 
            font-size: 0.9em;
            color: var(--text-dark);
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            padding: 0 10px; 
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }

        .input-group:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25); /* Shadow hijau lembut */
        }

        .input-group i {
            color: #6c757d; /* Warna ikon abu-abu */
            margin-right: 10px;
            font-size: 1em;
        }
        
        .input-group:focus-within i {
            color: var(--primary-color); 
        }

        .input-group input {
            border: none;
            padding: 12px 0;
            width: 100%;
            font-size: 0.95em;
            outline: none;
            background-color: transparent;
            color: var(--text-dark);
        }
        
        .password-toggle-icon {
            color: #6c757d;
            cursor: pointer;
            padding: 12px 0;
            transition: color var(--transition-speed);
        }
        
        .password-toggle-icon:hover {
            color: var(--primary-dark);
        }
        
        button[type="submit"] {
            width: 100%;
            padding: 12px; 
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color var(--transition-speed), transform 0.2s, box-shadow var(--transition-speed);
            margin-top: 10px;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3); /* Shadow Hijau */
        }

        button[type="submit"]:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px); 
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }
        
        button[type="submit"]:active {
            transform: translateY(0);
        }

        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-weight: 500;
            background-color: #F8D7DA; /* Latar merah error lembut */
            color: var(--error-color);
            border: 1px solid var(--error-color); 
            border-left: 4px solid var(--error-color);
            text-align: left;
            opacity: 0;
            animation: fadeIn 0.5s forwards;
        }
        
        @keyframes fadeIn {
            to { opacity: 1; }
        }
        
        .login-container > p { 
            margin-top: 20px !important; 
            font-size: 0.8em !important;
            color: #7f8c8d;
        }
        
        .login-container > p a {
            color: var(--primary-dark) !important; 
            transition: color var(--transition-speed);
            text-decoration: none; 
            font-weight: 600;
        }
        
        .login-container > p a:hover {
            color: var(--primary-color) !important;
            text-decoration: underline;
        }


        /* Mobile adjustments */
        @media (max-width: 500px) {
            .login-container {
                margin: 15px;
                padding: 25px;
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo"><i class="fas fa-chalkboard-teacher"></i></div>
        <div class="header">
            <h1>LOGIN GURU & STAF</h1>
            <p>Masukkan NIP dan Password Anda untuk akses dashboard</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="nip">NIP / Kode Unik</label>
                <div class="input-group">
                    <i class="fas fa-user-circle"></i>
                    <input type="text" id="nip" name="nip" required autofocus value="<?= htmlspecialchars($_POST['nip'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-key"></i>
                    <input type="password" id="password" name="password" required>
                    <i class="password-toggle-icon fas fa-eye" onclick="togglePasswordVisibility('password')"></i>
                </div>
            </div>

            <button type="submit">LOGIN</button>
        </form>
        
        <p>
            Khusus Admin? <a href="admin_login.php">Login di sini</a>.
        </p>

    </div>

    <script>
        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling; 
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>