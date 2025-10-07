<?php
// proses_jadwal.php

session_start();
include 'koneksi.php'; // Panggil file koneksi

// Pastikan form disubmit dengan metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Ambil data dari form dan amankan
    $nama_kelas = mysqli_real_escape_string($koneksi, $_POST['kelas']);
    $nama_guru = mysqli_real_escape_string($koneksi, $_POST['guru']); // Ambil data guru dari form
    $hari = mysqli_real_escape_string($koneksi, $_POST['hari']);
    $mata_pelajaran = mysqli_real_escape_string($koneksi, $_POST['mata_pelajaran']);
    $jam_mulai = mysqli_real_escape_string($koneksi, $_POST['jam_mulai']);
    $jam_selesai = mysqli_real_escape_string($koneksi, $_POST['jam_selesai']);

    // 2. Cari ID kelas berdasarkan nama kelas yang diinput
    $query_kelas = "SELECT id FROM kelas WHERE nama_kelas = '$nama_kelas'";
    $hasil_kelas = mysqli_query($koneksi, $query_kelas);
    $kelas_id = null;

    if (mysqli_num_rows($hasil_kelas) > 0) {
        // Jika kelas sudah ada, ambil ID-nya
        $data_kelas = mysqli_fetch_assoc($hasil_kelas);
        $kelas_id = $data_kelas['id'];
    } else {
        // Jika kelas belum ada, buat kelas baru dan ambil ID-nya
        $insert_kelas = "INSERT INTO kelas (nama_kelas) VALUES ('$nama_kelas')";
        if (mysqli_query($koneksi, $insert_kelas)) {
            $kelas_id = mysqli_insert_id($koneksi); // Ambil ID terakhir yang di-insert
        }
    }

    // 3. Jika ID kelas berhasil didapatkan, masukkan data jadwal
    if ($kelas_id) {
        // Query INSERT disesuaikan dengan menambahkan kolom 'nama_guru'
        $query_insert_jadwal = "INSERT INTO jadwal (kelas_id, mata_pelajaran, nama_guru, hari, jam_mulai, jam_selesai) 
                                VALUES ('$kelas_id', '$mata_pelajaran', '$nama_guru', '$hari', '$jam_mulai', '$jam_selesai')";

        if (mysqli_query($koneksi, $query_insert_jadwal)) {
            // Jika berhasil, set pesan sukses
            $_SESSION['message'] = "Jadwal berhasil ditambahkan!";
        } else {
            // Jika gagal, set pesan error
            $_SESSION['error'] = "Error: " . mysqli_error($koneksi);
        }
    } else {
        $_SESSION['error'] = "Gagal memproses data kelas.";
    }

    // 4. Redirect kembali ke halaman utama
    header("Location: kelola_jadwal.php");
    exit();
}
?>