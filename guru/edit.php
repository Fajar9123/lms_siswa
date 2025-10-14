<?php
// edit.php
date_default_timezone_set('Asia/Jakarta');

// ====================================================================
// A. KONFIGURASI DAN KONEKSI
// ====================================================================

$host = 'localhost';
$user = 'root';
$password = ''; 
$dbname = 'smkjt1'; 
const DB_TABLE = 'akunsiswa'; 
const INDEX_URL = 'index.php'; // Halaman daftar siswa

function connectDB($host, $user, $password, $dbname) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}
$pdo = connectDB($host, $user, $password, $dbname);

// Data Referensi
$jurusan_map = [
    'MP' => 'Manajemen Perkantoran', 
    'TKJ' => 'Teknik Komputer Jaringan',
    'BR' => 'Bisnis Ritel',
    'BD' => 'Bisnis Digital',
    'AK' => 'Akuntansi'
];
$valid_jurusan = array_keys($jurusan_map);

// Inisialisasi
$message_type = ''; 
$message = '';
$errors = [];
$siswa_data = null;

// Tentukan NIS target dari GET atau POST
$nis_target = trim($_GET['nis'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nis_original'])) {
    $nis_target = trim($_POST['nis_original']);
}

// ====================================================================
// B. LOGIKA POST (UPDATE)
// ====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    
    $nis_original = trim($_POST['nis_original']);
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $kelas = substr(trim($_POST['kelas'] ?? ''), 0, 100); 
    $jurusan = $_POST['jurusan'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    
    // --- Validasi Input ---
    if (empty($nama_lengkap)) $errors['nama_lengkap'] = "Nama lengkap wajib diisi.";
    if (empty($kelas)) $errors['kelas'] = "Nama kelas wajib diisi.";
    if (!in_array($jurusan, $valid_jurusan)) $errors['jurusan'] = "Pilihan jurusan tidak valid.";
    if (!empty($password_baru) && strlen($password_baru) < 6) $errors['password_baru'] = "Password baru minimal 6 karakter.";

    if (empty($errors)) {
        try {
            $sql = "UPDATE " . DB_TABLE . " SET nama_lengkap = ?, kelas = ?, jurusan = ?";
            $params = [$nama_lengkap, $kelas, $jurusan];
            
            if (!empty($password_baru)) {
                $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $sql .= ", password_hash = ?";
                $params[] = $password_hash;
            }
            
            $sql .= " WHERE nis = ?";
            $params[] = $nis_original;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $message_type = 'success';
            $message = "✅ Data siswa **$nis_original** berhasil diperbarui!";
            
            // Redirect untuk menampilkan pesan sukses dan memuat data terbaru
            $redirect_url = "edit.php?nis=" . $nis_original . "&msg_type=success&msg=" . urlencode($message);
            header("Location: " . $redirect_url);
            exit();

        } catch (PDOException $e) {
            $message_type = 'error';
            $message = "❌ Gagal memperbarui data: Terjadi kesalahan database.";
            // Simpan data POST agar form tidak kosong jika ada error database
            $siswa_data = ['nis' => $nis_original, 'nama_lengkap' => $nama_lengkap, 'kelas' => $kelas, 'jurusan' => $jurusan];
        }
    } else {
        $message_type = 'error';
        $message = "⚠️ Silakan periksa kembali data. Terdapat kesalahan validasi.";
        // Simpan data POST untuk mempertahankan input
        $siswa_data = ['nis' => $nis_original, 'nama_lengkap' => $nama_lengkap, 'kelas' => $kelas, 'jurusan' => $jurusan];
    }
}

// ====================================================================
// C. LOGIKA LOAD DATA (GET & Setelah POST dengan Error)
// ====================================================================

// Tampilkan pesan setelah redirect
if (isset($_GET['msg_type']) && isset($_GET['msg'])) {
    $message_type = $_GET['msg_type'];
    $message = $_GET['msg'];
}

if (!$siswa_data && $pdo) { // Hanya load dari DB jika belum dimuat (yaitu bukan setelah error validasi/database)
    if (empty($nis_target) || !preg_match('/^[0-9]+$/', $nis_target)) {
        $message_type = 'error';
        $message = "❌ Parameter NIS tidak ditemukan atau tidak valid.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT nis, nama_lengkap, kelas, jurusan FROM " . DB_TABLE . " WHERE nis = ?");
            $stmt->execute([$nis_target]);
            $siswa_data = $stmt->fetch();
            
            if (!$siswa_data) {
                $message_type = 'error';
                $message = "❌ Data siswa dengan NIS **$nis_target** tidak ditemukan.";
            }
        } catch (PDOException $e) {
            $message_type = 'error';
            $message = "❌ Gagal memuat data siswa.";
        }
    }
} else if (!$pdo) {
    $message_type = 'error';
    $message = "🚨 Koneksi database gagal! Tidak dapat memuat atau menyimpan data.";
}

