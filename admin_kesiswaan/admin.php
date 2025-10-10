<?php
// ====================================================================
// admin.php: MANAJEMEN PENGGUNA (DUPLICATION CHECK IMPROVED + SEARCH FUNCTIONALITY)
// TELAH DISESUAIKAN UNTUK MENGGUNAKAN TABEL 'admin'
// ====================================================================

session_start();

// Asumsi: File ini berada di luar folder 'koneksi'.
// Pastikan file db_config.php ada dan berisi koneksi PDO ($pdo)
require_once '../koneksi/db_config.php';

// --- PERIKSA AUTENTIKASI DAN ROLE ---
if (!isset($_SESSION['user_id'])) {
    header('Location: admin_login.php');
    exit;
}

if ($_SESSION['role'] !== 'Admin') {
    // Redirect ke halaman yang sesuai jika bukan Admin (misal dashboard biasa)
    // Jika tidak ada halaman lain, tetap redirect ke login atau tampilkan pesan akses ditolak
    header('Location: admin_login.php?access=denied');
    exit;
}
// Jika role adalah 'Admin', eksekusi kode di bawah akan dilanjutkan.

$logged_in_user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Ambil query pencarian dari URL, default kosong
$search_term = $_GET['search'] ?? '';
$search_term = trim(htmlspecialchars($search_term));


// --- DYNAMIC LOGGED-IN USER SETUP ---
$current_user_name = "Pengguna Tidak Dikenal";
$current_user_role = "Undefined Role";
$current_user_profile_pic = "../img/logosmkjt1.png"; // PATH GAMBAR DIUBAH

if ($pdo && $logged_in_user_id) {
    try {
        $stmt = $pdo->prepare("SELECT nama, role, profile_pic FROM admin WHERE user_id = ?");
        $stmt->execute([$logged_in_user_id]);
        $current_user_data = $stmt->fetch();

        if ($current_user_data) {
            $current_user_name = htmlspecialchars($current_user_data['nama']);
            $current_user_role = str_replace('_', ' ', htmlspecialchars($current_user_data['role']));
            if (!empty($current_user_data['profile_pic'])) {
                $current_user_profile_pic = htmlspecialchars($current_user_data['profile_pic']);
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching current user data: " . $e->getMessage());
    }
}
// --- END DYNAMIC LOGGED-IN USER SETUP ---


// --- FUNGSI AJAX HANDLERS ---

/**
 * Memeriksa duplikasi NIP atau Email.
 * @param PDO $pdo Koneksi PDO.
 * @param string $nip NIP yang diperiksa.
 * @param string $email Email yang diperiksa.
 * @param int $exclude_id ID user yang dikecualikan (untuk update).
 * @return string Pesan error atau string kosong jika tidak ada duplikasi.
 */
function checkDuplicate($pdo, $nip, $email, $exclude_id = 0)
{
    $error_msg = '';

    // Cek NIP duplikat di tabel 'admin'
    $stmt_nip = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE nip = ? AND user_id != ?");
    $stmt_nip->execute([$nip, $exclude_id]);
    $nip_count = $stmt_nip->fetchColumn();

    // Cek Email duplikat di tabel 'admin'
    $stmt_email = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = ? AND user_id != ?");
    $stmt_email->execute([$email, $exclude_id]);
    $email_count = $stmt_email->fetchColumn();

    if ($nip_count > 0 && $email_count > 0) {
        $error_msg = "Gagal: **NIP** dan **Email** sudah digunakan oleh pengguna lain!";
    } elseif ($nip_count > 0) {
        $error_msg = "Gagal: **NIP** sudah terdaftar dan digunakan oleh pengguna lain!";
    } elseif ($email_count > 0) {
        $error_msg = "Gagal: **Email** sudah terdaftar dan digunakan oleh pengguna lain!";
    }

    return $error_msg;
}

// Proses Update Data dari Modal (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user_modal') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Koneksi database gagal.'];

    if ($pdo) {
        $user_id = filter_var($_POST['edit_user_id'] ?? null, FILTER_VALIDATE_INT);
        $nama    = trim($_POST['edit_nama'] ?? '');
        $nip     = trim($_POST['edit_nip'] ?? '');
        $email   = trim($_POST['edit_email'] ?? '');
        $role    = $_POST['edit_role'] ?? '';
        $new_password = $_POST['edit_password'] ?? '';

        $allowed_roles = ['Admin', 'Guru_Mapel', 'Wali_Kelas', 'Kepala_Jurusan', 'Guru_BK'];
        if (!in_array($role, $allowed_roles)) {
            $response['message'] = "Role pengguna tidak valid!";
            echo json_encode($response);
            exit;
        }

        if (!$user_id || empty($nama) || empty($nip) || empty($email) || empty($role)) {
            $response['message'] = "Data tidak valid atau kolom wajib diisi!";
        } else {
            // --- Cek Duplikasi Lebih Spesifik ---
            $duplicate_error = checkDuplicate($pdo, $nip, $email, $user_id);
            if (!empty($duplicate_error)) {
                $response['message'] = $duplicate_error;
                echo json_encode($response);
                exit;
            }
            // --- END Cek Duplikasi ---
            
            // Update data di tabel 'admin'
            $sql = "UPDATE admin SET nama = :nama, nip = :nip, email = :email, role = :role";
            $params = [
                ':nama' => $nama,
                ':nip' => $nip,
                ':email' => $email,
                ':role' => $role,
                ':id' => $user_id
            ];

            if (!empty($new_password)) {
                if (strlen($new_password) < 8) {
                    $response['message'] = "Password baru minimal 8 karakter!";
                    echo json_encode($response);
                    exit;
                }
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $sql .= ", password = :password_hash";
                $params[':password_hash'] = $hashed_password;
            }
            
            $sql .= " WHERE user_id = :id";
            
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $response['success'] = true;
                $response['message'] = "Data pengguna **" . htmlspecialchars($nama) . "** berhasil diperbarui!" .
                                         (!empty($new_password) ? " Password juga telah diubah." : "");
                $response['data'] = [
                    'user_id' => $user_id,
                    'nama' => $nama,
                    'nip' => $nip,
                    'email' => $email,
                    'role' => $role,
                    'role_display' => str_replace('_', ' ', $role),
                ];
                
            } catch (PDOException $e) {
                $response['message'] = "DB Error: " . $e->getMessage();
            }
        }
    }

    echo json_encode($response);
    exit;
}

