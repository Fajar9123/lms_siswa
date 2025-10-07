<?php
session_start();
include 'koneksi.php'; // Memanggil file koneksi
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor Wali Kelas - Kelola Jadwal</title>
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

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-dark);
        }

        .container {
            display: flex;
            width: 100%;
        }

        /* Sidebar */
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
            z-index: 1000;
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
        
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 1rem;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sidebar-footer a:hover {
            background-color: #FFCDD2;
            color: #D32F2F;
        }

        /* Main Content */
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
            flex-wrap: wrap;
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
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-orange);
        }

        /* Form Styling */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            width: 100%;
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
            justify-content: center;
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

        /* Table */
        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            margin-top: 1rem;
        }
        
        th, td {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
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

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--primary-orange);
        }
        
        .btn-danger {
            background-color: #D32F2F;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.8rem;
            text-decoration: none;
        }
        
        .btn-danger:hover {
            background-color: #B71C1C;
        }
        
        /* Alert Box Styles */
        .alert-box {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background-color: #E8F5E9;
            color: #388E3C;
            border-color: #C8E6C9;
        }
        
        .alert-error {
            background-color: #FFCDD2;
            color: #D32F2F;
            border-color: #FFB3B7;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
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
                <a href="#kelola-jadwal" class="nav-item active"><i class="fas fa-calendar-alt"></i> Kelola Jadwal</a>
            </nav>
            <div class="sidebar-footer">
                <a href="#"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </aside>

        <main class="main-content">
            <header>
                <div class="header-title">
                    <button class="menu-toggle" id="menu-toggle" aria-label="Buka Menu Navigasi"><i class="fas fa-bars"></i></button>
                    <h2>Dasbor Jadwal Pelajaran</h2>
                </div>
                <div class="user-profile">
                    <div class="user-info">
                        <strong>Arya Pratama, S.Pd.</strong><br>
                        <small>Administrator</small>
                    </div>
                    <img src="https://i.pravatar.cc/150?u=walikelasarya" alt="Foto Profil">
                </div>
            </header>
            
            <?php
            if (isset($_SESSION['message'])) {
                echo "<div class='alert-box alert-success'>{$_SESSION['message']}</div>";
                unset($_SESSION['message']);
            }
            if (isset($_SESSION['error'])) {
                echo "<div class='alert-box alert-error'>{$_SESSION['error']}</div>";
                unset($_SESSION['error']);
            }
            ?>

            <section id="kelola-jadwal" class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Kelola Jadwal Pelajaran</h3>
                </div>
                <form action="proses_jadwal.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="kelas_jadwal">Kelas</label>
                            <input type="text" id="kelas_jadwal" name="kelas" placeholder="Contoh: IX-A" required>
                        </div>
                        <div class="form-group">
                            <label for="hari">Hari</label>
                            <select id="hari" name="hari" class="form-control" required>
                                <option value="">-- Pilih Hari --</option>
                                <option value="Senin">Senin</option>
                                <option value="Selasa">Selasa</option>
                                <option value="Rabu">Rabu</option>
                                <option value="Kamis">Kamis</option>
                                <option value="Jumat">Jumat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="mata_pelajaran">Mata Pelajaran</label>
                            <input type="text" id="mata_pelajaran" name="mata_pelajaran" placeholder="Contoh: Matematika" required>
                        </div>
                        <div class="form-group">
                            <label for="guru">Nama Guru</label>
                            <input type="text" id="guru" name="guru" placeholder="Contoh: Budi Santoso, S.Pd." required>
                        </div>
                        <div class="form-group">
                            <label for="jam_mulai">Jam Mulai</label>
                            <input type="time" id="jam_mulai" name="jam_mulai" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="jam_selesai">Jam Selesai</label>
                            <input type="time" id="jam_selesai" name="jam_selesai" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Tambah ke Jadwal
                        </button>
                    </div>
                </form>
                
                <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--border-color);">

                <div id="jadwal-tampil">
                    <h4>Jadwal Semua Kelas</h4>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kelas</th>
                                    <th>Hari</th>
                                    <th>Jam</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Nama Guru</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // PERUBAHAN 3: Query diperbarui untuk mengambil nama_guru
                                $query = "SELECT 
                                            j.id, 
                                            j.hari, 
                                            j.mata_pelajaran, 
                                            j.nama_guru, 
                                            j.jam_mulai, 
                                            j.jam_selesai, 
                                            k.nama_kelas
                                          FROM jadwal j
                                          JOIN kelas k ON j.kelas_id = k.id
                                          ORDER BY k.nama_kelas, FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'), j.jam_mulai";
                                
                                $hasil = mysqli_query($koneksi, $query);

                                if (mysqli_num_rows($hasil) > 0) {
                                    while ($baris = mysqli_fetch_assoc($hasil)) {
                                        echo "<tr>";
                                        echo "<td data-label='Kelas'>" . htmlspecialchars($baris['nama_kelas']) . "</td>";
                                        echo "<td data-label='Hari'>" . htmlspecialchars($baris['hari']) . "</td>";
                                        echo "<td data-label='Jam'>" . htmlspecialchars(date('H:i', strtotime($baris['jam_mulai']))) . " - " . htmlspecialchars(date('H:i', strtotime($baris['jam_selesai']))) . "</td>";
                                        echo "<td data-label='Mata Pelajaran'>" . htmlspecialchars($baris['mata_pelajaran']) . "</td>";
                                        // PERUBAHAN 4: Menampilkan data nama_guru
                                        echo "<td data-label='Nama Guru'>" . htmlspecialchars($baris['nama_guru']) . "</td>";
                                        echo "<td data-label='Aksi'>";
                                        echo "<a href='hapus_jadwal.php?id=" . $baris['id'] . "' class='btn btn-danger' onclick='return confirm(\"Apakah Anda yakin ingin menghapus jadwal ini?\")'><i class='fas fa-trash'></i></a>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    // PERUBAHAN 5: Colspan diubah menjadi 6
                                    echo "<tr><td colspan='6' style='text-align:center;'>Belum ada jadwal yang ditambahkan.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Script untuk Toggle Sidebar
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const menuToggle = document.getElementById('menu-toggle');

            if (menuToggle) {
                menuToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    sidebar.classList.toggle('show');
                });
            }

            if (mainContent) {
                 mainContent.addEventListener('click', () => {
                    if (sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                    }
                });
            }
        });
    </script>
</body>
</html>