// Siapkan data untuk ditampilkan di form (escape HTML)
$data_form = [
    'nis' => htmlspecialchars($siswa_data['nis'] ?? ''),
    'nama_lengkap' => htmlspecialchars($siswa_data['nama_lengkap'] ?? ''),
    'kelas' => htmlspecialchars($siswa_data['kelas'] ?? ''),
    'jurusan' => htmlspecialchars($siswa_data['jurusan'] ?? '')
];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Akun Siswa | <?php echo $data_form['nis']; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        :root {
            --primary-color: #3498db; /* Biru untuk edit */
            --primary-dark: #2980b9;    
            --primary-light: #e6f0f6;   
            --bg-page: #f9fbf9;         
            --bg-container: #ffffff;
            --text-dark: #344754;       
            --text-light: #6c757d;      
            --error-color: #dc3545; 
            --border-color: #e9ecef; 
            --input-border: #ced4da;
            --box-shadow-light: 0 4px 20px rgba(0, 0, 0, 0.05); 
            --border-radius-md: 8px; 
            --border-radius-lg: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-page); color: var(--text-dark); display: flex; flex-direction: column; min-height: 100vh; line-height: 1.6; }
        .main-content { flex-grow: 1; padding: 30px; width: 100%; max-width: 900px; margin: 0 auto; }
        .container { width: 100%; background: var(--bg-container); padding: 30px; border-radius: var(--border-radius-lg); box-shadow: var(--box-shadow-light); border: 1px solid var(--border-color); margin-bottom: 30px; }
        .page-title { font-size: 2em; font-weight: 700; display: flex; align-items: center; gap: 12px; border-bottom: 4px solid var(--primary-color); padding-bottom: 0.75rem; margin-bottom: 2rem; color: var(--primary-dark); }
        .page-title i { color: var(--primary-color); font-size: 1.1em; }
        .alert { padding: 18px 25px; margin-bottom: 30px; border-radius: var(--border-radius-md); font-weight: 500; border: 1px solid; display: flex; align-items: center; gap: 15px; }
        .alert-success { background-color: var(--primary-light); border-color: #b1dfbb; color: #1e7e34; }
        .alert-error { background-color: #f8d7da; border-color: #f5c6cb; color: var(--error-color); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        #group-nama, #group-password-baru, #group-nis-edit { grid-column: 1 / -1; }
        .kelas-jurusan-wrapper { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; gap: 0 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 8px; color: var(--text-dark); }
        .form-group i { margin-right: 5px; color: var(--primary-color); }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid var(--input-border); border-radius: var(--border-radius-md); transition: border-color 0.3s; }
        .form-control:focus { border-color: var(--primary-dark); box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25); outline: none; }
        .form-group.has-error .form-control { border-color: var(--error-color); }
        .form-group.has-error .error-message { color: var(--error-color); font-size: 0.85em; margin-top: 5px; font-weight: 400; }
        .input-group { display: flex; }
        .input-group .form-control { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .toggle-password { padding: 0 15px; background-color: var(--border-color); border: 1px solid var(--input-border); border-left: 0; border-top-right-radius: var(--border-radius-md); border-bottom-right-radius: var(--border-radius-md); cursor: pointer; color: var(--text-light); }
        .form-actions { grid-column: 1 / -1; margin-top: 40px; display: flex; gap: 15px; justify-content: flex-end; padding-top: 25px; border-top: 1px solid var(--border-color); }
        .btn { padding: 10px 25px; border: none; border-radius: var(--border-radius-md); font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-submit { background-color: var(--primary-color); color: white; }
        .btn-submit:hover { background-color: var(--primary-dark); transform: translateY(-2px); }
        .btn-back { background-color: var(--text-light); color: white; }
        .btn-back:hover { background-color: var(--text-dark); }
        footer { text-align: center; padding: 25px 30px; background-color: var(--primary-dark); color: rgba(255, 255, 255, 0.95); font-size: 0.9em; width: 100%; margin-top: auto; }
        @media (max-width: 600px) { .kelas-jurusan-wrapper { grid-template-columns: 1fr; } .form-actions { flex-direction: column; } .btn { width: 100%; } }
    </style>
</head>
<body>

    <div class="page-content-wrapper">
        <main class="main-content">
            
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Akun Siswa: NIS **<?php echo $data_form['nis']; ?>**
            </h1>

            <div class="container">

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                    <i class="fas <?php echo ($message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'); ?>"></i>
                    <div><?php echo $message; ?></div>
                </div>
                <?php endif; ?>

                <?php if ($siswa_data): ?>
                <form action="edit.php" method="POST">
                    
                    <input type="hidden" name="nis_original" value="<?php echo $data_form['nis']; ?>">
                    
                    <div class="form-grid">
                        
                        <div class="form-group" id="group-nis-edit">
                            <label for="nis_display"><i class="fas fa-id-card-alt"></i> NIS / Username</label>
                            <input type="text" id="nis_display" value="<?php echo $data_form['nis']; ?>" class="form-control" disabled>
                            <small style="color: var(--text-light); display: block; margin-top: 5px;">*NIS dan Username tidak dapat diubah.</small>
                        </div>

                        <div class="form-group <?php echo isset($errors['nama_lengkap']) ? 'has-error' : ''; ?>" id="group-nama">
                            <label for="nama_lengkap"><i class="fas fa-user"></i> Nama Lengkap Siswa</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control <?php echo isset($errors['nama_lengkap']) ? 'is-invalid' : ''; ?>" value="<?php echo $data_form['nama_lengkap']; ?>" required>
                            <?php if (isset($errors['nama_lengkap'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['nama_lengkap']); ?></div><?php endif; ?>
                        </div>
                        
                        <div class="kelas-jurusan-wrapper">
                            
                            <div class="form-group <?php echo isset($errors['kelas']) ? 'has-error' : ''; ?>">
                                <label for="kelas"><i class="fas fa-school"></i> Nama Kelas Lengkap</label>
                                <input 
                                    type="text" 
                                    id="kelas" 
                                    name="kelas" 
                                    class="form-control <?php echo isset($errors['kelas']) ? 'is-invalid' : ''; ?>" 
                                    value="<?php echo $data_form['kelas']; ?>" 
                                    required
                                    maxlength="100"
                                >
                                <?php if (isset($errors['kelas'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['kelas']); ?></div><?php endif; ?>
                            </div>
                            
                            <div class="form-group <?php echo isset($errors['jurusan']) ? 'has-error' : ''; ?>">
                                <label for="jurusan"><i class="fas fa-cogs"></i> Jurusan</label>
                                <select id="jurusan" name="jurusan" class="form-control <?php echo isset($errors['jurusan']) ? 'is-invalid' : ''; ?>" required>
                                    <option value="" disabled>Pilih Jurusan</option>
                                    <?php foreach ($jurusan_map as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($data_form['jurusan'] == $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['jurusan'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['jurusan']); ?></div><?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group <?php echo isset($errors['password_baru']) ? 'has-error' : ''; ?>" id="group-password-baru">
                            <label for="password_baru"><i class="fas fa-unlock"></i> Password Baru (Kosongkan jika tidak ingin diubah)</label>
                            <div class="input-group">
                                <input type="password" id="password_baru" name="password_baru" class="form-control <?php echo isset($errors['password_baru']) ? 'is-invalid' : ''; ?>" placeholder="Minimal 6 karakter" minlength="6">
                                <button type="button" id="togglePassword" class="toggle-password" title="Tampilkan/Sembunyikan Password"><i class="fas fa-eye"></i></button>
                            </div>
                            <?php if (isset($errors['password_baru'])): ?><div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['password_baru']); ?></div><?php endif; ?>
                        </div>

                    </div>
                    
                    <div class="form-actions">
                        <a href="<?php echo INDEX_URL; ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Daftar</a>
                        <button type="submit" class="btn btn-submit" name="submit"><i class="fas fa-save"></i> Perbarui Data</button>
                    </div>

                </form>
                <?php endif; ?>

            </div>
        </main>
        
        <footer>
            <p>&copy; 2024 SMK JTI Admin Panel | Halaman Edit.</p>
        </footer>
    </div>
    
    <script>
        // Fungsi Show/Hide Password
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const passwordField = document.getElementById('password_baru');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>

</body>
</html>