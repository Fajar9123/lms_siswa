<?php
// index.php

// Tentukan halaman tujuan
$halaman_tujuan = 'admin_login.php';

// Melakukan pengalihan (redirection) ke halaman tujuan
// Perintah ini memberitahu browser untuk langsung pergi ke file guru.php
header('Location: ' . $halaman_tujuan);
exit(); // Penting untuk menghentikan eksekusi script setelah pengalihan
?>