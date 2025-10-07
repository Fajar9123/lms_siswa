<?php

// ====================================================================
// admin.php: MANAJEMEN PENGGUNA (Revisi & Perbaikan)
// ====================================================================

session_start();

// Pastikan file db_config.php ada dan berisi koneksi PDO ($pdo)
require_once 'db_config.php'; 

// --- PERIKSA AUTENTIKASI DAN ROLE ---

// BLOK INI SEKARANG AKTIF: Memeriksa apakah user_id ada di session dan role adalah 'Admin'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    // Redirect ke dashboard role masing-masing jika sudah login, tetapi bukan Admin
    if (isset($_SESSION['role'])) {
        $redirect_page = ($_SESSION['role'] === 'Guru_Mapel') ? 'guru_dashboard.php' : 'wali_kelas_dashboard.php';
        header("Location: $redirect_page");
        exit;
    }
    // Redirect ke login jika belum login sama sekali
    header('Location: admin_login.php');
    exit;
}


// Lanjutkan inisialisasi user yang login
// Blok dummy user telah dihapus/dinonaktifkan
$logged_in_user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// --- DYNAMIC LOGGED-IN USER SETUP ---
// Nilai default jika gagal ambil data dari DB
$current_user_name = "Pengguna Tidak Dikenal";
$current_user_role = "Undefined Role";
$current_user_profile_pic = "https://placehold.co/80x80/ff7e00/ffffff?text=ADM"; 

if ($pdo && $logged_in_user_id) {
    try {
        $stmt = $pdo->prepare("SELECT nama, role, profile_pic FROM users WHERE user_id = ?");
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
        // Error fetching user data.
    }
}
// --- END DYNAMIC LOGGED-IN USER SETUP ---


