<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Siswa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary-orange: #FF7F32;
            --secondary-orange: #FF9B50;
            --background-start: #FF9B50;
            --background-end: #FF7F32;
            --card-bg-color: #FFFFFF;
            --text-dark: #212121;
            --text-gray: #757575;
            --border-color: #EEEEEE;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.07);
            --border-radius: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--background-start), var(--background-end));
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-dark);
            padding: 1.5rem;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            background-color: var(--card-bg-color);
            border-radius: var(--border-radius);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .login-header {
            margin-bottom: 2rem;
        }

        .login-header .logo-icon {
            font-size: 3.5rem;
            color: var(--primary-orange);
            margin-bottom: 0.75rem;
        }

        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .login-header p {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .form-group {
            text-align: left;
            margin-bottom: 1.5rem;
            position: relative; /* Menambahkan posisi relatif untuk penempatan icon */
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .form-group input {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: white;
            padding-right: 3rem; /* Menyesuaikan padding agar tidak menutupi icon */
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 4px rgba(255, 127, 50, 0.2);
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 1.25rem;
            transform: translateY(12%);
            cursor: pointer;
            color: var(--text-gray);
            font-size: 1.1rem;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--text-dark);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--primary-orange);
            color: white;
        }

        .btn:hover {
            background: #e06d2d;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 127, 50, 0.3);
        }

        .alert-message {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            font-weight: 500;
            text-align: left;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background-color: #E8F5E9;
            color: #388E3C;
            border: 1px solid #C8E6C9;
        }

        .alert-error {
            background-color: #FFCDD2;
            color: #D32F2F;
            border: 1px solid #EF9A9A;
        }

        .forgot-password {
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        .forgot-password a {
            color: var(--primary-orange);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .forgot-password a:hover {
            color: var(--text-dark);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-user-circle logo-icon"></i>
            <h1>Selamat Datang, Siswa!</h1>
            <p>Silakan masuk untuk melihat data absensi dan jadwal Anda.</p>
        </div>

        <?php
            // Menampilkan pesan error jika ada
            if (isset($_SESSION['login_error'])) {
                echo "<p class='alert-message alert-error'>{$_SESSION['login_error']}</p>";
                unset($_SESSION['login_error']);
            }
        ?>

        <form action="proses_login.php" method="POST">
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan Nama Lengkap Anda" required>
            </div>
            <div class="form-group">
                <label for="nis">Nomor Induk Siswa (NIS)</label>
                <input type="text" id="nis" name="nis" placeholder="Masukkan NIS Anda" required>
            </div>
            <div class="form-group">
                <label for="kelas">Kelas</label>
                <input type="text" id="kelas" name="kelas" placeholder="Contoh: IX-A" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password Anda" required>
                <i class="far fa-eye password-toggle" id="togglePassword"></i>
            </div>
            <button type="submit" class="btn">Masuk</button>
        </form>
    </div>

    <script>
        // Kode JavaScript untuk fungsi mata
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function (e) {
            // Mengalihkan tipe input antara 'password' dan 'text'
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Mengubah ikon mata
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>