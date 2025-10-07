<?php
// --- Konfigurasi Database ---
$servername = "localhost"; // Nama server database, biasanya "localhost"
$username = "root";        // Username database, defaultnya "root" untuk XAMPP
$password = "";            // Password database, defaultnya kosong untuk XAMPP
$dbname = "smkjt1";    // Nama database yang Anda buat sebelumnya

// --- Membuat Koneksi ---
$conn = new mysqli($servername, $username, $password, $dbname);

// --- Cek Koneksi ---
if ($conn->connect_error) {
    // Jika koneksi gagal, hentikan skrip dan tampilkan pesan error
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

// Jika Anda ingin melakukan tes apakah koneksi berhasil,
// hapus tanda komentar pada baris di bawah ini.
// echo "Koneksi ke database berhasil!"; 
?>