// Proses Hapus Data (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Koneksi database gagal.'];

    if ($pdo) {
        $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

        if ($user_id == $logged_in_user_id) {
            $response['message'] = "Anda tidak bisa menghapus akun yang sedang Anda gunakan!";
            echo json_encode($response);
            exit;
        }

        if (!$user_id) {
            $response['message'] = "ID Pengguna tidak valid!";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Hapus detail terkait (Guru Mapel & Wali Kelas) - Asumsi tabel detail tetap sama
                $pdo->prepare("DELETE FROM guru_mapel_detail WHERE user_id = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM wali_kelas_assignment WHERE user_id = ?")->execute([$user_id]);
                
                // Hapus pengguna utama dari tabel 'admin'
                $sql = "DELETE FROM admin WHERE user_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = "Pengguna dengan ID **$user_id** berhasil dihapus!";
                    $response['user_id'] = $user_id;
                    $pdo->commit();
                } else {
                    $response['message'] = "Gagal menghapus: Pengguna tidak ditemukan.";
                    $pdo->rollBack();
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $msg = "Gagal menghapus data. DB Error: " . $e->getMessage();
                $response['message'] = $msg;
            }
        }
    }

    echo json_encode($response);
    exit;
}

// Proses Ambil Data User untuk Modal (AJAX GET)
if (isset($_GET['action']) && $_GET['action'] === 'get_user_data' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'data' => null, 'message' => 'Koneksi database gagal.'];
    $user_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if ($pdo && $user_id) {
        try {
            // Ambil data dari tabel 'admin'
            $stmt = $pdo->prepare("SELECT user_id, nama, nip, email, role FROM admin WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $response['success'] = true;
                // Sanitasi output untuk keamanan
                foreach ($user as $key => $value) {
                    if (is_string($value)) {
                        $user[$key] = htmlspecialchars($value);
                    }
                }
                $response['data'] = $user;
            } else {
                $response['message'] = "Pengguna tidak ditemukan.";
            }
        } catch (PDOException $e) {
            $response['message'] = "Gagal mengambil data: " . $e->getMessage();
        }
    }

    echo json_encode($response);
    exit;
}


