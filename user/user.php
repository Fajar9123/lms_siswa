<?php
// Memulai session di baris paling atas
session_start();
// Memanggil file koneksi database
include 'koneksi.php';

//=====================================================
// 🔑 CEK OTENTIKASI SISWA
//=====================================================
if (!isset($_SESSION['nis'])) {
    header("Location: login.php");
    exit(); 
}

//=====================================================
// 👍 AMBIL DATA SISWA DARI SESSION
//=====================================================
$nama_siswa = htmlspecialchars($_SESSION['nama_lengkap']);
$nis_siswa = htmlspecialchars($_SESSION['nis']);
$kelas_siswa = htmlspecialchars($_SESSION['kelas']);

//=====================================================
// 🚀 AMBIL DATA JADWAL DARI DATABASE
//=====================================================
$jadwal_per_hari = [
    'Senin' => [], 'Selasa' => [], 'Rabu' => [], 'Kamis' => [], 'Jumat' => []
];

$query_jadwal = "SELECT j.id, j.hari, j.mata_pelajaran, j.nama_guru, j.jam_mulai, j.jam_selesai
                 FROM jadwal j
                 JOIN kelas k ON j.kelas_id = k.id
                 WHERE k.nama_kelas = ?
                 ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'), j.jam_mulai";

$stmt = mysqli_prepare($koneksi, $query_jadwal);
mysqli_stmt_bind_param($stmt, "s", $kelas_siswa);
mysqli_stmt_execute($stmt);
$hasil_jadwal = mysqli_stmt_get_result($stmt);

