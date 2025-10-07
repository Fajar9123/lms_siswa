<?php
// ====================================================================
// generate_hash.php: Skrip Utilitas untuk Membuat Hash Password Baru
// ====================================================================

// 1. GANTI INI DENGAN PASSWORD BARU YANG ANDA INGINKAN
$new_password_plain = "MyNewAdminPassword123"; 

// Hashing menggunakan algoritma BCRYPT, sesuai dengan logika di admin.php
$hashed_password = password_hash($new_password_plain, PASSWORD_BCRYPT);

echo "===================================================================\n";
echo "Langkah 1: GENERATE HASH PASSWORD BARU\n";
echo "===================================================================\n";
echo "Password Plain (BARU): " . $new_password_plain . "\n";
echo "NIP Admin Baru: 00000001\n";
echo "-------------------------------------------------------------------\n";
echo "SALIN LENGKAP HASH DI BAWAH INI (termasuk \$2y\$... sampai akhir):\n";
echo $hashed_password . "\n";
echo "-------------------------------------------------------------------\n";
echo "Salin hash di atas dan paste ke file reset_admin_command.sql di Langkah 2.\n";
echo "===================================================================\n";
?>
