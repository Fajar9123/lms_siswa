<?php
session_start();
// Sertakan file koneksi database Anda
include 'koneksi/koneksi.php';

// Ambil data dari formulir
$nama_lengkap = $_POST['nama_lengkap'];
$nis = $_POST['nis'];
$kelas = $_POST['kelas'];
$password = $_POST['password'];

// Lakukan sanitasi data untuk keamanan (meskipun prepared statement lebih baik)
$nama_lengkap_safe = mysqli_real_escape_string($conn, $nama_lengkap);
$nis_safe = mysqli_real_escape_string($conn, $nis);
$kelas_safe = mysqli_real_escape_string($conn, $kelas);

// Cari siswa di database berdasarkan NIS, nama, dan kelas
// PERHATIAN: Cara ini masih rentan terhadap SQL Injection. Lihat Versi 2 di bawah.
$sql = "SELECT * FROM akunsiswa WHERE nis = '$nis_safe' AND nama_lengkap = '$nama_lengkap_safe' AND kelas = '$kelas_safe'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    
    // Verifikasi password
    if (password_verify($password, $row['password'])) {
        // Password cocok, login berhasil!
        
        // =================================================================
        // ✅ INI BAGIAN TERPENTING UNTUK MENCEGAH TABRAKAN SESI
        // Buat ID sesi yang baru dan segar untuk pengguna ini
        session_regenerate_id(true);
        // =================================================================

        // Simpan data siswa ke dalam sesi unik mereka
        $_SESSION['nis'] = $row['nis'];
        $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
        $_SESSION['kelas'] = $row['kelas'];
        $_SESSION['user_logged_in'] = true;

        // (Opsional) Anda bisa hapus pesan sukses jika tidak digunakan di halaman login
        $_SESSION['login_success'] = "Login Berhasil! Selamat datang, " . $row['nama_lengkap'] . ".";

        // Redirect ke halaman dasbor siswa
        header("Location: user/user.php");
        exit();
    } else {
        // Password tidak cocok
        $_SESSION['login_error'] = "Password yang Anda masukkan salah.";
        header("Location: login.php");
        exit();
    }
} else {
    // Siswa tidak ditemukan
    $_SESSION['login_error'] = "Data tidak ditemukan. Pastikan Nama Lengkap, NIS, dan Kelas Anda benar.";
    header("Location: login.php");
    exit();
}
?>