// --- FUNGSI AJAX HANDLERS (Dipertahankan dan Disesuaikan) ---

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

        if (!$user_id || empty($nama) || empty($nip) || empty($email) || empty($role)) {
            $response['message'] = "Data tidak valid atau kolom wajib diisi!";
        } else {
            // Cek apakah NIP atau Email duplikat (selain diri sendiri)
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (nip = ? OR email = ?) AND user_id != ?");
            $stmt_check->execute([$nip, $email, $user_id]);
            if ($stmt_check->fetchColumn() > 0) {
                   $response['message'] = "Gagal: NIP atau Email sudah digunakan oleh pengguna lain!";
                   echo json_encode($response);
                   exit;
            }

            $sql = "UPDATE users SET nama = :nama, nip = :nip, email = :email, role = :role";
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
                
                // Hapus detail terkait (ASUMSI foreign key CASCADE BELUM DISET):
                $pdo->prepare("DELETE FROM guru_mapel_detail WHERE user_id = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM wali_kelas_assignment WHERE user_id = ?")->execute([$user_id]);

                // Hapus pengguna utama
                $sql = "DELETE FROM users WHERE user_id = :id";
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
            $stmt = $pdo->prepare("SELECT user_id, nama, nip, email, role FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

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
        $nama           = trim($_POST['nama'] ?? '');
        $nip            = trim($_POST['nip'] ?? '');
        $password_plain = $_POST['password'] ?? '';
        $email          = trim($_POST['email'] ?? '');
        $role           = $_POST['role'] ?? '';

        if (empty($nama) || empty($nip) || empty($password_plain) || empty($email) || empty($role)) {
            $message = "Semua kolom wajib diisi!";
            $message_type = 'error';
        } else {
            if (strlen($password_plain) < 8) {
                $message = "Password minimal 8 karakter!";
                $message_type = 'error';
            } else {
                $hashed_password = password_hash($password_plain, PASSWORD_BCRYPT);
                
                // Cek duplikasi NIP atau Email sebelum INSERT
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE nip = ? OR email = ?");
                $stmt_check->execute([$nip, $email]);
                if ($stmt_check->fetchColumn() > 0) {
                    $message = "Gagal: NIP atau Email sudah terdaftar (Duplikat Data)!";
                    $message_type = 'error';
                } else {
                    $sql_user = "INSERT INTO users (nama, nip, password, email, role) VALUES (:nama, :nip, :password, :email, :role)";
                    
                    try {
                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare($sql_user);
                        $stmt->execute([':nama' => $nama, ':nip' => $nip, ':password' => $hashed_password, ':email' => $email, ':role' => $role]);
                        
                        $last_id = $pdo->lastInsertId();
                        $message = "User baru berhasil ditambahkan! User ID: " . $last_id;
                        $message_type = 'success';
                        
                        // Logika detail role
                        if ($role === 'Guru_Mapel') {
                            $mapel = trim($_POST['mapel'] ?? '');
                            $jam = filter_var($_POST['jam_pelajaran'] ?? 0, FILTER_VALIDATE_INT);
                            if (!empty($mapel) && $jam !== false && $jam > 0) {
                                // PENTING: Pastikan tabel guru_mapel_detail sudah ada
                                $sql_detail = "INSERT INTO guru_mapel_detail (user_id, mata_pelajaran, jam_pelajaran) VALUES (?, ?, ?)";
                                $pdo->prepare($sql_detail)->execute([$last_id, $mapel, $jam]);
                                $message .= " dan detail Guru Mapel berhasil ditambahkan.";
                            } else {
                                 $message .= ". (Peringatan: Detail Guru Mapel tidak lengkap/invalid)";
                            }
                        } elseif ($role === 'Wali_Kelas') {
                            $kelas = trim($_POST['kelas_dipegang'] ?? '');
                            // Set default tahun ajaran jika kosong
                            $tahun = trim($_POST['tahun_ajaran'] ?? '') ?: date('Y') . '/' . (date('Y') + 1); 
                            if (!empty($kelas)) {
                                // PENTING: Pastikan tabel wali_kelas_assignment sudah ada
                                $sql_assign = "INSERT INTO wali_kelas_assignment (user_id, kelas_dipegang, tahun_ajaran) VALUES (?, ?, ?)";
                                $pdo->prepare($sql_assign)->execute([$last_id, $kelas, $tahun]);
                                $message .= " dan penugasan Wali Kelas berhasil ditambahkan.";
                            } else {
                                 $message .= ". (Peringatan: Detail Wali Kelas tidak lengkap)";
                            }
                        }
                        
                        $pdo->commit();
                        
                        // Redirect-After-Post (PRG Pattern)
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?status=' . $message_type . '&msg=' . urlencode($message));
                        exit;

                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        // 23000 adalah kode error untuk UNIQUE constraint violation
                        $message = ($e->getCode() == '23000') ? "Gagal: NIP atau Email sudah terdaftar (Duplikat Data)!" : "Gagal menyimpan data: " . $e->getMessage();
                        $message_type = 'error';
                    }
                }
            }
        }
    }
}

// Ambil pesan dari Redirect-After-Post (jika ada)
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $message_type = $_GET['status'];
    // Gunakan htmlspecialchars(urldecode()) untuk mencegah XSS dalam pesan yang dikirim via URL
    $message = htmlspecialchars(urldecode($_GET['msg'])); 
}


/**
 * Mengambil daftar 15 pengguna terbaru.
 * @return array Daftar pengguna.
 */
function getUsers($pdo) {
    if (!$pdo) return [];
    try {
        // user_id > 0 untuk mengabaikan user ID 1 yang mungkin digunakan untuk dummy/testing.
        $stmt = $pdo->prepare("SELECT user_id, nama, nip, email, role, created_at FROM users WHERE user_id > 0 ORDER BY user_id DESC LIMIT 15"); 
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return []; 
    }
}

// Ambil data pengguna untuk ditampilkan di tabel
$users_data = getUsers($pdo);

