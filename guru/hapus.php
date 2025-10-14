<?php
// hapus.php
date_default_timezone_set('Asia/Jakarta');

// ====================================================================
// KONFIGURASI DAN KONEKSI (Sesuaikan dengan setting Anda)
// ====================================================================
$host = 'localhost';
$user = 'root';
$password = ''; 
$dbname = 'smkjt1'; 
const DB_TABLE = 'akunsiswa'; 
const REDIRECT_URL = 'index.php'; // Halaman utama tempat daftar siswa berada

function connectDB($host, $user, $password, $dbname) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}
$pdo = connectDB($host, $user, $password, $dbname);

// ====================================================================
// LOGIKA HAPUS (DELETE)
// ====================================================================

$message_type = 'error';
$message = '❌ Akses tidak sah.';

if ($pdo && isset($_GET['nis'])) {
    $nis_to_delete = trim($_GET['nis']);

    // Validasi dasar NIS
    if (!empty($nis_to_delete) && preg_match('/^[0-9]+$/', $nis_to_delete)) {
        try {
            // Menggunakan Prepared Statement untuk keamanan
            $stmt = $pdo->prepare("DELETE FROM " . DB_TABLE . " WHERE nis = ?");
            $stmt->execute([$nis_to_delete]);

            if ($stmt->rowCount() > 0) {
                $message_type = 'success';
                $message = "🗑️ Akun siswa dengan NIS **$nis_to_delete** berhasil dihapus!";
            } else {
                $message_type = 'error';
                $message = "❌ Gagal menghapus. Akun siswa dengan NIS **$nis_to_delete** tidak ditemukan.";
            }
        } catch (PDOException $e) {
            $message_type = 'error';
            $message = "❌ Terjadi kesalahan database saat menghapus.";
        }
    } else {
        $message = "❌ NIS yang dimasukkan tidak valid.";
    }
} else if (!$pdo) {
    $message = "❌ Koneksi database gagal.";
}

// Redirect kembali ke halaman utama (index.php) dengan membawa pesan status
$redirect_url = REDIRECT_URL . "?msg_type=" . $message_type . "&msg=" . urlencode($message);
header("Location: " . $redirect_url);
exit();

?>