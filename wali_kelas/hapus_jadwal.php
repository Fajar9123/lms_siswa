<?php
// hapus_jadwal.php

// Memulai session untuk menggunakan variabel session
session_start();
// Memanggil file koneksi database
include 'koneksi.php';

//=====================================================
// 🔑 PERIKSA HAK AKSES (Opsional tapi disarankan)
//=====================================================
// Anda bisa menambahkan pengecekan apakah pengguna adalah admin/wali kelas
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
//     $_SESSION['error'] = "Anda tidak memiliki hak akses untuk menghapus data.";
//     header("Location: kelola_jadwal.php");
//     exit();
// }

//=====================================================
// 🚀 PROSES HAPUS DATA
//=====================================================
// 1. Cek apakah ada parameter 'id' yang dikirim melalui URL (metode GET)
if (isset($_GET['id'])) {
    
    // 2. Amankan ID untuk mencegah SQL Injection
    $id_jadwal = mysqli_real_escape_string($koneksi, $_GET['id']);

    // 3. Buat query SQL untuk menghapus data
    $query_hapus = "DELETE FROM jadwal WHERE id = '$id_jadwal'";

    // 4. Jalankan query
    if (mysqli_query($koneksi, $query_hapus)) {
        // Jika query berhasil dijalankan, set pesan sukses
        $_SESSION['message'] = "Jadwal berhasil dihapus.";
    } else {
        // Jika query gagal, set pesan error
        $_SESSION['error'] = "Gagal menghapus jadwal: " . mysqli_error($koneksi);
    }
} else {
    // Jika tidak ada parameter 'id' di URL, set pesan error
    $_SESSION['error'] = "Permintaan tidak valid. ID jadwal tidak ditemukan.";
}

//=====================================================
// ↩️ KEMBALIKAN PENGGUNA
//=====================================================
// Alihkan (redirect) pengguna kembali ke halaman utama
header("Location: kelola_jadwal.php");
exit(); // Pastikan untuk menghentikan eksekusi script setelah redirect
?>