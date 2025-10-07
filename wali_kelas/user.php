<?php session_start(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor Wali Kelas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary-orange: #FF7F32;
            --secondary-orange: #FF9B50;
            --background-color: #F8F9FA;
            --card-bg-color: #FFFFFF;
            --text-dark: #212121;
            --text-gray: #757575;
            --border-color: #EEEEEE;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.07);
            --border-radius: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-dark);
        }

        .container {
            display: flex;
            width: 100%;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background-color: var(--card-bg-color);
            padding: 1.5rem;
            height: 100vh;
            position: sticky;
            top: 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2.5rem;
        }

        .sidebar-header .logo-icon {
            font-size: 2.2rem;
            color: var(--primary-orange);
        }

        .sidebar-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sidebar-nav a.active,
        .sidebar-nav a:hover {
            background-color: #FFF2E8;
            color: var(--primary-orange);
        }
        
        .sidebar-nav a.active {
            font-weight: 600;
        }

        .sidebar-nav a i {
            width: 22px;
            font-size: 1.1rem;
            text-align: center;
        }
        
        .sidebar-footer {
            margin-top: auto;
        }

        /* Main Content Styling */
        .main-content {
            flex: 1;
            padding: 2.5rem;
            overflow-y: auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-title h2 {
            font-size: 2rem;
            font-weight: 700;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-profile img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-orange);
        }

        .card {
            background-color: var(--card-bg-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            flex-wrap: wrap;
        }

        .card-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-orange);
            margin-bottom: 1rem;
        }

        /* Form Styling */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .form-group input, .form-control, .form-group select {
            padding: 0.9rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: white;
        }

        .form-group input:focus, .form-control:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 4px rgba(255, 127, 50, 0.2);
        }
        
        .form-group input[readonly] {
            background-color: #F5F5F5;
            cursor: not-allowed;
        }

        .btn {
            padding: 0.9rem 1.8rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-orange);
            color: white;
        }
        
        .btn-primary:hover {
            background: #e06d2d;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 127, 50, 0.3);
        }

        .form-actions {
            margin-top: 1.5rem;
            text-align: right;
        }

        /* Attendance Table Styling */
        .table-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            width: 100%;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        
        th, td {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            color: var(--text-gray);
        }
        
        td {
            font-weight: 500;
        }
        
        tr:last-child td {
            border-bottom: none;
        }

        .status-select {
            padding: 0.5rem 0.8rem;
            border-radius: 8px;
            border: 2px solid transparent;
            font-weight: 600;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.7em top 50%;
            background-size: 0.65em auto;
        }

        .status-select.hadir { background-color: #E8F5E9; color: #388E3C; border-color: #388E3C;}
        .status-select.izin { background-color: #E3F2FD; color: #1976D2; border-color: #1976D2;}
        .status-select.sakit { background-color: #FFF8E1; color: #FFA000; border-color: #FFA000;}
        .status-select.alpa { background-color: #FFCDD2; color: #D32F2F; border-color: #D32F2F;}

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--primary-orange);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
                z-index: 1000;
            }
            .sidebar.show {
                transform: translateX(0);
                box-shadow: 10px 0 30px rgba(0,0,0,0.1);
            }
            .main-content {
                padding: 1.5rem;
            }
            .menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .header-title h2 { font-size: 1.5rem; }
            .user-profile { flex-direction: column; align-items: flex-end; text-align: right; }
            .user-profile .user-info { display: none; }
            .form-grid { grid-template-columns: 1fr; }
            .table-controls { flex-direction: column; align-items: stretch; }
            #btn-simpan-absen { margin-left: 0 !important; }

            /* Responsive Table -> Card View */
            .table-wrapper { overflow-x: hidden; }
            table thead { display: none; }
            table, tbody, tr, td { display: block; width: 100%; }
            tr {
                padding: 1rem;
                border: 1px solid var(--border-color);
                border-radius: var(--border-radius);
                margin-bottom: 1rem;
            }
            td {
                padding: 0.5rem 0;
                border: none;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
            }
            td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-dark);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-school logo-icon"></i>
                <h1>Wali Kelas</h1>
            </div>
            <nav class="sidebar-nav">
                <a href="#buat-akun" class="nav-item active"><i class="fas fa-user-plus"></i> Buat Akun Siswa</a>
                <a href="#kelola-absensi" class="nav-item"><i class="fas fa-calendar-check"></i> Kelola Absensi</a>
                <a href="kelola_jadwal.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Kelola Jadwal</a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </aside>

        <main class="main-content">
            <header>
                <div class="header-title">
                    <button class="menu-toggle" id="menu-toggle"><i class="fas fa-bars"></i></button>
                    <h2>Dasbor Kelas IX-A</h2>
                </div>
                <div class="user-profile">
                    <div class="user-info">
                        <strong>Arya Pratama, S.Pd.</strong><br>
                        <small>Wali Kelas IX-A</small>
                    </div>
                    <img src="https://i.pravatar.cc/150?u=walikelasarya" alt="Foto Profil">
                </div>
            </header>

            <section id="buat-akun" class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Formulir Akun Siswa Baru</h3>
                </div>

                <?php
                    // Display session messages
                    if (isset($_SESSION['message'])) {
                        echo "<p style='color: green; margin-bottom: 1rem; padding: 1rem; background-color: #E8F5E9; border-radius: 8px;'>{$_SESSION['message']}</p>";
                        unset($_SESSION['message']);
                    }
                    if (isset($_SESSION['error'])) {
                        echo "<p style='color: red; margin-bottom: 1rem; padding: 1rem; background-color: #FFCDD2; border-radius: 8px;'>{$_SESSION['error']}</p>";
                        unset($_SESSION['error']);
                    }
                ?>

                <form action="buat_akun.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nama_lengkap">Nama Lengkap Siswa</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" placeholder="Contoh: Bunga Citra Lestari" required>
                        </div>
                        <div class="form-group">
                            <label for="nis">Nomor Induk Siswa (NIS)</label>
                            <input type="text" id="nis" name="nis" placeholder="Contoh: 250901" required>
                        </div>
                        <div class="form-group">
                            <label for="kelas">Kelas</label>
                            <input type="text" id="kelas" name="kelas" placeholder="Contoh: IX-A" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password Awal</label>
                            <input type="text" id="password" name="password" placeholder="Isi password di sini..." required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Buat Akun
                        </button>
                    </div>
                </form>
            </section>

            <section id="kelola-absensi" class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check"></i> Kelola Absensi per Mata Pelajaran</h3>
                </div>
                
                <div class="table-controls">
                    <input type="date" id="tanggal-absen" value="<?php echo date('Y-m-d'); ?>" class="form-control">
                    
                    <select id="pilih-mapel" class="form-control">
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        <option value="matematika">Matematika</option>
                        <option value="ipa">Ilmu Pengetahuan Alam (IPA)</option>
                        <option value="bahasa_indonesia">Bahasa Indonesia</option>
                        <option value="penjaskes">Olahraga (Penjaskes)</option>
                    </select>
 
                    <button class="btn btn-primary" id="btn-tampilkan-absen">
                        <i class="fas fa-list-alt"></i> Tampilkan
                    </button>
 
                    <button class="btn btn-primary" id="btn-simpan-absen" style="margin-left: auto;" onclick="alert('Absensi berhasil disimpan!')">
                        <i class="fas fa-save"></i> Simpan Absensi
                    </button>
                </div>
 
                <div id="attendance-placeholder" style="text-align: center; padding: 3rem 1rem; color: var(--text-gray);">
                    <i class="fas fa-info-circle fa-2x" style="margin-bottom: 1rem;"></i>
                    <p>Silakan pilih tanggal dan mata pelajaran, lalu klik "Tampilkan" untuk memulai absensi.</p>
                </div>
 
                <div class="table-wrapper" id="attendance-table-container" style="display: none;">
                    <h4 id="attendance-table-title" style="margin-top: 2rem; margin-bottom: 1rem; font-weight: 600;"></h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Siswa</th>
                                <th>NIS</th>
                                <th>Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody id="attendance-tbody">
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        // --- DATA SISWA (Simulasi Database) ---
        const dataSiswa = [
            { nis: "250901", nama: "Bunga Citra Lestari", kelas: "IX-A" },
            { nis: "250902", nama: "Daffa Alamsyah", kelas: "IX-A" },
            { nis: "250903", nama: "Rizky Febian", kelas: "IX-A" },
            { nis: "250904", nama: "Zahra Nuraini", kelas: "IX-A" },
            { nis: "250905", nama: "Alfi Rahmat", kelas: "IX-B" }
        ];

        // --- FUNGSI UTAMA ---

        // Fungsi untuk mengubah warna dropdown absensi
        function updateColor(selectElement) {
            selectElement.className = 'status-select ' + selectElement.value;
        }

        // Fungsi untuk memuat tabel absensi berdasarkan kelas
        function muatTabelAbsensi(mapel, kelas) {
            const tbody = document.getElementById('attendance-tbody');
            tbody.innerHTML = ''; // Kosongkan tabel sebelum diisi

            // Filter siswa berdasarkan kelas yang diinginkan
            const siswaPerKelas = dataSiswa.filter(siswa => siswa.kelas === kelas);

            if (siswaPerKelas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align: center;">Tidak ada data siswa untuk kelas ini.</td></tr>';
                return;
            }

            siswaPerKelas.forEach(siswa => {
                // Simulasi: Status default berbeda untuk setiap mapel
                let defaultStatus = 'hadir';
                if (mapel === 'penjaskes' && siswa.nis === '250904') {
                    defaultStatus = 'izin'; // Zahra izin saat olahraga
                }
                if (mapel === 'matematika' && siswa.nis === '250903') {
                    defaultStatus = 'sakit'; // Rizky sakit saat matematika
                }

                const row = `
                    <tr>
                        <td data-label="Nama">${siswa.nama}</td>
                        <td data-label="NIS">${siswa.nis}</td>
                        <td data-label="Status">
                            <select class="status-select ${defaultStatus}" onchange="updateColor(this)">
                                <option value="hadir" ${defaultStatus === 'hadir' ? 'selected' : ''}>Hadir</option>
                                <option value="sakit" ${defaultStatus === 'sakit' ? 'selected' : ''}>Sakit</option>
                                <option value="izin" ${defaultStatus === 'izin' ? 'selected' : ''}>Izin</option>
                                <option value="alpa" ${defaultStatus === 'alpa' ? 'selected' : ''}>Alpa</option>
                            </select>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        // --- EVENT LISTENERS ---

        // Event Listener untuk tombol "Tampilkan"
        document.getElementById('btn-tampilkan-absen').addEventListener('click', () => {
            const tanggal = document.getElementById('tanggal-absen').value;
            const mapelSelect = document.getElementById('pilih-mapel');
            const mapelValue = mapelSelect.value;
            const mapelText = mapelSelect.options[mapelSelect.selectedIndex].text;
            
            const placeholder = document.getElementById('attendance-placeholder');
            const tableContainer = document.getElementById('attendance-table-container');

            // Ambil nama kelas dari header dasbor
            const dashboardTitle = document.querySelector('.header-title h2').textContent;
            const kelas = dashboardTitle.replace('Dasbor Kelas ', '');

            if (!tanggal || !mapelValue) {
                alert('Harap pilih Tanggal dan Mata Pelajaran terlebih dahulu!');
                return;
            }

            // Tampilkan tabel dan sembunyikan placeholder
            placeholder.style.display = 'none';
            tableContainer.style.display = 'block';

            // Set judul tabel
            const tableTitle = document.getElementById('attendance-table-title');
            tableTitle.textContent = `Daftar Hadir: ${mapelText} - ${new Date(tanggal).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;

            // Muat data siswa ke dalam tabel berdasarkan kelas
            muatTabelAbsensi(mapelValue, kelas);
        });

        // Navigasi Smooth Scrolling & Active State
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId.startsWith('#')) {
                    document.querySelector(targetId).scrollIntoView({ behavior: 'smooth' });
                    navItems.forEach(nav => nav.classList.remove('active'));
                    this.classList.add('active');
                } else {
                    window.location.href = targetId;
                }
            });
        });

        // Toggle Sidebar di Mobile
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');

        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });

        mainContent.addEventListener('click', () => {
            if (sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>