if ($hasil_jadwal) {
    while ($baris = mysqli_fetch_assoc($hasil_jadwal)) {
        if (array_key_exists($baris['hari'], $jadwal_per_hari)) {
            $jadwal_per_hari[$baris['hari']][] = $baris;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - <?php echo $nama_siswa; ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-orange: #FF7F32;
            --secondary-orange: #FF9B50;
            --dark-text: #212121;
            --body-text: #424242;
            --gray-text: #757575;
            --background: #F4F7FE;
            --sidebar-bg: #FFFFFF;
            --card-bg: #FFFFFF;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.07);
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--background);
            color: var(--body-text);
            display: flex;
        }
        
        .body-no-scroll {
            overflow: hidden;
        }

        .dashboard-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* --- Sidebar --- */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2.5rem;
        }

        .sidebar-logo i {
            font-size: 2rem;
            color: var(--primary-orange);
        }

        .sidebar-logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-text);
        }

        .sidebar-menu {
            list-style: none;
            flex-grow: 1;
            padding: 0;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 1rem;
            margin: 0.3rem 0;
            border-radius: 8px;
            text-decoration: none;
            color: var(--gray-text);
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .sidebar-menu li a:hover,
        .sidebar-menu li a.active {
            background-color: #FFF2E8;
            color: var(--primary-orange);
        }
        
        .sidebar-menu li a i {
            font-size: 1.2rem;
            width: 22px;
            text-align: center;
        }

        /* Gaya untuk Submenu Dropdown */
        .sidebar-menu .submenu {
            list-style: none;
            padding-left: 2.5rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
        }
        
        .sidebar-menu .has-submenu.open .submenu {
            max-height: 200px;
        }
        
        .sidebar-menu .submenu a {
            padding-top: 0.7rem;
            padding-bottom: 0.7rem;
            font-size: 0.9rem;
        }
        
        .sidebar-menu .has-submenu .sub-icon {
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        .sidebar-menu .has-submenu.open .sub-icon {
            transform: rotate(180deg);
        }
        
        .sidebar-logout {
            margin-top: auto;
        }

        /* --- Konten Utama --- */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            min-width: 0;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header-title {
            display: flex;
            align-items: center;
        }

        .header-title h2 {
            font-size: 1.8rem;
            color: var(--dark-text);
            font-weight: 600;
        }

        .mobile-menu-btn {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            margin-right: 1rem;
            color: var(--dark-text);
        }
        
        .card {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .profile-card {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            border-left: 5px solid var(--primary-orange);
        }

        .profile-icon {
            flex-shrink: 0;
            width: 70px;
            height: 70px;
            background-color: #FFF2E8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--primary-orange);
        }

        .profile-details h3 {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--dark-text);
        }

        .profile-details p {
            font-size: 1rem;
            color: var(--gray-text);
        }

        /* --- Jadwal Pelajaran --- */
        .schedule-wrapper {
             grid-column: 1 / -1;
        }
        
        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .schedule-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-text);
        }

        .slider-nav-buttons {
            display: flex;
            gap: 10px;
        }

        .slider-btn {
            background-color: var(--card-bg);
            border: 1px solid #e0e0e0;
            color: var(--gray-text);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .slider-btn:hover {
            background-color: var(--primary-orange);
            color: white;
            border-color: var(--primary-orange);
        }

        .schedule-slider {
            position: relative;
            overflow: hidden;
        }
        
        .schedule-track {
            display: flex;
            transition: transform 0.5s cubic-bezier(0.25, 1, 0.5, 1);
            gap: 1.5rem;
        }
        
        .schedule-day-card {
            min-width: 320px;
            flex-shrink: 0;
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .schedule-day-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
            font-size: 1.2rem;
            color: var(--dark-text);
            font-weight: 600;
        }
        
        .schedule-day-header i {
            color: var(--primary-orange);
        }

        .schedule-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .schedule-item {
            display: grid;
            grid-template-areas: 
                "time title"
                "time teacher"
                "time button";
            grid-template-columns: 80px 1fr;
            gap: 5px 10px;
            padding: 1rem;
            background-color: #F8F9FA;
            border-left: 4px solid var(--primary-orange);
            border-radius: 6px;
        }
        
        .schedule-item-time {
            grid-area: time;
            font-size: 0.9rem;
            color: var(--gray-text);
        }

        .schedule-item-title {
            grid-area: title;
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-text);
        }

        .schedule-item-teacher {
            grid-area: teacher;
            font-size: 0.9rem;
            color: var(--gray-text);
        }

        .btn-masuk-kelas {
            grid-area: button;
            background-color: var(--primary-orange);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s ease;
            margin-top: 10px;
            justify-self: start;
        }
        
        .btn-masuk-kelas:hover {
            background-color: #e06d2d;
        }
        
        .empty-schedule {
            text-align: center;
            color: var(--gray-text);
            padding: 2rem 0;
        }
        
        /* --- Responsif --- */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999; 
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100%;
                transform: translateX(-100%);
                z-index: 1000;
            }
            .sidebar.show { transform: translateX(0); }
            .main-content { padding: 1.5rem; }
            .mobile-menu-btn { display: block; }
        }

        @media (max-width: 768px) {
            .slider-nav-buttons {
                display: none;
            }
            .schedule-slider {
                overflow: visible;
            }
            .schedule-track {
                display: block;
            }
            .schedule-day-card {
                min-width: 100%;
                margin-bottom: 1.5rem;
            }
            .profile-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>

    <div class="dashboard-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <i class="fas fa-graduation-cap"></i>
                <h1>EduLMS</h1>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="#"><i class="fas fa-book-open"></i> Materi Saya</a></li>
                <li><a href="#"><i class="fas fa-users"></i> Kelas</a></li>
                
                <li class="has-submenu">
                    <a class="dropdown-toggle">
                        <i class="fas fa-file-alt"></i> Ujian <i class="fas fa-chevron-down sub-icon"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="#"><i class="fas fa-pencil-alt fa-fw"></i> Ujian Harian</a></li>
                        <li><a href="#"><i class="fas fa-file-signature fa-fw"></i> Ujian Tengah Semester</a></li>
                        <li><a href="#"><i class="fas fa-graduation-cap fa-fw"></i> Ujian Akhir Semester</a></li>
                    </ul>
                </li>
                
                <li><a href="#"><i class="fas fa-award"></i> Sertifikat</a></li>
                <li><a href="#"><i class="fas fa-user-circle"></i> Profil</a></li>
            </ul>
            <div class="sidebar-logout">
                <ul class="sidebar-menu">
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                </ul>
            </div>
        </aside>

        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-title">
                    <button class="mobile-menu-btn" id="mobile-menu-btn"><i class="fas fa-bars"></i></button>
                    <h2>Dashboard Siswa</h2>
                </div>
            </header>

            <div class="card profile-card">
                 <div class="profile-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="profile-details">
                    <h3><?php echo $nama_siswa; ?></h3>
                    <p>
                        <span>NIS: <?php echo $nis_siswa; ?></span> |
                        <span>Kelas: <?php echo $kelas_siswa; ?></span>
                    </p>
                </div>
            </div>

            <div class="schedule-wrapper">
                <div class="schedule-header">
                    <h3>Jadwal Pelajaran Kelas <?php echo $kelas_siswa; ?></h3>
                    <div class="slider-nav-buttons">
                        <button class="slider-btn" id="prevBtn"><i class="fas fa-chevron-left"></i></button>
                        <button class="slider-btn" id="nextBtn"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
                <div class="schedule-slider">
                    <div class="schedule-track" id="sliderTrack">
                        <?php foreach ($jadwal_per_hari as $hari => $jadwals) : ?>
                            <div class="schedule-day-card">
                                <div class="schedule-day-header">
                                    <i class="fas fa-calendar-day"></i>
                                    <h4><?php echo $hari; ?></h4>
                                </div>
                                <ul class="schedule-list">
                                    <?php if (empty($jadwals)) : ?>
                                        <li class="empty-schedule">Tidak ada jadwal untuk hari ini.</li>
                                    <?php else : ?>
                                        <?php foreach ($jadwals as $jadwal) : ?>
                                            <li class="schedule-item">
                                                <div class="schedule-item-time"><?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?></div>
                                                <div class="schedule-item-title"><?php echo htmlspecialchars($jadwal['mata_pelajaran']); ?></div>
                                                <div class="schedule-item-teacher"><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($jadwal['nama_guru']); ?></div>
                                                <a href="kelas.php?id=<?php echo $jadwal['id']; ?>" class="btn-masuk-kelas">Masuk Kelas <i class="fas fa-arrow-right"></i></a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- LOGIKA UNTUK MENU MOBILE & OVERLAY ---
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const body = document.body;

        const toggleMenu = () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('active');
            body.classList.toggle('body-no-scroll');
        };
        
        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', toggleMenu);
        }

        if (overlay) {
            overlay.addEventListener('click', toggleMenu);
        }

        // --- LOGIKA UNTUK SUBMENU DROPDOWN ---
        const submenuToggles = document.querySelectorAll('.dropdown-toggle');
        submenuToggles.forEach(function(toggle) {
            toggle.addEventListener('click', function(event) {
                event.preventDefault();
                const parentLi = this.parentElement;
                parentLi.classList.toggle('open');
            });
        });

        // --- FUNGSI SLIDER JADWAL (HANYA UNTUK DESKTOP) ---
        const initScheduleSlider = () => {
            const sliderTrack = document.getElementById('sliderTrack');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            if (window.innerWidth <= 768 || !sliderTrack || !prevBtn || !nextBtn) {
                if (sliderTrack) sliderTrack.style.transform = 'translateX(0)';
                return; 
            }

            const slides = Array.from(sliderTrack.children);
            if (slides.length > 0) {
                const totalSlides = slides.length;
                let currentIndex = 0;

                const updateSlider = () => {
                    const slideWidth = slides[0].getBoundingClientRect().width;
                    const gap = parseFloat(getComputedStyle(sliderTrack).gap);
                    sliderTrack.style.transform = `translateX(-${currentIndex * (slideWidth + gap)}px)`;
                    prevBtn.disabled = currentIndex === 0;
                    nextBtn.disabled = totalSlides <= 1 || currentIndex >= totalSlides - 1;
                };

                nextBtn.addEventListener('click', () => {
                    if (currentIndex < totalSlides - 1) {
                        currentIndex++;
                        updateSlider();
                    }
                });

                prevBtn.addEventListener('click', () => {
                    if (currentIndex > 0) {
                        currentIndex--;
                        updateSlider();
                    }
                });
                
                updateSlider();
            }
        };

        initScheduleSlider();
        window.addEventListener('resize', initScheduleSlider);
    });
    </script>
</body>
</html>