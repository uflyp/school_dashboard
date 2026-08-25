<?php
require_once 'config.php';

// Fetch Active Running Text
$stmtRt = $pdo->query("SELECT * FROM running_text WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$runningText = $stmtRt->fetch();

// Fetch Active Countdown Event
$stmtCd = $pdo->query("SELECT * FROM events WHERE is_countdown = 1 AND is_active = 1 ORDER BY id DESC LIMIT 1");
$countdownEvent = $stmtCd->fetch();

// Fetch Popup Announcement Event
$stmtPop = $pdo->query("SELECT * FROM events WHERE is_popup = 1 AND is_active = 1 ORDER BY id DESC LIMIT 1");
$popupEvent = $stmtPop->fetch();

// Fetch Berita
$beritaList = $pdo->query("SELECT * FROM berita ORDER BY id DESC LIMIT 4")->fetchAll();

// Fetch Events
$eventList = $pdo->query("SELECT * FROM events ORDER BY event_date ASC LIMIT 4")->fetchAll();

// Stats
$totalSiswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$totalGuru = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(get_setting('school_name')); ?> - Portal Resmi</title>
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS Output -->
    <link rel="stylesheet" href="assets/css/output.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Theme Manager (load early to prevent flash) -->
    <script src="assets/js/theme.js"></script>
</head>
<body class="dark:bg-slate-950 bg-slate-50 dark:text-slate-100 text-slate-800 font-sans antialiased selection:bg-indigo-500 selection:text-white min-h-screen flex flex-col transition-colors duration-300">

    <!-- 📌 Premium Sticky Glassmorphism Homepage Navbar (76px Height with Scroll Shrink) -->
    <header id="main-navbar" class="sticky top-0 z-50 h-[76px] transition-all duration-300 dark:bg-slate-950/85 bg-white/85 backdrop-blur-2xl border-b dark:border-indigo-500/15 border-slate-200/80 shadow-lg">
        <div class="max-w-7xl mx-auto h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            
            <!-- Logo & Brand (Left) -->
            <a href="index.php" class="flex items-center gap-3.5 group shrink-0">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-600 via-purple-500 to-emerald-400 p-0.5 shadow-lg shadow-indigo-500/30 group-hover:scale-105 transition-transform duration-300">
                    <div class="w-full h-full dark:bg-slate-950 bg-indigo-50 rounded-[14px] flex items-center justify-center">
                        <i class="fa-solid fa-graduation-cap text-indigo-500 text-xl"></i>
                    </div>
                </div>
                <div>
                    <h1 class="text-base sm:text-lg font-extrabold dark:text-white text-slate-900 leading-tight tracking-tight group-hover:text-indigo-400 transition-colors">
                        <?= htmlspecialchars(get_setting('school_name')); ?>
                    </h1>
                    <span class="text-[9px] text-indigo-400 font-extrabold tracking-widest uppercase block">
                        <?= htmlspecialchars(get_setting('school_tagline')); ?>
                    </span>
                </div>
            </a>

            <!-- Centered Navigation Links with Underline Hover Animation (Middle) -->
            <nav class="hidden lg:flex items-center gap-8 text-sm font-semibold">
                <a href="#home" class="relative py-1.5 dark:text-white text-slate-900 font-bold group">
                    <span>Beranda</span>
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full scale-x-100 transition-transform origin-left"></span>
                </a>
                <a href="#berita" class="relative py-1.5 text-slate-400 hover:text-indigo-400 transition-colors group">
                    <span>Berita Sekolah</span>
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></span>
                </a>
                <a href="#agenda" class="relative py-1.5 text-slate-400 hover:text-indigo-400 transition-colors group">
                    <span>Agenda Event</span>
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></span>
                </a>
                <a href="ppdb.php" class="relative py-1.5 text-slate-400 hover:text-indigo-400 transition-colors group">
                    <span class="flex items-center gap-1.5">
                        <span>PPDB 2026</span>
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                    </span>
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></span>
                </a>
                <a href="#informasi" class="relative py-1.5 text-slate-400 hover:text-indigo-400 transition-colors group">
                    <span>Informasi</span>
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></span>
                </a>
                <a href="#kontak" class="relative py-1.5 text-slate-400 hover:text-indigo-400 transition-colors group">
                    <span>Kontak</span>
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></span>
                </a>
            </nav>

            <!-- Right Utilities & Premium "Masuk Portal" Button -->
            <div class="flex items-center gap-3.5">
                <!-- Real-time Digital Clock -->
                <div class="digital-clock-display hidden xl:block"></div>

                <!-- Dark / Light Mode Toggle -->
                <button onclick="toggleTheme()" title="Ganti ke Mode Terang" class="theme-toggle-btn w-9 h-9 rounded-xl dark:bg-slate-900 bg-slate-100 dark:text-amber-400 text-slate-600 border dark:border-slate-800 border-slate-200 flex items-center justify-center hover:ring-2 hover:ring-indigo-500 transition-all shrink-0">
                    <i class="fa-solid theme-icon fa-moon text-sm"></i>
                </button>

                <!-- Premium "Masuk Portal" CTA Button -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-xs font-extrabold px-5 py-2.5 rounded-xl shadow-lg shadow-emerald-500/25 hover:scale-[1.03] transition-all">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="inline-block text-center px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-xs font-extrabold shadow-xl shadow-indigo-600/35 hover:shadow-indigo-600/50 hover:scale-[1.03] active:scale-[0.98] transition-all duration-300" style="color:#ffffff !important;">
                        Masuk Portal
                    </a>
                <?php endif; ?>

                <!-- Mobile Hamburger Button -->
                <button id="mobile-menu-btn" class="lg:hidden w-10 h-10 rounded-xl dark:bg-slate-900 bg-slate-100 dark:text-slate-200 text-slate-800 border dark:border-slate-800 border-slate-300 flex items-center justify-center">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
            </div>

        </div>

        <!-- 📢 Running Announcement Marquee Bar (Under Top Navbar) -->
        <?php if ($runningText): ?>
            <div class="bg-indigo-950/90 backdrop-blur-md border-t border-b border-indigo-500/20 py-1 px-4 text-xs font-semibold text-indigo-200 overflow-hidden flex items-center gap-3 shadow-inner">
                <span class="shrink-0 px-2 py-0.5 rounded bg-indigo-600 text-white text-[9px] font-extrabold uppercase tracking-wider animate-pulse">
                    INFO PENTING
                </span>
                <marquee behavior="scroll" direction="left" class="font-medium text-slate-200">
                    <?= htmlspecialchars($runningText['content']); ?>
                </marquee>
            </div>
        <?php endif; ?>

        <!-- Mobile Dropdown Navigation Drawer -->
        <div id="mobile-dropdown" class="lg:hidden hidden bg-slate-950/95 backdrop-blur-2xl border-b border-slate-800 p-6 space-y-3 shadow-2xl animate-fade-in-up">
            <a href="#home" class="block py-2 text-sm font-bold text-indigo-400">Beranda</a>
            <a href="#berita" class="block py-2 text-sm font-semibold text-slate-300 hover:text-indigo-400">Berita Sekolah</a>
            <a href="#agenda" class="block py-2 text-sm font-semibold text-slate-300 hover:text-indigo-400">Agenda Event</a>
            <a href="#ppdb" class="block py-2 text-sm font-semibold text-amber-400 font-bold">PPDB 2026</a>
            <a href="#informasi" class="block py-2 text-sm font-semibold text-slate-300 hover:text-indigo-400">Informasi Penting</a>
            <a href="#kontak" class="block py-2 text-sm font-semibold text-slate-300 hover:text-indigo-400">Kontak Kami</a>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="relative py-12 lg:py-16 px-4 sm:px-8 overflow-hidden">
        <!-- Ambient Glowing Orbs -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] bg-gradient-to-tr from-indigo-600/20 via-purple-600/15 to-emerald-500/10 rounded-full blur-[140px] pointer-events-none animate-glow-pulse"></div>

        <div class="max-w-6xl mx-auto relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                
                <div class="lg:col-span-7 space-y-6 text-center lg:text-left animate-fade-in-up">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-500/15 border border-indigo-500/30 text-indigo-400 dark:text-indigo-300 text-xs font-extrabold backdrop-blur-md shadow-sm">
                        <i class="fa-solid fa-award text-amber-400"></i>
                        <span>PPDB 2026: <?= htmlspecialchars(get_setting('ppdb_status')); ?></span>
                    </div>

                    <h1 class="text-3xl sm:text-5xl font-extrabold dark:text-white text-slate-900 tracking-tight leading-tight">
                        <?= htmlspecialchars(get_setting('hero_title')); ?>
                    </h1>

                    <p class="text-sm sm:text-base dark:text-slate-300 text-slate-600 leading-relaxed font-normal">
                        <?= htmlspecialchars(get_setting('hero_subtitle')); ?>
                    </p>

                    <!-- ⏳ Countdown Event Widget -->
                    <?php if ($countdownEvent): ?>
                        <div id="countdown-widget" class="p-5 rounded-3xl dark:bg-slate-900/90 bg-white border dark:border-indigo-500/30 border-slate-200 max-w-lg mx-auto lg:mx-0 shadow-2xl">
                            <div class="flex items-center gap-2 text-xs font-bold text-amber-400 mb-2.5">
                                <i class="fa-solid fa-hourglass-half animate-spin text-amber-400"></i>
                                <span><?= htmlspecialchars($countdownEvent['title']); ?>:</span>
                            </div>
                            <div class="grid grid-cols-4 gap-2.5 text-center" id="timer-box" data-date="<?= htmlspecialchars($countdownEvent['event_date']); ?>" data-title="<?= htmlspecialchars($countdownEvent['title']); ?>">
                                <div class="p-2.5 dark:bg-slate-950 bg-slate-100 rounded-2xl border dark:border-slate-800 border-slate-200 shadow-inner">
                                    <span id="cd-days" class="text-xl font-extrabold dark:text-white text-slate-900 block font-mono">00</span>
                                    <span class="text-[9px] text-slate-400 font-semibold uppercase tracking-wider">Hari</span>
                                </div>
                                <div class="p-2.5 dark:bg-slate-950 bg-slate-100 rounded-2xl border dark:border-slate-800 border-slate-200 shadow-inner">
                                    <span id="cd-hours" class="text-xl font-extrabold dark:text-white text-slate-900 block font-mono">00</span>
                                    <span class="text-[9px] text-slate-400 font-semibold uppercase tracking-wider">Jam</span>
                                </div>
                                <div class="p-2.5 dark:bg-slate-950 bg-slate-100 rounded-2xl border dark:border-slate-800 border-slate-200 shadow-inner">
                                    <span id="cd-mins" class="text-xl font-extrabold dark:text-white text-slate-900 block font-mono">00</span>
                                    <span class="text-[9px] text-slate-400 font-semibold uppercase tracking-wider">Menit</span>
                                </div>
                                <div class="p-2.5 dark:bg-slate-950 bg-slate-100 rounded-2xl border dark:border-slate-800 border-slate-200 shadow-inner">
                                    <span id="cd-secs" class="text-xl font-extrabold text-emerald-500 block font-mono">00</span>
                                    <span class="text-[9px] text-slate-400 font-semibold uppercase tracking-wider">Detik</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                        <a href="login.php" class="inline-flex items-center gap-3 bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-extrabold px-7 py-4 rounded-2xl shadow-xl shadow-indigo-600/30 hover:scale-[1.03] transition-all">
                            <span>Masuk Portal Akun</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                        <a href="#berita" class="inline-flex items-center gap-2 dark:bg-slate-900 bg-white hover:bg-slate-100 border dark:border-slate-700 border-slate-300 dark:text-slate-200 text-slate-800 font-bold px-6 py-4 rounded-2xl shadow-md transition-all">
                            <i class="fa-solid fa-newspaper text-indigo-500"></i>
                            <span>Berita Terbaru</span>
                        </a>
                    </div>
                </div>

                <!-- Sharp Floating Image Card -->
                <div class="lg:col-span-5 animate-float">
                    <div class="relative rounded-3xl dark:bg-slate-900 bg-white p-2.5 border dark:border-indigo-500/30 border-slate-200 shadow-2xl overflow-hidden group">
                        <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=1000&q=90" alt="Sekolah" class="w-full h-80 sm:h-[400px] object-cover rounded-2xl opacity-95 group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t dark:from-slate-950 from-slate-900/60 via-transparent to-transparent rounded-2xl"></div>
                        <div class="absolute bottom-6 left-6 right-6 p-4 rounded-2xl dark:bg-slate-900/90 bg-white/90 backdrop-blur-xl border dark:border-indigo-500/30 border-slate-200 flex items-center justify-between shadow-xl">
                            <div>
                                <h4 class="text-xs font-extrabold dark:text-white text-slate-900 flex items-center gap-1.5"><i class="fa-solid fa-certificate text-indigo-500"></i> Akreditasi Unggul A</h4>
                                <p class="text-[10px] text-slate-400 mt-0.5">Kemendikbudristek RI</p>
                            </div>
                            <span class="text-xs font-extrabold text-emerald-500 bg-emerald-500/10 px-3 py-1.5 rounded-xl border border-emerald-500/30">99.8% Lulus UTBK</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Berita Sekolah Section -->
    <section id="berita" class="py-14 px-4 sm:px-8 dark:bg-slate-900/40 bg-slate-100 border-t dark:border-slate-800 border-slate-200">
        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <span class="text-xs font-bold text-indigo-500 uppercase tracking-widest block mb-1">Kabar Nusantara</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold dark:text-white text-slate-900 tracking-tight">Berita & Informasi Terbaru</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($beritaList as $b): ?>
                    <div class="dark:bg-slate-900 bg-white border dark:border-slate-800 border-slate-200 rounded-3xl overflow-hidden shadow-xl hover:border-indigo-500/50 transition-all group flex flex-col justify-between">
                        <div>
                            <img src="<?= htmlspecialchars($b['thumbnail']); ?>" class="w-full h-44 object-cover group-hover:scale-105 transition-transform duration-300">
                            <div class="p-5">
                                <div class="text-[10px] text-slate-400 mb-2 font-mono"><i class="fa-regular fa-calendar mr-1"></i><?= date('d M Y', strtotime($b['created_at'])); ?></div>
                                <h3 class="text-sm font-bold dark:text-white text-slate-900 group-hover:text-indigo-500 transition-colors mb-2 line-clamp-2">
                                    <?= htmlspecialchars($b['title']); ?>
                                </h3>
                                <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                                    <?= htmlspecialchars($b['content']); ?>
                                </p>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            <span class="text-xs font-bold text-indigo-500 group-hover:underline inline-flex items-center gap-1">
                                Baca Selengkapnya <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="mt-auto dark:bg-slate-900 bg-white border-t dark:border-slate-800 border-slate-200 py-8 px-4 sm:px-8 text-xs text-slate-400">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 font-bold dark:text-white text-slate-900">
                <i class="fa-solid fa-graduation-cap text-indigo-500 text-base"></i>
                <span><?= htmlspecialchars(get_setting('school_name')); ?></span>
            </div>
            <div><?= htmlspecialchars(get_setting('footer_text')); ?></div>
        </div>
    </footer>

    <!-- 🎓 Popup PPDB Status Announcement Modal (HANYA DI INDEX.PHP) -->
    <?php 
    $currentPpdbStatus = get_ppdb_status(); 
    ?>
    <div id="popup-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="dark:bg-slate-900 bg-white border dark:border-slate-800 border-slate-200 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative text-center">
            <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-2xl mb-4 mx-auto border border-indigo-500/20">
                <i class="fa-solid fa-id-card"></i>
            </div>
            <div class="mb-3">
                <span class="px-3 py-1 rounded-full text-xs font-extrabold border <?= $currentPpdbStatus['color']; ?>">
                    STATUS PPDB: <?= $currentPpdbStatus['label']; ?>
                </span>
            </div>
            <h3 class="text-lg font-extrabold dark:text-white text-slate-900 mb-2">Informasi PPDB Online 2026</h3>
            <p class="text-xs dark:text-slate-400 text-slate-700 leading-relaxed mb-6">
                <?= htmlspecialchars($currentPpdbStatus['message']); ?>
            </p>
            <div class="flex justify-center gap-3">
                <button type="button" onclick="document.getElementById('popup-modal').classList.add('hidden')" class="w-full py-2.5 dark:bg-slate-800 bg-slate-200 dark:text-slate-300 text-slate-800 hover:text-slate-900 dark:hover:text-white font-bold rounded-xl text-xs transition-colors">
                    Tutup Notifikasi
                </button>
                <a href="ppdb.php" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 font-bold rounded-xl text-xs text-center flex items-center justify-center shadow-lg shadow-indigo-600/30" style="color:#ffffff !important;">
                    Buka Portal PPDB
                </a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/clock.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Navbar Scroll Shrink & Glassmorphism Effect
        window.addEventListener('scroll', () => {
            const navbar = document.getElementById('main-navbar');
            if (navbar) {
                if (window.scrollY > 20) {
                    navbar.classList.add('h-[64px]', 'shadow-2xl');
                    navbar.classList.remove('h-[76px]');
                } else {
                    navbar.classList.add('h-[76px]');
                    navbar.classList.remove('h-[64px]', 'shadow-2xl');
                }
            }
        });

        // Mobile Menu Dropdown Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileDropdown = document.getElementById('mobile-dropdown');
        if (mobileMenuBtn && mobileDropdown) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileDropdown.classList.toggle('hidden');
            });
        }

        // Countdown Timer Script (Accurate Timestamp Math)
        const timerBox = document.getElementById('timer-box');
        if (timerBox) {
            const rawDateStr = timerBox.getAttribute('data-date') || '';
            const eventTitle = timerBox.getAttribute('data-title') || 'Event';
            const isoDateStr = rawDateStr.trim().replace(' ', 'T');
            const targetTime = new Date(isoDateStr).getTime();

            function updateCountdown() {
                const now = Date.now();
                const distance = targetTime - now;
                const widget = document.getElementById('countdown-widget');

                if (isNaN(targetTime) || distance <= 0) {
                    if (widget) {
                        widget.innerHTML = `
                            <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-center">
                                <div class="text-xs font-bold text-rose-400 flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                    <span>${eventTitle} Telah Berakhir</span>
                                </div>
                            </div>
                        `;
                    }
                    return;
                }

                const SECOND = 1000;
                const MINUTE = SECOND * 60;
                const HOUR = MINUTE * 60;
                const DAY = HOUR * 24;

                const days = Math.floor(distance / DAY);
                const hours = Math.floor((distance % DAY) / HOUR);
                const minutes = Math.floor((distance % HOUR) / MINUTE);
                const seconds = Math.floor((distance % MINUTE) / SECOND);

                const elDays = document.getElementById('cd-days');
                const elHours = document.getElementById('cd-hours');
                const elMins = document.getElementById('cd-mins');
                const elSecs = document.getElementById('cd-secs');

                if (elDays) elDays.innerText = String(days).padStart(2, '0');
                if (elHours) elHours.innerText = String(hours).padStart(2, '0');
                if (elMins) elMins.innerText = String(minutes).padStart(2, '0');
                if (elSecs) elSecs.innerText = String(seconds).padStart(2, '0');
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        }

        // Register Service Worker for PWA Offline Support
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').then(reg => {
                    console.log('Service Worker Registered successfully:', reg.scope);
                }).catch(err => {
                    console.log('Service Worker Registration failed:', err);
                });
            });
        }
    </script>
</body>
</html>