// --- PROSES SUBMIT FORM BIASA (Tambah User) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    if (!$pdo) {
        $message = "Koneksi ke database gagal. Periksa konfigurasi DB Anda.";
        $message_type = 'error';
    } else {
        $nama             = trim($_POST['nama'] ?? '');
        $nip              = trim($_POST['nip'] ?? '');
        $password_plain   = $_POST['password'] ?? '';
        $email            = trim($_POST['email'] ?? '');
        $role             = $_POST['role'] ?? '';
        
        $allowed_roles = ['Admin', 'Guru_Mapel', 'Wali_Kelas', 'Kepala_Jurusan', 'Guru_BK'];
        if (!in_array($role, $allowed_roles)) {
            $message = "Role pengguna tidak valid!";
            $message_type = 'error';
            goto end_of_post_check;
        }

        if (empty($nama) || empty($nip) || empty($password_plain) || empty($email) || empty($role)) {
            $message = "Semua kolom wajib diisi!";
            $message_type = 'error';
        } else {
            if (strlen($password_plain) < 8) {
                $message = "Password minimal 8 karakter!";
                $message_type = 'error';
            } else {
                $hashed_password = password_hash($password_plain, PASSWORD_BCRYPT);
                
                // --- Cek Duplikasi Lebih Spesifik ---
                $duplicate_error = checkDuplicate($pdo, $nip, $email, 0); // ID 0 untuk memastikan tidak ada pengecualian
                if (!empty($duplicate_error)) {
                    $message = $duplicate_error;
                    $message_type = 'error';
                } else {
                // --- END Cek Duplikasi ---
                
                    // Insert data ke tabel 'admin'
                    $sql_user = "INSERT INTO admin (nama, nip, password, email, role) VALUES (:nama, :nip, :password, :email, :role)";
                    
                    try {
                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare($sql_user);
                        $stmt->execute([':nama' => $nama, ':nip' => $nip, ':password' => $hashed_password, ':email' => $email, ':role' => $role]);
                        
                        $last_id = $pdo->lastInsertId();
                        $message = "User baru berhasil ditambahkan! User ID: " . $last_id;
                        $message_type = 'success';
                        
                        // Logika detail role (Tabel detail tetap sama)
                        if ($role === 'Guru_Mapel') {
                            $mapel = trim($_POST['mapel'] ?? '');
                            $jam = filter_var($_POST['jam_pelajaran'] ?? 0, FILTER_VALIDATE_INT);
                            if (!empty($mapel) && $jam !== false && $jam > 0) {
                                // Asumsi tabel guru_mapel_detail sudah ada
                                $sql_detail = "INSERT INTO guru_mapel_detail (user_id, mata_pelajaran, jam_pelajaran) VALUES (?, ?, ?)";
                                $pdo->prepare($sql_detail)->execute([$last_id, $mapel, $jam]);
                                $message .= " dan detail Guru Mapel berhasil ditambahkan.";
                            } else {
                                $message .= ". (Peringatan: Detail Guru Mapel tidak lengkap/invalid)";
                            }
                        } elseif ($role === 'Wali_Kelas') {
                            $kelas = trim($_POST['kelas_dipegang'] ?? '');
                            $tahun = trim($_POST['tahun_ajaran'] ?? '') ?: date('Y') . '/' . (date('Y') + 1);
                            if (!empty($kelas)) {
                                // Asumsi tabel wali_kelas_assignment sudah ada
                                $sql_assign = "INSERT INTO wali_kelas_assignment (user_id, kelas_dipegang, tahun_ajaran) VALUES (?, ?, ?)";
                                $pdo->prepare($sql_assign)->execute([$last_id, $kelas, $tahun]);
                                $message .= " dan penugasan Wali Kelas berhasil ditambahkan.";
                            } else {
                                $message .= ". (Peringatan: Detail Wali Kelas tidak lengkap)";
                            }
                        } elseif ($role === 'Kepala_Jurusan') {
                            $jurusan = trim($_POST['jurusan_dipegang'] ?? '');
                            if (!empty($jurusan)) {
                                $message .= " Role Kepala Jurusan ditambahkan (Jurusan: " . htmlspecialchars($jurusan) . ").";
                            } else {
                                $message .= ". (Peringatan: Detail Kepala Jurusan **Jurusan yang Dipegang** belum diisi)";
                            }
                        } elseif ($role === 'Guru_BK') {
                            $message .= ". Role Guru BK berhasil ditambahkan.";
                        }
                        
                        $pdo->commit();
                        
                        // Redirect-After-Post (PRG Pattern)
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?status=' . $message_type . '&msg=' . urlencode($message));
                        exit;

                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        // Jika ada duplikasi yang gagal ditangkap checkDuplicate (misal: UNIQUE constraint DB)
                        $message = "Gagal menyimpan data: " . $e->getMessage();
                        $message_type = 'error';
                    }
                }
            }
        }
    }
}
// Label untuk lompatan if ($role tidak valid)
end_of_post_check:

// Ambil pesan dari Redirect-After-Post (jika ada)
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $message_type = $_GET['status'];
    $message = htmlspecialchars(urldecode($_GET['msg']));
}

/**
 * Mengambil daftar pengguna, dengan opsi pencarian.
 * @param PDO $pdo Koneksi PDO.
 * @param string $search_term Kata kunci pencarian (nama, nip, atau email).
 * @return array Daftar pengguna.
 */
