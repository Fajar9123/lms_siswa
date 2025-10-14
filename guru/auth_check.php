<?php
// auth_check.php
// Digunakan untuk memverifikasi apakah user sudah login dan memiliki role yang benar.

session_start();

// Halaman ini hanya boleh diakses oleh role Wali_Kelas
$allowed_role = 'Wali_Kelas';

// 1. Cek apakah sesi ada
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_SESSION['nama'])) {
    // Sesi tidak ada, redirect ke login
    session_unset(); 
    session_destroy();
    header('Location: login.php?error=session_expired');
    exit;
}

// 2. Cek apakah role sesuai
if ($_SESSION['role'] !== $allowed_role) {
    // Role tidak sesuai (misalnya Guru Mapel mencoba masuk ke dashboard Wali Kelas)
    session_unset(); 
    session_destroy();
    header('Location: login.php?error=forbidden_access');
    exit;
}

// Jika lolos semua cek, sesi dianggap valid dan user siap digunakan
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['nama']; // Nama user yang tidak akan bertukar
$current_user_role = $_SESSION['role'];

// Opsional: Perluas data sesi dari database jika butuh data spesifik kelas
/*
try {
    require_once '../koneksi/db_config.php';
    $stmt = $pdo->prepare("SELECT id_kelas, kelas_name FROM wali_kelas_detail WHERE user_id = ?");
    $stmt->execute([$current_user_id]);
    $wali_kelas_data = $stmt->fetch();
    
    if ($wali_kelas_data) {
        $_SESSION['id_kelas_ampu'] = $wali_kelas_data['id_kelas'];
        $_SESSION['kelas_name'] = $wali_kelas_data['kelas_name'];
    }
} catch (PDOException $e) {
    // Handle error DB jika diperlukan
}
*/

?>