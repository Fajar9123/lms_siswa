<?php
// ====================================================================
// update_password_admin.php: SKRIP UNTUK MEMPROSES GANTI PASSWORD
// ====================================================================

session_start();

// Redirect jika sudah login sebagai Admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    header('Location: admin.php');
    exit;
}

// Cek apakah request datang dari method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

// 1. Ambil data dari formulir
$nip = trim($_POST['nip'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 2. Validasi Input
if (empty($nip) || empty($new_password) || empty($confirm_password)) {
    $msg = urlencode("Semua kolom harus diisi!");
    header("Location: forgot_password.php?status=error&msg=$msg");
    exit;
}

if ($new_password !== $confirm_password) {
    $msg = urlencode("Password baru dan konfirmasi password tidak cocok.");
    header("Location: forgot_password.php?status=error&msg=$msg");
    exit;
}

if (strlen($new_password) < 6) {
    $msg = urlencode("Password minimal harus 6 karakter.");
    header("Location: forgot_password.php?status=error&msg=$msg");
    exit;
}

// ====================================================================
// 3. KONFIGURASI DATABASE (SESUAIKAN DENGAN DETAIL ANDA)
// ====================================================================
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "nama_database_anda"; // Ganti dengan nama database Anda

// Buat koneksi
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    // Tangani error koneksi database
    $msg = urlencode("Terjadi kesalahan koneksi database.");
    header("Location: forgot_password.php?status=error&msg=$msg");
    exit;
}

// 4. Cek keberadaan NIP Admin
// Pastikan nama tabel dan kolom sesuai dengan database Anda!
$sql_check_nip = "SELECT * FROM admin WHERE nip = ?"; // Ganti 'admin' dengan nama tabel admin Anda
$stmt = $conn->prepare($sql_check_nip);
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    $msg = urlencode("NIP Admin tidak ditemukan. Silakan periksa kembali.");
    header("Location: forgot_password.php?status=error&msg=$msg");
    exit;
}

$stmt->close();

// 5. Update Password
// Selalu gunakan hashing untuk menyimpan password (misalnya: password_hash)
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Pastikan nama tabel dan kolom sesuai dengan database Anda!
$sql_update = "UPDATE admin SET password = ? WHERE nip = ?";
$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param("ss", $hashed_password, $nip);

if ($stmt_update->execute()) {
    // Sukses: Redirect dengan pesan sukses
    $msg = urlencode("Password Admin berhasil diperbarui! Silakan masuk.");
    header("Location: forgot_password.php?status=success&msg=$msg");
} else {
    // Gagal: Redirect dengan pesan error
    $msg = urlencode("Gagal memperbarui password. Silakan coba lagi. Error: " . $stmt_update->error);
    header("Location: forgot_password.php?status=error&msg=$msg");
}

$stmt_update->close();
$conn->close();

exit;
?>