function getUsers($pdo, $search_term = '')
{
    if (!$pdo) return [];
    
    try {
        // user_id > 0 untuk menghindari menampilkan data user ID 0 (jika ada)
        $sql = "SELECT user_id, nama, nip, email, role, created_at FROM admin WHERE user_id > 0";
        $params = [];
        
        // LOGIKA PENCARIAN
        if (!empty($search_term)) {
            $sql .= " AND (nama LIKE :search_nama OR nip LIKE :search_nip OR email LIKE :search_email)";
            $search_param = '%' . $search_term . '%';
            $params[':search_nama'] = $search_param;
            $params[':search_nip'] = $search_param;
            $params[':search_email'] = $search_param;
        }
        // END LOGIKA PENCARIAN

        $sql .= " ORDER BY user_id DESC";
        
        // Terapkan limit (ini bisa disesuaikan, tapi 50 cukup untuk tampilan awal)
        if (empty($search_term)) {
            $sql .= " LIMIT 50";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB Error fetching users: " . $e->getMessage());
        return [];
    }
}

// Ambil data pengguna untuk ditampilkan di tabel (menggunakan search term)
$users_data = getUsers($pdo, $search_term);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Dashboard Admin SMK JTI</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* ====================================================================
            MODIFIKASI CSS UNTUK TAMPILAN PUTIH HIJAU
            ==================================================================== */
        :root {
            --primary-color: #1a8917; /* Hijau Daun */
            --primary-dark: #146312;  /* Hijau Lebih Gelap */
            --secondary-color: #ffffff;
            --bg-page: #f7fcf7;       /* Latar Belakang Putih Sangat Muda */
            --bg-container: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --success-color: #27ae60;
            --error-color: #c0392b;
            --edit-color: #3498db;
            --delete-color: #e74c3c;
            --border-color: #e0e0e0; /* Border Abu-abu Muda */
            --sidebar-width: 280px;
            --box-shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.08);
            --border-radius-md: 8px;
            --border-radius-lg: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .page-content-wrapper {
            display: flex;
            flex-direction: column; /* Mengubah flex direction agar footer bisa di bawah */
            flex-grow: 1;
            width: 100%;
        }

        .main-content-and-sidebar {
             display: flex;
             flex-grow: 1;
        }

        /* --- Sidebar & Navigasi (DEKSTOP) --- */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--secondary-color);
            padding: 24px 0;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.15);
            position: fixed;
            height: 100%;
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
            overflow-y: auto;
            flex-shrink: 0; /* Mencegah sidebar menyusut */
        }
        
        .sidebar-header { text-align: center; padding: 10px 20px 20px; font-size: 1.5em; font-weight: 700; letter-spacing: 1px; border-bottom: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .user-profile { text-align: center; padding: 15px 20px; margin-bottom: 25px; }
        .user-profile img {
            width: 100px; /* Ukuran bisa disesuaikan */
            height: 100px; /* Ukuran bisa disesuaikan */
            border-radius: var(--border-radius-md); /* Menjadi kotak dengan sudut tumpul */
            border: 4px solid var(--secondary-color);
            object-fit: contain; /* atau 'cover', sesuaikan dengan logo */
            margin-bottom: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            background-color: #fff; /* Tambahkan background putih jika logo transparan */
        }
        .user-profile .name { font-size: 1.1em; font-weight: 600; }
        .user-profile .role { font-size: 0.8em; color: rgba(255, 255, 255, 0.8); }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: var(--secondary-color); text-decoration: none; font-size: 0.95em; transition: background-color 0.3s ease, padding-left 0.3s ease; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255, 255, 255, 0.15); border-left: 4px solid var(--secondary-color); font-weight: 600; }
        .sidebar-menu i { margin-right: 15px; font-size: 1.1em; width: 20px; text-align: center; }
        
        /* Tombol Toggle Menu & Overlay untuk Mobile */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1010;
            background-color: var(--primary-dark);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: var(--border-radius-md);
            cursor: pointer;
            font-size: 1.2em;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }
        
        /* Main Content dan Container (Desktop Base) */
        .main-content {
            flex-grow: 1;
            padding: 30px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - var(--sidebar-width));
        }
        
        .container {
            width: 100%;
            background: var(--bg-container);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-medium);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        h1 { font-size: 2em; font-weight: 700; display: flex; align-items: center; gap: 15px; border-bottom: 2px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem; }
        h1 i { color: var(--primary-color); }
        h2 { font-size: 1.5em; font-weight: 600; margin-top: 2rem; margin-bottom: 1.5rem; color: var(--primary-dark); }
        p { color: var(--text-light); margin-bottom: 2rem; line-height: 1.6; }

        /* Form Card */
        .form-card {
            padding: 30px;
            border-radius: var(--border-radius-lg);
            background-color: var(--bg-container);
            margin-bottom: 40px;
            box-shadow: var(--box-shadow-medium);
        }

        /* Form Layout menggunakan CSS Grid Responsif */
        form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }
        .full-width { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 500; font-size: 0.9em; color: var(--text-dark); }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: var(--border-radius-md);
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: #ffffff;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 137, 23, 0.2);
            background-color: #fafff5;
        }
        
        /* Password Toggle */
        .password-wrapper { position: relative; display: block; }
        .password-input-group { position: relative; display: flex; align-items: center; }
        .password-wrapper input { padding-right: 45px; width: 100%; }
        .password-toggle-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--text-light); cursor: pointer; transition: color 0.3s; z-index: 2; }
        .password-toggle-icon:hover { color: var(--primary-color); }
        
        button[type="submit"] {
            grid-column: 1 / -1;
            padding: 15px 20px;
            background-color: var(--primary-color);
            color: var(--secondary-color);
            border: none;
            border-radius: var(--border-radius-md);
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s, box-shadow 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 10px rgba(26, 137, 23, 0.3);
        }
        button[type="submit"]:hover { background-color: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(26, 137, 23, 0.4); }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: var(--border-radius-md); font-weight: 500; display: flex; align-items: center; gap: 10px; transition: opacity 0.5s ease; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert.success { background-color: #eaf7ed; color: #206b32; border-left: 5px solid var(--success-color); }
        .alert.error { background-color: #f9e4e2; color: #8c2a20; border-left: 5px solid var(--error-color); }
        
        #role-details {
            border-top: 1px dashed var(--border-color);
            padding-top: 20px;
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 15px;
            grid-column: 1 / -1;
        }
        #role-details.active { display: grid; }
        #role-details h4 { grid-column: 1 / -1; font-size: 1.1em; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        
        /* Tampilan Tabel (Desktop Base) */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        table th, table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; background-color: var(--bg-container); }
        table th { background-color: var(--primary-color); color: var(--secondary-color); font-weight: 600; text-transform: uppercase; font-size: 0.9em; border-bottom: none; }
        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:hover { background-color: #f0fff0; }
        
        .action-buttons { display: flex; gap: 8px; }
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border: none;
            border-radius: var(--border-radius-md);
            color: white;
            text-decoration: none;
            font-size: 0.9em;
            cursor: pointer;
            transition: all 0.2s;
        }
        .action-btn:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .btn-edit { background-color: var(--edit-color); }
        .btn-delete { background-color: var(--delete-color); }
        
        .role-badge { padding: 5px 12px; border-radius: 9999px; font-size: 0.8em; font-weight: 600; text-transform: capitalize; display: inline-block; }
        .role-badge-Admin { background-color: #e0f8e0; color: #1a8917; border: 1px solid #1a8917; }
        .role-badge-Guru_Mapel { background-color: #d1fae5; color: #047857; }
        .role-badge-Wali_Kelas { background-color: #fef3c7; color: #92400e; }
        .role-badge-Kepala_Jurusan { background-color: #ffe4e6; color: #db2777; }
        .role-badge-Guru_BK { background-color: #e0f2fe; color: #075985; }

        /* Styling Modal Box */
        .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow-y: auto; background-color: rgba(0, 0, 0, 0.5); padding: 10px; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background-color: var(--bg-container); padding: 25px; border-radius: var(--border-radius-lg); width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3); position: relative; transform: scale(0.9) translateY(40px); opacity: 0; transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); display: grid; grid-template-columns: 1fr; gap: 20px; }
        .modal.active .modal-content { transform: scale(1) translateY(0); opacity: 1; }
        .modal-content h3 { margin-bottom: 0; font-size: 1.6em; color: var(--primary-dark); grid-column: 1 / -1; }
        .modal-content form { grid-template-columns: 1fr; gap: 20px; }
        .modal-footer { margin-top: 15px; text-align: right; grid-column: 1 / -1; display: flex; gap: 10px; justify-content: flex-end; }
        .modal-footer button { padding: 10px 20px; border: none; border-radius: var(--border-radius-md); font-weight: 600; cursor: pointer; transition: opacity 0.3s; }

        /* --- Footer Styling --- */
        footer {
            text-align: center;
            padding: 25px 30px;
            background-color: var(--primary-color); /* Latar belakang hijau */
            color: var(--secondary-color); /* Teks putih agar kontras */
            font-size: 0.9em;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
        }
        footer p {
            margin: 0;
            margin-bottom: 5px;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9); /* Sedikit transparan untuk p */
        }
        footer a {
            color: #87CEFA; /* Warna biru muda untuk link */
            text-decoration: none;
            font-weight: 600;
        }
        footer a:hover {
            text-decoration: underline;
        }
        
        /* ====================================================================
           RESPONSIVE DESIGN - UNTUK TAMPILAN MOBILE (DISEMPURNAKAN)
           ==================================================================== */
           
        /* Tablet & Mobile (di bawah 992px) */
        @media screen and (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: none;
            }
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
                padding-top: 80px;
            }
            .menu-toggle {
                display: block;
            }
            h1 { font-size: 1.8em; }
            .container, .form-card { padding: 20px; }
            .action-btn .btn-text { display: none; } /* Sembunyikan teks di tombol aksi agar lebih ringkas */

            footer {
                margin-left: 0;
                width: 100%;
                padding-left: 20px;
                padding-right: 20px;
            }
        }

        /* Mobile (di bawah 768px) - Tampilan Kartu Pengguna */
        @media screen and (max-width: 768px) {
            .main-content { padding: 15px; padding-top: 75px; }
            .container, .form-card { padding: 15px; }
            h1 { font-size: 1.5em; }
            h2 { font-size: 1.3em; margin-top: 1.5rem; margin-bottom: 1rem;}

            /* Sembunyikan header tabel */
            .table-container { box-shadow: none; }
            table thead { display: none; }
            
            /* Ubah baris tabel menjadi kartu */
            table, table tbody, table tr, table td { display: block; width: 100%; }
            table { border-collapse: collapse; }
            
            table tr {
                margin-bottom: 15px;
                border-radius: var(--border-radius-md);
                box-shadow: var(--box-shadow-medium);
                padding: 15px;
                background-color: var(--bg-container);
            }

            table td {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
                padding: 10px 0;
                border-bottom: 1px solid var(--border-color);
                background-color: transparent;
            }
            
            table tr td:last-child { border-bottom: none; }

            /* Buat label dari atribut data-label */
            table td::before {
                content: attr(data-label);
                font-weight: 600;
                font-size: 0.8em;
                color: var(--text-light);
                margin-bottom: 4px;
                text-transform: uppercase;
            }

            /* Beri penekanan pada data penting */
            td[data-label="Nama"] {
                font-size: 1.15em;
                font-weight: 600;
                color: var(--primary-dark);
            }
            td[data-label="Nama"]::before { display: none; } /* Sembunyikan label "Nama" */
            
            /* Tata ulang tombol aksi */
            td[data-label="Aksi"] { padding-top: 15px; }
            td[data-label="Aksi"]::before { display: none; }
            .action-buttons {
                width: 100%;
                margin-top: 0;
            }
            .action-btn {
                flex-grow: 1;
                justify-content: center;
            }
            .modal-content { width: 95%; max-width: 95%; padding: 20px;}
        }

        /* Mobile sangat kecil (di bawah 480px) */
        @media screen and (max-width: 480px) {
            .search-form .form-group > div {
                flex-direction: column;
            }
            .search-form button[type="submit"] {
                width: 100%;
            }
            .modal-footer { flex-direction: column-reverse; }
            .modal-footer button { width: 100%; }
            .action-btn .btn-text { display: inline; } /* Tampilkan lagi teks di tombol aksi saat full width */
        }
    </style>
