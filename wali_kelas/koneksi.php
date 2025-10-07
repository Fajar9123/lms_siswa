<?php
// koneksi.php

$host = "localhost";    // Nama host database Anda
$user = "root";         // Username database Anda
$pass = "";             // Password database Anda (kosongkan jika tidak ada)
$db   = "smkjt1";   // Nama database yang Anda buat

$koneksi = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi
if (!$koneksi) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}
?>