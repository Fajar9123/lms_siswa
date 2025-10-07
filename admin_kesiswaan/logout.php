<?php
// ====================================================================
// logout.php: MENGHANCURKAN SESI DAN REDIRECT KE LOGIN
// ====================================================================

session_start();

// Hapus semua variabel sesi
$_SESSION = array();

// Hancurkan sesi
session_destroy();

// Redirect ke halaman login
header('Location: login.php?msg=Anda berhasil keluar.&status=success');
exit;
?>