// ====================================================================
// 2. HTML DAN TAMPILAN (Disempurnakan & Dilenkapi)
// ====================================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SMK JTI 1</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        :root {
            --primary-color: #ff7e00; 
            --primary-dark: #e66e00;
            --secondary-color: #ffffff;
            --bg-page: #f0f4f8; 
            --bg-container: #ffffff;
            --text-dark: #2c3e50;      
            --text-light: #7f8c8d;     
            --success-color: #27ae60;
            --error-color: #c0392b;    
            --info-color: #2980b9;
            --edit-color: #3498db;
            --delete-color: #e74c3c;
            --border-color: #dcdcdc; 
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
        
        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: 100%; 
        }
        
        .page-content-wrapper {
            display: flex;
            flex: 1; 
            width: 100%;
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
        }
        
        .sidebar-header { text-align: center; padding: 10px 20px 20px; font-size: 1.5em; font-weight: 700; letter-spacing: 1px; border-bottom: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .user-profile { text-align: center; padding: 15px 20px; margin-bottom: 25px; }
        .user-profile img { width: 80px; height: 80px; border-radius: 50%; border: 4px solid var(--secondary-color); object-fit: cover; margin-bottom: 10px; box-shadow: 0 0 15px rgba(0, 0, 0, 0.3); }
        .user-profile .name { font-size: 1.1em; font-weight: 600; }
        .user-profile .role { font-size: 0.8em; color: rgba(255, 255, 255, 0.8); }
        .sidebar-menu a { display: flex; align-items: center; padding: 15px 25px; color: var(--secondary-color); text-decoration: none; font-size: 0.95em; transition: background-color 0.3s ease, padding-left 0.3s ease; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255, 255, 255, 0.15); border-left: 4px solid var(--secondary-color); font-weight: 600; }
        .sidebar-menu i { margin-right: 15px; font-size: 1.1em; width: 20px; text-align: center; }
        .menu-toggle { 
            /* Sembunyikan di Desktop */
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
            font-size: 1em; 
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); 
            transition: background-color 0.3s; 
        }
        
        .main-content { 
            flex-grow: 1; 
            padding: 40px; 
            margin-left: var(--sidebar-width); 
            transition: margin-left 0.3s ease-in-out; 
            width: calc(100% - var(--sidebar-width)); 
            min-height: 100vh;
        }
        
        .container { max-width: 100%; background: var(--bg-container); padding: 40px; border-radius: var(--border-radius-lg); box-shadow: var(--box-shadow-medium); margin-bottom: 30px; }
        h1 { font-size: 2em; font-weight: 700; display: flex; align-items: center; gap: 15px; border-bottom: 2px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem; }
        h1 i { color: var(--primary-color); }
        h2 { font-size: 1.5em; font-weight: 600; margin-top: 2.5rem; margin-bottom: 1rem; color: var(--primary-dark); }
        p { color: var(--text-light); margin-bottom: 2rem; line-height: 1.6; }
        .form-card { padding: 30px; border-radius: var(--border-radius-md); background-color: #fdfdfd; border: 1px solid #e8e8e8; margin-bottom: 40px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); }
        form { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .full-width { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 500; font-size: 0.9em; color: var(--text-dark); }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 1px solid #ccc; border-radius: var(--border-radius-md); font-family: 'Poppins', sans-serif; font-size: 1em; transition: border-color 0.3s, box-shadow 0.3s; background-color: #ffffff; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(255, 126, 0, 0.2); background-color: #fafff5; }
        
        /* START CSS UNTUK IKON MATA */
        .password-wrapper { 
            position: relative; 
            display: block; 
        }
        .password-input-group { 
            position: relative; 
            display: flex; 
            align-items: center; 
        }
        .password-wrapper input { 
            padding-right: 45px;
            width: 100%; 
        }
        /* Kelas CSS untuk Ikon Mata (digunakan di form tambah dan modal edit) */
        .password-toggle-icon { 
            position: absolute; 
            right: 15px; 
            top: 50%; 
            /* Perbaikan posisi agar ikon terlihat di tengah field input */
            transform: translateY(-50%); 
            color: var(--text-light); 
            cursor: pointer; 
            transition: color 0.3s; 
            z-index: 2; 
        }
        .password-toggle-icon:hover { color: var(--primary-color); }
        /* END CSS UNTUK IKON MATA */
        
        button[type="submit"] { grid-column: 1 / -1; padding: 15px 20px; background-color: var(--primary-color); color: var(--secondary-color); border: none; border-radius: var(--border-radius-md); font-size: 1.1em; font-weight: 600; cursor: pointer; transition: background-color 0.3s, transform 0.2s, box-shadow 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 10px rgba(255, 126, 0, 0.3); }
        button[type="submit"]:hover { background-color: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(255, 126, 0, 0.4); }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: var(--border-radius-md); font-weight: 500; display: flex; align-items: center; gap: 10px; transition: opacity 0.5s ease; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert.success { background-color: #eaf7ed; color: #206b32; border-left: 5px solid var(--success-color); }
        .alert.error { background-color: #f9e4e2; color: #8c2a20; border-left: 5px solid var(--error-color); }
        .alert.temp { background-color: #fff3cd; color: #664d03; border-left: 5px solid #ffc107; }
        
        #role-details { border-top: 2px dashed #eee; padding-top: 20px; display: none; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 15px; }
        #role-details.active { display: grid; }
        #role-details h4 { grid-column: 1 / -1; font-size: 1.1em; color: var(--primary-color); display: flex; align-items: center; gap: 10px; }
        .table-container { overflow-x: auto; margin-bottom: 20px;}
        table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); overflow: hidden; }
        table th, table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        table th { background-color: var(--primary-color); color: var(--secondary-color); font-weight: 600; text-transform: uppercase; font-size: 0.9em; border-bottom: none; }
        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:hover { background-color: #fff9f2; }
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
            transition: background-color 0.3s; 
        }
        .action-btn:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .btn-edit { background-color: var(--edit-color); }
        .btn-delete { background-color: var(--delete-color); }
        .role-badge { 
            padding: 5px 12px; 
            border-radius: 9999px; 
            font-size: 0.8em; 
            font-weight: 600; 
            text-transform: capitalize; 
            display: inline-flex; 
            align-items: center;
        }
        .role-badge-Admin { background-color: #e0e7ff; color: #4338ca; }
        .role-badge-Guru_Mapel { background-color: #d1fae5; color: #047857; }
        .role-badge-Wali_Kelas { background-color: #fef3c7; color: #92400e; }


        /* --- Styling Modal Box --- */
        .modal {
            display: none; 
            position: fixed;
            z-index: 1050; 
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow-y: auto; 
            background-color: rgba(0, 0, 0, 0.5); 
            padding: 10px; 
        }

        .modal.active { display: flex; align-items: center; justify-content: center; } 

        .modal-content {
            background-color: var(--bg-container);
            margin: 8% auto; 
            padding: 30px;
            border-radius: var(--border-radius-lg);
            width: 90%;
            max-width: 600px; 
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3); 
            position: relative;
            transform: scale(0.9) translateY(40px); 
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); 
        }
        
        .modal.active .modal-content {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
        
        .modal-content button[type="submit"] {
             grid-column: 1 / -1; 
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 25px;
            color: var(--text-light);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        .modal-close:hover,
        .modal-close:focus {
            color: var(--delete-color);
            text-decoration: none;
            cursor: pointer;
        }

        /* ====================================================================
        Media Query (Responsif Mobile)
        ==================================================================== */
        @media (max-width: 992px) {
            
            .page-content-wrapper { flex-direction: column; }
            body { display: block; } 
            
            /* Sidebar di Mobile */
            .sidebar { 
                transform: translateX(-100%); 
                padding-top: 70px; 
                height: 100vh;
                position: fixed;
            }
            .sidebar.active { 
                transform: translateX(0); 
                box-shadow: 5px 0 20px rgba(0, 0, 0, 0.5); 
            }
            .main-content { 
                margin-left: 0; 
                padding: 15px; 
                padding-top: 80px; 
                width: 100%; 
                min-height: auto; 
            }
             .container { 
                padding: 20px; 
                margin-bottom: 20px; 
                max-width: 100%;
            }
            .menu-toggle { 
                display: flex; 
            }
            
            /* Form dan Tabel di Mobile */
            form { grid-template-columns: 1fr; gap: 15px; }
            #role-details { grid-template-columns: 1fr; gap: 15px; }
            table thead { display: none; }
            table { border: none; border-radius: 0; }
            table tbody, table tr { display: block; width: 100%; }
            table tr { margin-bottom: 20px; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); }
            table td { padding: 12px 15px; border: none; border-bottom: 1px dashed #e1e1e1; position: relative; display: flex; align-items: center; justify-content: space-between; }
            table td:before { content: attr(data-label); text-align: left; font-weight: 600; color: var(--text-dark); flex-basis: 40%; flex-shrink: 0; padding-right: 15px;}
            .td-content-wrapper { flex-basis: 60%; flex-grow: 1; text-align: right; word-break: break-all; display: flex; justify-content: flex-end; align-items: center; min-width: 0; }
            table td[data-label="Aksi"] { justify-content: flex-end; border-bottom: none; }
            table td[data-label="Aksi"]:before { display: none; }
            table td:last-child { border-bottom: none; }
        }
        
        /* Tambahan: Untuk layar sangat kecil (di bawah 500px) */
        @media (max-width: 500px) {
            .container { padding: 10px; }
            .main-content { padding: 10px; padding-top: 80px; }
            h1 { font-size: 1.4em; padding-bottom: 10px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="page-content-wrapper">
        <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i> Menu</button>

        <div class="sidebar" id="sidebar">
            <div class="sidebar-header"><i class="fas fa-graduation-cap"></i><span>SMK JTI 1</span></div>
            <div class="user-profile">
                <img src="<?php echo htmlspecialchars($current_user_profile_pic); ?>" alt="Profil Pengguna" onerror="this.onerror=null;this.src='https://placehold.co/80x80/ff7e00/ffffff?text=ADM';">
                <div class="name"><?php echo htmlspecialchars($current_user_name); ?></div>
                <div class="role"><?php echo htmlspecialchars($current_user_role); ?></div>
            </div>
            <nav class="sidebar-menu">
                <a href="admin.php" class="active"><i class="fas fa-user-plus"></i> Tambah Pengguna</a>
                <a href="#data-pengguna"><i class="fas fa-users"></i> Data Pengguna</a>
                <!-- Arahkan ke logout.php atau dashboard lain jika ada -->
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>

        <main class="main-content" id="mainContent">
            <div class="container">
                <h1><i class="fas fa-user-shield"></i> Manajemen Pengguna</h1>
                <p>Formulir untuk menambah dan mengelola data pengguna sistem. Setelah penambahan, data akan muncul di tabel **Daftar Pengguna Terbaru** di bawah. Gunakan tombol **Edit** untuk memunculkan formulir edit dan **Hapus** untuk menghapus data.</p>
                
                <div id="alert-container">
                    <!-- Pesan dari PHP (Redirect-After-Post) -->
                    <?php if (!empty($message)): ?>
                        <div id="alert-message" class="alert <?php echo $message_type; ?>">
                            <i class="fas <?php echo ($message_type === 'success') ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <span><?php echo $message; ?></span>
                            <button onclick="document.getElementById('alert-message').style.display='none'" style="background:none; border:none; color:inherit; font-size:1.2em; margin-left:auto; cursor:pointer;">&times;</button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h2><i class="fas fa-plus-circle"></i> Tambah Pengguna Baru</h2>
                
                <div class="form-card">
                    <form method="POST" action="admin.php">
                        <input type="hidden" name="tambah_user" value="1">
                        
                        <div class="form-group">
                            <label for="nama">Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nip">NIP (Nomor Induk Pegawai)</label>
                            <input type="text" id="nip" name="nip" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>

                        <div class="form-group password-wrapper">
                            <label for="password">Password (Min. 8 Karakter)</label>
                            <div class="password-input-group">
                                <input type="password" id="password" name="password" required minlength="8">
                                <i class="fas fa-eye password-toggle-icon" onclick="togglePasswordVisibility('password')"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="role">Role Pengguna</label>
                            <select id="role" name="role" required onchange="showRoleDetails(this.value)">
                                <option value="" disabled selected>Pilih Role</option>
                                <option value="Admin">Admin</option>
                                <option value="Guru_Mapel">Guru Mata Pelajaran</option>
                                <option value="Wali_Kelas">Wali Kelas</option>
                            </select>
                        </div>

                        <div id="role-details" class="full-width">
                            <h4><i class="fas fa-info-circle"></i> Detail Tambahan (Sesuai Role)</h4>
                            
                            <div id="guru-mapel-fields" style="display:none;">
                                <div class="form-group">
                                    <label for="mapel">Mata Pelajaran yang Diampu</label>
                                    <input type="text" id="mapel" name="mapel" placeholder="Contoh: Matematika">
                                </div>
                                <div class="form-group">
                                    <label for="jam_pelajaran">Jumlah Jam Pelajaran per Minggu</label>
                                    <input type="number" id="jam_pelajaran" name="jam_pelajaran" min="1" placeholder="Contoh: 4">
                                </div>
                            </div>

                            <div id="wali-kelas-fields" style="display:none;">
                                <div class="form-group">
                                    <label for="kelas_dipegang">Kelas yang Dipegang</label>
                                    <input type="text" id="kelas_dipegang" name="kelas_dipegang" placeholder="Contoh: XII RPL A">
                                </div>
                                <div class="form-group">
                                    <label for="tahun_ajaran">Tahun Ajaran</label>
                                    <input type="text" id="tahun_ajaran" name="tahun_ajaran" value="<?php echo date('Y') . '/' . (date('Y') + 1); ?>" placeholder="Contoh: 2024/2025">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit">
                            <i class="fas fa-save"></i> Tambah Pengguna
                        </button>
                    </form>
                </div>

                <a id="data-pengguna"></a>
                <h2><i class="fas fa-table"></i> Daftar Pengguna Terbaru</h2>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>NIP</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <?php if (!empty($users_data)): ?>
                                <?php foreach ($users_data as $user): ?>
                                    <tr data-user-id="<?php echo htmlspecialchars($user['user_id']); ?>">
                                        <td data-label="ID"><?php echo htmlspecialchars($user['user_id']); ?></td>
                                        <td data-label="Nama"><?php echo htmlspecialchars($user['nama']); ?></td>
                                        <td data-label="NIP"><?php echo htmlspecialchars($user['nip']); ?></td>
                                        <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td data-label="Role">
                                            <span class="role-badge role-badge-<?php echo htmlspecialchars($user['role']); ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $user['role'])); ?>
                                            </span>
                                        </td>
                                        <td data-label="Dibuat" class="td-content-wrapper"><?php echo htmlspecialchars(date('d/m/Y', strtotime($user['created_at']))); ?></td>
                                        <td data-label="Aksi" class="td-content-wrapper action-buttons">
                                            <button class="action-btn btn-edit" onclick="openEditModal(<?php echo htmlspecialchars($user['user_id']); ?>)"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="action-btn btn-delete" onclick="confirmDelete(<?php echo htmlspecialchars($user['user_id']); ?>, '<?php echo htmlspecialchars($user['nama']); ?>')"><i class="fas fa-trash"></i> Hapus</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">Belum ada data pengguna yang tersedia.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>
</div>


<!-- Modal Edit Pengguna -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Data Pengguna</h2>
        <form id="editUserForm" onsubmit="handleEditSubmit(event)">
            <input type="hidden" id="edit_user_id" name="edit_user_id">
            <input type="hidden" name="action" value="update_user_modal">

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
                <label for="edit_role">Role Pengguna</label>
                <select id="edit_role" name="edit_role" required>
                    <option value="Admin">Admin</option>
                    <option value="Guru_Mapel">Guru Mata Pelajaran</option>
                    <option value="Wali_Kelas">Wali Kelas</option>
                </select>
            </div>

            <div class="form-group password-wrapper full-width">
                <label for="edit_password">Password Baru (Kosongkan jika tidak ingin diubah)</label>
                <div class="password-input-group">
                    <input type="password" id="edit_password" name="edit_password" minlength="8" placeholder="Opsional: Min. 8 karakter">
                    <i class="fas fa-eye password-toggle-icon" onclick="togglePasswordVisibility('edit_password')"></i>
                </div>
            </div>

            <button type="submit">
                <i class="fas fa-sync-alt"></i> Simpan Perubahan
            </button>
        </form>
    </div>
</div>


<!-- Modal Konfirmasi Hapus -->
<div id="deleteConfirmModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('deleteConfirmModal')">&times;</span>
        <h2><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h2>
        <p id="deleteMessage">Apakah Anda yakin ingin menghapus pengguna ini?</p>
        <div style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px;">
            <button class="action-btn" style="background-color: var(--text-light);" onclick="closeModal('deleteConfirmModal')"><i class="fas fa-ban"></i> Batal</button>
            <button class="action-btn btn-delete" id="deleteConfirmButton" onclick=""><i class="fas fa-trash"></i> Hapus Permanen</button>
        </div>
    </div>
</div>


<script>
    // --- UTILITIES ---
    
    // 1. Toggle Password Visibility
    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = field.nextElementSibling;
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    // 2. Tampilkan Detail Role Tambahan
    function showRoleDetails(role) {
        const guruFields = document.getElementById('guru-mapel-fields');
        const waliFields = document.getElementById('wali-kelas-fields');
        const roleDetailsDiv = document.getElementById('role-details');
        
        // Sembunyikan semua dulu
        guruFields.style.display = 'none';
        waliFields.style.display = 'none';
        roleDetailsDiv.classList.remove('active');

        // Atur required status
        document.getElementById('mapel').required = false;
        document.getElementById('jam_pelajaran').required = false;
        document.getElementById('kelas_dipegang').required = false;

        if (role === 'Guru_Mapel') {
            guruFields.style.display = 'grid';
            document.getElementById('mapel').required = true;
            document.getElementById('jam_pelajaran').required = true;
            roleDetailsDiv.classList.add('active');
        } else if (role === 'Wali_Kelas') {
            waliFields.style.display = 'grid';
            document.getElementById('kelas_dipegang').required = true;
            roleDetailsDiv.classList.add('active');
        }
    }


    // 3. Tampilkan Pesan (Alert)
    function showAlert(message, type = 'temp') {
        const container = document.getElementById('alert-container');
        const alertHtml = `
            <div id="temp-alert" class="alert ${type}">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                <span>${message}</span>
                <button onclick="document.getElementById('temp-alert').remove()" style="background:none; border:none; color:inherit; font-size:1.2em; margin-left:auto; cursor:pointer;">&times;</button>
            </div>
        `;
        // Hapus alert sebelumnya jika ada
        const existingAlert = document.getElementById('temp-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        container.innerHTML += alertHtml;

        // Auto-hide alert setelah 5 detik
        setTimeout(() => {
            const tempAlert = document.getElementById('temp-alert');
            if (tempAlert) {
                tempAlert.style.opacity = '0';
                setTimeout(() => tempAlert.remove(), 500); // Hapus setelah transisi
            }
        }, 5000);
    }
    
    // 4. Modal Handler
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }

    function closeModal(modalId = null) {
        // Jika modalId tidak ditentukan, tutup semua modal
        if (modalId === null) {
            document.querySelectorAll('.modal').forEach(modal => modal.classList.remove('active'));
        } else {
            document.getElementById(modalId).classList.remove('active');
        }
    }


    // --- CRUD FUNCTIONS (AJAX) ---

    // 1. EDIT: Buka Modal & Isi Data
    async function openEditModal(userId) {
        try {
            const response = await fetch(`admin.php?action=get_user_data&id=${userId}`);
            const result = await response.json();

            if (result.success) {
                const user = result.data;
                document.getElementById('edit_user_id').value = user.user_id;
                document.getElementById('edit_nama').value = user.nama;
                document.getElementById('edit_nip').value = user.nip;
                document.getElementById('edit_email').value = user.email;
                document.getElementById('edit_role').value = user.role;
                document.getElementById('edit_password').value = ''; // Selalu reset password field

                openModal('editUserModal');
            } else {
                showAlert(`Gagal mengambil data: ${result.message}`, 'error');
            }
        } catch (error) {
            showAlert(`Terjadi kesalahan jaringan saat mengambil data: ${error.message}`, 'error');
        }
    }
    
    // 2. EDIT: Proses Submit Form Edit
    async function handleEditSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const userId = formData.get('edit_user_id');
        const submitButton = form.querySelector('button[type="submit"]');

        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

        try {
            const response = await fetch('admin.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showAlert(result.message, 'success');
                closeModal('editUserModal');
                
                // Update baris tabel secara dinamis
                const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                if (row) {
                    const data = result.data;
                    row.querySelector('td[data-label="Nama"]').textContent = data.nama;
                    row.querySelector('td[data-label="NIP"]').textContent = data.nip;
                    row.querySelector('td[data-label="Email"]').textContent = data.email;
                    
                    const roleBadge = row.querySelector('.role-badge');
                    roleBadge.textContent = data.role_display;
                    roleBadge.className = `role-badge role-badge-${data.role}`;
                }
            } else {
                showAlert(result.message, 'error');
            }
        } catch (error) {
            showAlert(`Terjadi kesalahan jaringan: ${error.message}`, 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-sync-alt"></i> Simpan Perubahan';
        }
    }
    
    // 3. DELETE: Konfirmasi
    function confirmDelete(userId, userName) {
        const deleteMessage = document.getElementById('deleteMessage');
        deleteMessage.innerHTML = `Apakah Anda yakin ingin menghapus pengguna **${userName}** (ID: ${userId})? Tindakan ini tidak dapat dibatalkan.`;
        
        const confirmButton = document.getElementById('deleteConfirmButton');
        // Set action untuk tombol konfirmasi
        confirmButton.onclick = () => deleteUser(userId);
        
        openModal('deleteConfirmModal');
    }

    // 4. DELETE: Proses Hapus
    async function deleteUser(userId) {
        closeModal('deleteConfirmModal');
        const confirmButton = document.getElementById('deleteConfirmButton');
        confirmButton.disabled = true;

        try {
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);

            const response = await fetch('admin.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showAlert(result.message, 'success');
                // Hapus baris dari tabel
                const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                if (row) {
                    row.remove();
                }
            } else {
                showAlert(result.message, 'error');
            }

        } catch (error) {
            showAlert(`Terjadi kesalahan jaringan: ${error.message}`, 'error');
        } finally {
            confirmButton.disabled = false;
        }
    }


    // --- EVENT LISTENERS & INITIAL SETUP ---
    
    // Tangani alert dari PHP (Redirect-After-Post) agar hilang saat diklik
    document.addEventListener('DOMContentLoaded', () => {
        const phpAlert = document.getElementById('alert-message');
        if (phpAlert) {
            // Hapus tombol close default dari PHP, karena sudah ada yang baru di HTML
            const closeButton = phpAlert.querySelector('button');
            if (closeButton) {
                closeButton.onclick = () => phpAlert.style.display = 'none';
            }
            // Auto-hide alert dari PHP setelah 5 detik
            setTimeout(() => {
                phpAlert.style.opacity = '0';
                setTimeout(() => phpAlert.style.display = 'none', 500);
            }, 5000);
        }
        
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                // Overlay sederhana di mobile (opsional: tambahkan elemen overlay)
                if (sidebar.classList.contains('active')) {
                    mainContent.style.zIndex = '900'; 
                } else {
                    mainContent.style.zIndex = 'auto';
                }
            });
        }
    });

    // Close modal ketika klik di luar modal
    window.onclick = function(event) {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        });
    }
</script>
</body>
</html>
