<?php
session_start();

// Konfigurasi koneksi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smkjt1";

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Tangani permintaan POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nis_input = $conn->real_escape_string(trim($_POST['nis']));
    $password_input = $_POST['password'];

    // Gunakan prepared statement untuk mencari NIS
    $sql = "SELECT nis, password FROM akunsiswa WHERE nis = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nis_input);
    $stmt->execute();
    $stmt->store_result();
    
    // Jika NIS ditemukan
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($nis_db, $hashed_password);
        $stmt->fetch();

        // Verifikasi password yang dimasukkan dengan password yang di-hash
        if (password_verify($password_input, $hashed_password)) {
            // Password cocok, buat sesi login
            $_SESSION['nis'] = $nis_db;
            $_SESSION['role'] = 'siswa';

            // Redirect ke halaman dashboard siswa
            header("Location: dashboard_siswa.php");
            exit();
        } else {
            // Password tidak cocok
            header("Location: login.html?error=" . urlencode("NIS atau password salah."));
            exit();
        }
    } else {
        // NIS tidak ditemukan
        header("Location: login.html?error=" . urlencode("NIS atau password salah."));
        exit();
    }
    
    $stmt->close();
}

$conn->close();
?>