<?php
// ====================================================================
// db_config.php: KONFIGURASI DATABASE DAN FUNGSI KONEKSI
// Pastikan konfigurasi ini sesuai dengan lingkungan server database Anda.
// ====================================================================

// Konfigurasi Database - PASTIKAN SESUAI DENGAN LINGKUNGAN ANDA
$host = 'localhost';
$user = 'root'; // Ganti jika user database Anda berbeda
$password = ''; // Ganti jika user database Anda memiliki password
$dbname = 'smkjt1'; // Nama database yang telah dimodifikasi (smkjt1)

/**
 * Membuat koneksi PDO ke database.
 * @return PDO|null Objek PDO atau null jika gagal.
 */
function connectDB($host, $user, $password, $dbname) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Non-persistent connection is usually safer/preferred in web apps
            PDO::ATTR_PERSISTENT => false, 
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Pada produksi, ini harus dicatat ke file log, bukan ditampilkan ke pengguna.
        // echo "Koneksi database gagal: " . $e->getMessage();
        return null;
    }
}

// Inisialisasi koneksi
$pdo = connectDB($host, $user, $password, $dbname);

?>
