<?php
// ====================================================================
// update_password_admin.php: MEMPROSES LOGIKA PENGGANTIAN PASSWORD
// File ini menerima POST request dari forgot_password.php
// ====================================================================

session_start();
// Pastikan tidak ada sesi yang sedang berjalan (jika ada, hancurkan)
if (isset($_SESSION['role'])) {
    session_destroy();
}

// Sertakan konfigurasi database
require_once 'db_config.php';

// Fungsi untuk redirect dengan pesan status ke halaman form
function redirect_with_message($status, $msg) {
    header("Location: forgot_password.php?status=$status&msg=" . urlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip = trim($_POST['nip'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- 1. Validasi Input ---
    if (empty($nip) || empty($new_password) || empty($confirm_password)) {
        redirect_with_message('error', 'Semua field wajib diisi.');
    }

    if ($new_password !== $confirm_password) {
        redirect_with_message('error', 'Password baru dan konfirmasi password tidak cocok.');
    }

    if (strlen($new_password) < 6) {
        redirect_with_message('error', 'Password minimal harus 6 karakter.');
    }
    
    // Pastikan koneksi DB tersedia
    if (!isset($pdo) || $pdo === null) {
        redirect_with_message('error', 'Koneksi database gagal. Silakan coba lagi.');
    }

    try {
        // --- 2. Verifikasi NIP dan Role ---
        // Kita hanya mengizinkan Admin untuk mereset password melalui form ini
        $stmt = $pdo->prepare("SELECT role FROM users WHERE nip = ?");
        $stmt->execute([$nip]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            redirect_with_message('error', 'NIP tidak ditemukan.');
        }

        if ($user['role'] !== 'Admin') {
            redirect_with_message('error', 'NIP ini bukan milik Admin atau tidak diizinkan untuk direset.');
        }

        // --- 3. Hashing dan Update Password ---
        // Hashing password baru sebelum menyimpannya ke database
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update Password di Database
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE nip = ? AND role = 'Admin'");
        $update_stmt->execute([$hashed_password, $nip]);

        if ($update_stmt->rowCount() > 0) {
            // Berhasil
            redirect_with_message('success', 'Password Admin berhasil diubah! Silakan login dengan password baru.');
        } else {
            // Gagal Update
            redirect_with_message('error', 'Gagal memperbarui password. NIP tidak valid atau tidak ada perubahan yang terdeteksi.');
        }

    } catch (PDOException $e) {
        // Log error database
        error_log("Password Reset Error: " . $e->getMessage());
        redirect_with_message('error', 'Terjadi kesalahan sistem saat pembaruan password.');
    }
} else {
    // Jika diakses langsung tanpa metode POST
    redirect_with_message('error', 'Metode akses tidak valid.');
}
?>
