<?php
// ====================================================================
// forgot_password.php: FORM UNTUK MENGGANTI PASSWORD
// ====================================================================

session_start();
// Jika sudah login, redirect kembali ke dashboard Admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    header('Location: admin.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #0d6efd; 
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
        .container {
            background-color: var(--secondary-color);
            padding: 40px;
            border-radius: var(--border-radius-md);
            box-shadow: var(--box-shadow-login);
            width: 100%;
            max-width: 450px;
        }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 {
            font-size: 1.6em;
            font-weight: 700;
            color: var(--primary-dark);
        }
        .header p { font-size: 0.9em; color: #7f8c8d; margin-top: 5px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9em; color: var(--text-dark); }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: var(--border-radius-md);
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            transition: border-color 0.3s;
        }
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
            transition: background-color 0.3s, transform 0.2s;
            margin-top: 10px;
        }
        button[type="submit"]:hover { background-color: var(--primary-dark); transform: translateY(-1px); }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius-md);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert.error { background-color: #f9e4e2; color: var(--error-color); border-left: 5px solid var(--error-color); }
        .alert.success { background-color: #e6f7ef; color: var(--success-color); border-left: 5px solid var(--success-color); }
        .back-link { display: block; text-align: center; margin-top: 20px; font-size: 0.9em; }
        .back-link a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-key"></i> Ganti Password Admin</h1>
            <p>Masukkan NIP dan Password baru Anda.</p>
        </div>
        
        <?php 
        $message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
        $message_type = isset($_GET['status']) ? $_GET['status'] : '';
        
        if (!empty($message)): ?>
            <div class="alert <?php echo $message_type; ?>">
                <?php 
                    if ($message_type === 'error') {
                        echo '<i class="fas fa-times-circle"></i>';
                    } elseif ($message_type === 'success') {
                        echo '<i class="fas fa-check-circle"></i>';
                    }
                ?>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="update_password_admin.php">
            <div class="form-group">
                <label for="nip">NIP Admin</label>
                <input type="text" id="nip" name="nip" placeholder="Masukkan NIP Admin" required>
            </div>
            <div class="form-group">
                <label for="new_password">Password Baru</label>
                <input type="password" id="new_password" name="new_password" placeholder="Masukkan Password Baru" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password Baru</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi Password Baru" required minlength="6">
            </div>
            
            <button type="submit">
                <i class="fas fa-save"></i> GANTI PASSWORD
            </button>
        </form>

        <div class="back-link">
            <a href="admin_login.php">
                <i class="fas fa-arrow-left"></i> Kembali ke Halaman Login
            </a>
        </div>
    </div>
</body>
</html>