</head>
<body>
    <div class="page-content-wrapper">
        <div class="main-content-and-sidebar">
            <button class="menu-toggle" id="menu-toggle" aria-label="Buka Menu">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <i class="fas fa-school"></i>
                    <span>SMK JTI</span>
                </div>
                <div class="user-profile">
                    <img src="<?= $current_user_profile_pic ?>" alt="Foto Profil Admin">
                    <div class="name"><?= $current_user_name ?></div>
                    <div class="role"><?= $current_user_role ?></div>
                </div>
                <nav class="sidebar-menu">
                    <a href="#"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="admin.php" class="active"><i class="fas fa-users-cog"></i> Manajemen Pengguna</a>
                    <a href="#"><i class="fas fa-book"></i> Manajemen Mapel</a>
                    <a href="#"><i class="fas fa-chalkboard-teacher"></i> Manajemen Kelas</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <main class="main-content">
                <div class="container">
                    <h1><i class="fas fa-users-cog"></i> Manajemen Pengguna</h1>
                    <p>Kelola data pengguna sistem seperti Admin, Guru, Wali Kelas, dan lainnya. Anda bisa menambah, mengubah, dan menghapus data pengguna.</p>
                </div>

                <?php if (!empty($message)) : ?>
                    <div class="alert <?= $message_type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                        <span><?= nl2br(str_replace('**', '<strong>', $message)) ?></span>
                    </div>
                <?php endif; ?>

                <div class="form-card">
                    <h2><i class="fas fa-user-plus"></i> Tambah Pengguna Baru</h2>
                    <form action="" method="post" id="addUserForm">
                        <div class="form-group">
                            <label for="nama"><i class="fas fa-user"></i> Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" placeholder="Contoh: Budi Santoso" required>
                        </div>
                        <div class="form-group">
                            <label for="nip"><i class="fas fa-id-card"></i> NIP (Nomor Induk Pegawai)</label>
                            <input type="text" id="nip" name="nip" placeholder="Masukkan NIP unik" required>
                        </div>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="email" name="email" placeholder="contoh@email.com" required>
                        </div>
                        <div class="form-group">
                            <label for="password"><i class="fas fa-key"></i> Password</label>
                            <div class="password-wrapper">
                                <div class="password-input-group">
                                    <input type="password" id="password" name="password" placeholder="Minimal 8 karakter" required>
                                    <i class="fas fa-eye password-toggle-icon" onclick="togglePasswordVisibility('password')"></i>
                                </div>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="role"><i class="fas fa-user-tag"></i> Role Pengguna</label>
                            <select id="role" name="role" required>
                                <option value="" disabled selected>-- Pilih Role --</option>
                                <option value="Admin">Admin</option>
                                <option value="Guru_Mapel">Guru Mata Pelajaran</option>
                                <option value="Wali_Kelas">Wali Kelas</option>
                                <option value="Kepala_Jurusan">Kepala Jurusan</option>
                                <option value="Guru_BK">Guru BK</option>
                            </select>
                        </div>

                        <div id="role-details" class="full-width">
                            </div>

                        <button type="submit" name="tambah_user" class="full-width"><i class="fas fa-plus-circle"></i> Tambah Pengguna</button>
                    </form>
                </div>

                <div class="container">
                    <h2><i class="fas fa-list-ul"></i> Daftar Pengguna</h2>
                    
                    <form method="get" action="" class="search-form" style="margin-bottom: 25px;">
                        <div class="form-group full-width">
                            <label for="search">Cari Pengguna (Nama, NIP, Email)</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="search" id="search" name="search" placeholder="Ketik untuk mencari..." value="<?= htmlspecialchars($search_term) ?>" style="flex-grow: 1;">
                                <button type="submit" style="grid-column: auto; padding: 12px 20px; font-size: 1em; width: auto;"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                    </form>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>NIP</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users_data) > 0) : ?>
                                    <?php foreach ($users_data as $user) : ?>
                                        <tr id="user-row-<?= $user['user_id'] ?>">
                                            <td data-label="Nama"><?= htmlspecialchars($user['nama']) ?></td>
                                            <td data-label="NIP"><?= htmlspecialchars($user['nip']) ?></td>
                                            <td data-label="Email"><?= htmlspecialchars($user['email']) ?></td>
                                            <td data-label="Role"><span class="role-badge role-badge-<?= htmlspecialchars($user['role']) ?>"><?= str_replace('_', ' ', htmlspecialchars($user['role'])) ?></span></td>
                                            <td data-label="Aksi">
                                                <div class="action-buttons">
                                                    <button class="action-btn btn-edit" onclick="openEditModal(<?= $user['user_id'] ?>)"><i class="fas fa-edit"></i> <span class="btn-text">Edit</span></button>
                                                    <button class="action-btn btn-delete" onclick="confirmDelete(<?= $user['user_id'] ?>, '<?= htmlspecialchars(addslashes($user['nama'])) ?>')"><i class="fas fa-trash"></i> <span class="btn-text">Hapus</span></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 20px;">
                                            <?php if (!empty($search_term)): ?>
                                                Tidak ada pengguna yang cocok dengan kata kunci "<strong><?= htmlspecialchars($search_term) ?></strong>".
                                            <?php else: ?>
                                                Belum ada pengguna yang terdaftar.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>

        <footer>
            <p>© 2025 SMK JTI. Hak Cipta Dilindungi.</p>
            <p>Dikembangkan oleh <a href="#">Muhammad Fajarudin</a></p>
        </footer>
        </div>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>


