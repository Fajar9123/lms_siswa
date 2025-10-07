<?php
session_start();
require 'koneksi.php';

// 1. Hanya izinkan metode POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: user.php");
    exit();
}

// 2. Ambil dan bersihkan data dari form
$nama_lengkap = trim($_POST['nama_lengkap']);
$nis          = trim($_POST['nis']);
$kelas        = trim($_POST['kelas']);
$password     = $_POST['password'];

// 3. Validasi dasar: pastikan tidak ada yang kosong
if (empty($nama_lengkap) || empty($nis) || empty($kelas) || empty($password)) {
    $_SESSION['error'] = "Semua kolom wajib diisi.";
    header("Location: user.php");
    exit();
}

// --- LANGKAH KUNCI: Cek apakah NIS sudah ada ---
try {
    // 4. Siapkan query untuk mengecek keberadaan NIS
    $check_sql = "SELECT nis FROM akunsiswa WHERE nis = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $nis);
    $check_stmt->execute();
    $check_stmt->store_result(); // Simpan hasil untuk dicek jumlah barisnya

    // 5. Jika ditemukan (jumlah baris > 0), berarti NIS sudah terdaftar
    if ($check_stmt->num_rows > 0) {
        $_SESSION['error'] = "Akun sudah terdaftar. Gunakan Akun yang lain.";
        $check_stmt->close();
        $conn->close();
        header("Location: user.php");
        exit(); // Hentikan eksekusi skrip
    }

    // Tutup statement pengecekan
    $check_stmt->close();

    // --- JIKA NIS AMAN DAN BELUM TERDAFTAR, LANJUTKAN ---

    // 6. Hash password untuk keamanan
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 7. Siapkan query untuk memasukkan data baru
    $insert_sql = "INSERT INTO akunsiswa (nama_lengkap, nis, kelas, password) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ssss", $nama_lengkap, $nis, $kelas, $hashed_password);

    // 8. Eksekusi query insert
    if ($insert_stmt->execute()) {
        $_SESSION['message'] = "Akun siswa berhasil dibuat!";
    } else {
        // Ini sebagai cadangan jika masih ada error lain yang tidak terduga
        throw new Exception("Gagal menyimpan data ke database.");
    }

    // Tutup statement insert
    $insert_stmt->close();

} catch (Exception $e) {
    // 9. Tangkap semua jenis error (baik dari koneksi, query, dll)
    $_SESSION['error'] = "Terjadi kesalahan pada sistem. Silakan coba lagi nanti.";
    // Catat error sebenarnya untuk dilihat oleh developer (penting!)
    error_log("Error in buat_akun.php: " . $e->getMessage());
} finally {
    // 10. Pastikan koneksi selalu ditutup
    if (isset($conn)) {
        $conn->close();
    }
}

// 11. Alihkan pengguna kembali ke halaman user
header("Location: user.php");
exit();

?>