<div id="editUserModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-user-edit"></i> Edit Data Pengguna</h3>
        <form id="editUserForm">
            <input type="hidden" id="edit_user_id" name="edit_user_id">
            <div class="form-group">
                <label for="edit_nama">Nama Lengkap</label>
                <input type="text" id="edit_nama" name="edit_nama" required>
            </div>
            <div class="form-group">
                <label for="edit_nip">NIP</label>
                <input type="text" id="edit_nip" name="edit_nip" required>
            </div>
            <div class="form-group">
                <label for="edit_email">Email</label>
                <input type="email" id="edit_email" name="edit_email" required>
            </div>
            <div class="form-group">
                <label for="edit_role">Role</label>
                <select id="edit_role" name="edit_role" required>
                    <option value="Admin">Admin</option>
                    <option value="Guru_Mapel">Guru Mata Pelajaran</option>
                    <option value="Wali_Kelas">Wali Kelas</option>
                    <option value="Kepala_Jurusan">Kepala Jurusan</option>
                    <option value="Guru_BK">Guru BK</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_password">Password Baru (Opsional)</label>
                <div class="password-wrapper">
                    <div class="password-input-group">
                        <input type="password" id="edit_password" name="edit_password" placeholder="Isi untuk mengubah">
                        <i class="fas fa-eye password-toggle-icon" onclick="togglePasswordVisibility('edit_password')"></i>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeEditModal()" style="background-color: #7f8c8d; color: white;">Batal</button>
                <button type="submit" style="background-color: var(--primary-color); color: white; grid-column: auto; padding: 10px 20px;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Script untuk Toggle Sidebar Mobile & Overlay ---
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (menuToggle && sidebar && overlay) {
        const toggleSidebar = () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        };
        menuToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
    }

    // --- Script untuk Detail Role Dinamis di Form Tambah ---
    const roleSelect = document.getElementById('role');
    const roleDetailsContainer = document.getElementById('role-details');
    const roleDetailsHTML = {
        'Guru_Mapel': `
            <h4><i class="fas fa-book-open"></i> Detail Guru Mata Pelajaran</h4>
            <div class="form-group"><label for="mapel">Mata Pelajaran</label><input type="text" id="mapel" name="mapel" placeholder="Contoh: Matematika" required></div>
            <div class="form-group"><label for="jam_pelajaran">Jam Pelajaran / Minggu</label><input type="number" id="jam_pelajaran" name="jam_pelajaran" placeholder="Contoh: 4" min="1" required></div>`,
        'Wali_Kelas': `
            <h4><i class="fas fa-chalkboard-user"></i> Detail Wali Kelas</h4>
            <div class="form-group"><label for="kelas_dipegang">Kelas</label><input type="text" id="kelas_dipegang" name="kelas_dipegang" placeholder="Contoh: XII RPL 1" required></div>
            <div class="form-group"><label for="tahun_ajaran">Tahun Ajaran</label><input type="text" id="tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2024/2025" required value="<?= date('Y') . '/' . (date('Y') + 1) ?>"></div>`,
        'Kepala_Jurusan': `
            <h4><i class="fas fa-sitemap"></i> Detail Kepala Jurusan</h4>
            <div class="form-group full-width"><label for="jurusan_dipegang">Jurusan</label><input type="text" id="jurusan_dipegang" name="jurusan_dipegang" placeholder="Contoh: Rekayasa Perangkat Lunak" required></div>`
    };
    roleSelect.addEventListener('change', function() {
        const selectedRole = this.value;
        if (roleDetailsHTML[selectedRole]) {
            roleDetailsContainer.innerHTML = roleDetailsHTML[selectedRole];
            roleDetailsContainer.classList.add('active');
        } else {
            roleDetailsContainer.innerHTML = '';
            roleDetailsContainer.classList.remove('active');
        }
    });

    // --- Script untuk Toggle Visibilitas Password ---
    window.togglePasswordVisibility = function(inputId) {
        const passwordInput = document.getElementById(inputId);
        const icon = passwordInput.nextElementSibling;
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // --- Script AJAX untuk Modal Edit ---
    const modal = document.getElementById('editUserModal');
    const editForm = document.getElementById('editUserForm');
    window.openEditModal = function(userId) {
        fetch(`?action=get_user_data&id=${userId}`)
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data) {
                    document.getElementById('edit_user_id').value = res.data.user_id;
                    document.getElementById('edit_nama').value = res.data.nama;
                    document.getElementById('edit_nip').value = res.data.nip;
                    document.getElementById('edit_email').value = res.data.email;
                    document.getElementById('edit_role').value = res.data.role;
                    document.getElementById('edit_password').value = '';
                    modal.classList.add('active');
                } else {
                    Swal.fire('Error', res.message || 'Gagal memuat data pengguna.', 'error');
                }
            }).catch(() => Swal.fire('Error', 'Terjadi kesalahan jaringan.', 'error'));
    }
    window.closeEditModal = function() { modal.classList.remove('active'); }
    editForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'update_user_modal');
        fetch('', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                closeEditModal();
                Swal.fire({ icon: 'success', title: 'Berhasil!', html: res.message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>'), timer: 2000, showConfirmButton: false });
                const userRow = document.getElementById(`user-row-${res.data.user_id}`);
                if (userRow) {
                    userRow.cells[0].textContent = res.data.nama;
                    userRow.cells[1].textContent = res.data.nip;
                    userRow.cells[2].textContent = res.data.email;
                    userRow.cells[3].innerHTML = `<span class="role-badge role-badge-${res.data.role}">${res.data.role_display}</span>`;
                }
            } else {
                Swal.fire('Gagal!', res.message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>'), 'error');
            }
        }).catch(() => Swal.fire('Error', 'Terjadi kesalahan saat mengirim data.', 'error'));
    });

    // --- Script untuk Konfirmasi Hapus ---
    window.confirmDelete = function(userId, userName) {
        Swal.fire({
            title: 'Anda Yakin?',
            html: `Anda akan menghapus pengguna: <br><strong>${userName}</strong> (ID: ${userId})<br><br>Tindakan ini tidak dapat dibatalkan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#7f8c8d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('user_id', userId);
                fetch('', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Terhapus!', res.message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>'), 'success');
                        const rowToRemove = document.getElementById(`user-row-${res.user_id}`);
                        if (rowToRemove) rowToRemove.remove();
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                }).catch(() => Swal.fire('Error', 'Terjadi kesalahan jaringan.', 'error'));
            }
        });
    }
});
</script>
</body>
</html>