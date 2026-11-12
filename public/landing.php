<?php
// landing.php — Landing page standalone (tanpa require/include, tanpa database).
// Simpan di folder public/. Semua link relatif ke file yang sudah ada.
$tahun = date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PT Penerbang Roket — Dealer Mobil Baru Honda &amp; Suzuki</title>
    <meta name="description" content="Dealer mobil baru Honda &amp; Suzuki: katalog unit, booking test drive, booking servis, dan cek status booking secara online.">

    <script>
        // Tema: memakai kunci 'theme' yang sama dengan website utama, jadi pilihan terang/gelap ikut terbawa.
        (function () {
            var theme = 'dark';
            try { theme = localStorage.getItem('theme') || 'dark'; } catch (e) {}
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Segoe UI', 'system-ui', '-apple-system', 'Roboto', 'sans-serif'] },
                    colors: {
                        gold: { DEFAULT: '#c8a96a', dark: '#a68a4e' },
                        ink: { DEFAULT: '#0d0e11', card: '#16181d', line: '#262a31' },
                        paper: { DEFAULT: '#f6f5f2', line: '#e4e1d9' }
                    }
                }
            }
        };
    </script>
    <style>
        .hero-glow {
            background: radial-gradient(48rem 24rem at 90% -10%, rgba(200, 169, 106, .20), transparent 60%),
                        radial-gradient(36rem 20rem at 0% 100%, rgba(200, 169, 106, .10), transparent 60%);
        }
        html { scroll-behavior: smooth; }
    </style>
</head>

<body class="bg-paper text-slate-700 antialiased dark:bg-ink dark:text-neutral-300 font-sans">

    <!-- ===================== NAVBAR ===================== -->
    <header class="sticky top-0 z-50 border-b border-paper-line bg-white/90 backdrop-blur dark:border-ink-line dark:bg-ink-card/90">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="landing.php" class="flex items-center gap-2 text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                <svg class="h-6 w-6 text-gold" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2c3 2.2 5 5.6 5 9.5 0 1.4-.3 2.7-.8 3.9l2.3 2.3V20l-3.2-1.3c-.9.8-2 1.3-3.3 1.3s-2.4-.5-3.3-1.3L4.5 20v-2.3l2.3-2.3A9.6 9.6 0 0 1 6 11.500C6 7.600 8 4.200 12 2zm0 5a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
                </svg>
                <span>PENERBANG <span class="text-gold">ROKET</span></span>
            </a>

            <ul class="hidden items-center gap-1 lg:flex">
                <li><a href="index.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-gold dark:text-neutral-300 dark:hover:text-gold">Katalog</a></li>
                <li><a href="testdrive.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-gold dark:text-neutral-300 dark:hover:text-gold">Test Drive</a></li>
                <li><a href="service_booking.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-gold dark:text-neutral-300 dark:hover:text-gold">Servis</a></li>
                <li><a href="cek_status.php" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-gold dark:text-neutral-300 dark:hover:text-gold">Cek Status</a></li>
            </ul>

            <div class="flex items-center gap-2">
                <button id="themeToggle" type="button" aria-label="Ganti tema terang/gelap"
                    class="flex h-10 w-10 items-center justify-center rounded-lg border border-paper-line text-slate-600 transition hover:border-gold hover:text-gold dark:border-ink-line dark:text-neutral-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/><path d="M12 3v18" /><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>
                    </svg>
                </button>
                <a href="login.php" class="hidden rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 transition hover:border-gold hover:text-gold dark:border-neutral-600 dark:text-white dark:hover:border-gold dark:hover:text-gold sm:inline-flex">
                    Login
                </a>
                <button id="menuToggle" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="mobileMenu"
                    class="flex h-10 w-10 items-center justify-center rounded-lg border border-paper-line text-slate-600 dark:border-ink-line dark:text-neutral-300 lg:hidden">
                    <svg id="iconOpen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg id="iconClose" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        </nav>

        <div id="mobileMenu" class="hidden border-t border-paper-line bg-white px-4 pb-4 pt-2 dark:border-ink-line dark:bg-ink-card lg:hidden">
            <ul class="space-y-1">
                <li><a href="index.php" class="block rounded-lg px-3 py-2.5 text-sm font-medium hover:text-gold">Katalog</a></li>
                <li><a href="testdrive.php" class="block rounded-lg px-3 py-2.5 text-sm font-medium hover:text-gold">Test Drive</a></li>
                <li><a href="service_booking.php" class="block rounded-lg px-3 py-2.5 text-sm font-medium hover:text-gold">Servis</a></li>
                <li><a href="cek_status.php" class="block rounded-lg px-3 py-2.5 text-sm font-medium hover:text-gold">Cek Status</a></li>
            </ul>
            <a href="login.php" class="mt-3 flex w-full items-center justify-center rounded-lg border border-gold px-4 py-2.5 text-sm font-semibold text-gold">Login</a>
        </div>
    </header>

    <main>

        <!-- ===================== HERO ===================== -->
        <section class="relative overflow-hidden border-b border-paper-line bg-white dark:border-ink-line dark:bg-ink-card">
            <div class="hero-glow pointer-events-none absolute inset-0"></div>
            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-2 lg:px-8 lg:py-28">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-gold/40 bg-gold/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-gold-dark dark:text-gold">
                        <span class="h-1.5 w-1.5 rounded-full bg-gold"></span>
                        Honda &amp; Suzuki — Unit Baru Resmi
                    </span>
                    <h1 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight text-slate-900 dark:text-white sm:text-5xl lg:text-6xl">
                        Temukan Mobil Impian Anda, <span class="text-gold">Tanpa Ribet.</span>
                    </h1>
                    <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-600 dark:text-neutral-400 sm:text-lg">
                        Dari memilih unit, simulasi cicilan, test drive, hingga servis berkala — semuanya bisa diatur online dalam satu tempat. Transparan, cepat, dan terpercaya.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="index.php" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gold px-6 py-3.5 text-sm font-bold text-neutral-900 shadow-lg shadow-gold/20 transition hover:-translate-y-0.5 hover:bg-gold-dark">
                            Lihat Katalog Mobil
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                        <a href="testdrive.php" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-6 py-3.5 text-sm font-bold text-slate-800 transition hover:-translate-y-0.5 hover:border-gold hover:text-gold dark:border-neutral-600 dark:text-white dark:hover:border-gold dark:hover:text-gold">
                            Booking Test Drive
                        </a>
                    </div>
                    <dl class="mt-10 grid max-w-md grid-cols-3 gap-6 border-t border-paper-line pt-6 dark:border-ink-line">
                        <div><dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-neutral-500">Model</dt><dd class="mt-1 text-2xl font-extrabold text-slate-900 dark:text-white">20+</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-neutral-500">Merek</dt><dd class="mt-1 text-2xl font-extrabold text-slate-900 dark:text-white">2</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-neutral-500">Booking</dt><dd class="mt-1 text-2xl font-extrabold text-slate-900 dark:text-white">24/7</dd></div>
                    </dl>
                </div>

                <div class="mx-auto w-full max-w-xl">
                    <div class="rounded-3xl border border-paper-line bg-paper p-6 shadow-xl dark:border-ink-line dark:bg-ink sm:p-8">
                        <svg viewBox="0 0 520 220" class="w-full" role="img" aria-label="Ilustrasi mobil">
                            <defs>
                                <linearGradient id="body" x1="0" x2="1" y1="0" y2="1">
                                    <stop offset="0" stop-color="#d9bf86"/><stop offset="1" stop-color="#8a703a"/>
                                </linearGradient>
                            </defs>
                            <ellipse cx="260" cy="190" rx="220" ry="14" fill="#000" opacity=".18"/>
                            <path d="M40 150 C40 128 52 118 78 112 L140 94 C160 70 185 56 215 54 L300 54 C335 54 360 72 385 96 L440 108 C470 114 482 128 482 150 L482 164 L40 164 Z" fill="url(#body)"/>
                            <path d="M158 96 C174 76 192 66 214 66 L296 66 C322 66 342 80 362 98 Z" fill="#e5e7eb" opacity=".92"/>
                            <path d="M252 66 L252 98" stroke="#94a3b8" stroke-width="3"/>
                            <rect x="456" y="124" width="26" height="10" rx="5" fill="#fef3c7"/>
                            <rect x="40" y="128" width="20" height="9" rx="4" fill="#fecaca"/>
                            <circle cx="140" cy="166" r="30" fill="#0f172a"/><circle cx="140" cy="166" r="14" fill="#cbd5e1"/><circle cx="140" cy="166" r="5" fill="#475569"/>
                            <circle cx="386" cy="166" r="30" fill="#0f172a"/><circle cx="386" cy="166" r="14" fill="#cbd5e1"/><circle cx="386" cy="166" r="5" fill="#475569"/>
                        </svg>
                        <div class="mt-5 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-xl border border-paper-line bg-white px-2 py-3 dark:border-ink-line dark:bg-ink-card"><p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-neutral-500">Unit</p><p class="text-sm font-bold text-slate-900 dark:text-white">Baru Resmi</p></div>
                            <div class="rounded-xl border border-paper-line bg-white px-2 py-3 dark:border-ink-line dark:bg-ink-card"><p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-neutral-500">Cicilan</p><p class="text-sm font-bold text-slate-900 dark:text-white">Transparan</p></div>
                            <div class="rounded-xl border border-paper-line bg-white px-2 py-3 dark:border-ink-line dark:bg-ink-card"><p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-neutral-500">Purna Jual</p><p class="text-sm font-bold text-slate-900 dark:text-white">Terjamin</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== LAYANAN UTAMA ===================== -->
        <section id="layanan" class="py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-gold">Layanan Utama</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Satu dealer, semua kebutuhan mobil Anda</h2>
                    <p class="mt-4 text-slate-600 dark:text-neutral-400">Mulai dari membeli, mencoba, hingga merawat kendaraan — kami dampingi di setiap langkah.</p>
                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <article class="group flex flex-col rounded-2xl border border-paper-line bg-white p-7 transition hover:-translate-y-1 hover:border-gold dark:border-ink-line dark:bg-ink-card">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gold/15 text-gold">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 17h-2v-6l2-5h9l4 5h1a2 2 0 0 1 2 2v4h-2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                        </span>
                        <h3 class="mt-5 text-xl font-bold text-slate-900 dark:text-white">Penjualan Unit Berkualitas</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 dark:text-neutral-400">Jelajahi katalog unit baru Honda &amp; Suzuki lengkap dengan varian, warna, transmisi, dan harga OTR. Filter sesuai kebutuhan dan temukan yang paling pas.</p>
                        <a href="index.php" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-gold transition group-hover:gap-2.5">Jelajahi Unit <span aria-hidden="true">&rarr;</span></a>
                    </article>

                    <article class="group flex flex-col rounded-2xl border border-paper-line bg-white p-7 transition hover:-translate-y-1 hover:border-gold dark:border-ink-line dark:bg-ink-card">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gold/15 text-gold">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M12 3v6M12 15v6M3 12h6M15 12h6"/></svg>
                        </span>
                        <h3 class="mt-5 text-xl font-bold text-slate-900 dark:text-white">Test Drive Mudah</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 dark:text-neutral-400">Rasakan langsung performa mobil pilihan Anda. Pilih unit, tanggal, dan jam yang nyaman lewat form online — tanpa antre dan tanpa tekanan.</p>
                        <a href="testdrive.php" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-gold transition group-hover:gap-2.5">Booking Test Drive <span aria-hidden="true">&rarr;</span></a>
                    </article>

                    <article class="group flex flex-col rounded-2xl border border-paper-line bg-white p-7 transition hover:-translate-y-1 hover:border-gold dark:border-ink-line dark:bg-ink-card">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gold/15 text-gold">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.6-.6-.6-2.6z"/></svg>
                        </span>
                        <h3 class="mt-5 text-xl font-bold text-slate-900 dark:text-white">Servis Berkala</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 dark:text-neutral-400">Jaga performa dan nilai jual kendaraan dengan servis terjadwal oleh teknisi berpengalaman. Booking jadwal servis kapan saja secara online.</p>
                        <a href="service_booking.php" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-gold transition group-hover:gap-2.5">Booking Servis <span aria-hidden="true">&rarr;</span></a>
                    </article>
                </div>

                <div class="mt-10 flex flex-col items-center justify-between gap-4 rounded-2xl border border-dashed border-gold/60 bg-white px-6 py-5 text-center dark:bg-ink-card sm:flex-row sm:text-left">
                    <p class="text-sm text-slate-600 dark:text-neutral-400"><span class="font-semibold text-slate-900 dark:text-white">Sudah melakukan booking?</span> Pantau perkembangan test drive atau servis Anda kapan saja.</p>
                    <a href="cek_status.php" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gold px-5 py-2.5 text-sm font-semibold text-gold transition hover:bg-gold hover:text-neutral-900">Cek Status Booking</a>
                </div>
            </div>
        </section>

        <!-- ===================== KEUNGGULAN ===================== -->
        <section id="keunggulan" class="border-y border-paper-line bg-white py-16 dark:border-ink-line dark:bg-ink-card sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-gold">Keunggulan Dealer</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Mengapa memilih PT Penerbang Roket?</h2>
                    <p class="mt-4 text-slate-600 dark:text-neutral-400">Membeli mobil harus menyenangkan dan bebas kejutan. Setiap unit dan setiap angka kami pastikan jelas sejak awal.</p>
                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div class="rounded-2xl border border-paper-line bg-paper p-7 text-center dark:border-ink-line dark:bg-ink">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gold/15 text-gold">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
                        </span>
                        <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">Inspeksi Ketat</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-neutral-400">Setiap unit melewati pemeriksaan menyeluruh sebelum diserahkan — kondisi bodi, interior, hingga kelengkapan dokumen.</p>
                    </div>
                    <div class="rounded-2xl border border-paper-line bg-paper p-7 text-center dark:border-ink-line dark:bg-ink">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gold/15 text-gold">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h2M14 10h2M8 14h2M14 14h2M8 18h2M14 18h2"/></svg>
                        </span>
                        <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">Simulasi Kredit &amp; TDP Transparan</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-neutral-400">Hitung uang muka (TDP) dan cicilan bulanan sebelum memutuskan. Tidak ada biaya tersembunyi, semua rincian jelas di awal.</p>
                    </div>
                    <div class="rounded-2xl border border-paper-line bg-paper p-7 text-center dark:border-ink-line dark:bg-ink">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gold/15 text-gold">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="M8.5 14.5L7 22l5-3 5 3-1.5-7.5"/></svg>
                        </span>
                        <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">Garansi Mesin</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-neutral-400">Garansi 3 tahun / 100.000 km, didukung layanan purna jual dan riwayat servis yang tercatat rapi.</p>
                    </div>
                </div>

                <div class="mt-12 text-center">
                    <a href="index.php" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gold px-7 py-3.5 text-sm font-bold text-neutral-900 transition hover:bg-gold-dark">
                        Jelajahi Unit <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- ===================== FOOTER ===================== -->
    <footer class="bg-paper dark:bg-ink-card">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <a href="landing.php" class="text-lg font-bold text-slate-900 dark:text-white">PENERBANG <span class="text-gold">ROKET</span></a>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-neutral-400">Dealer mobil baru Honda &amp; Suzuki dengan layanan penjualan, test drive, dan purna jual yang transparan dan terpercaya.</p>
                </div>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">Navigasi</h3>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li><a href="index.php" class="text-slate-600 hover:text-gold dark:text-neutral-400">Katalog Mobil</a></li>
                        <li><a href="testdrive.php" class="text-slate-600 hover:text-gold dark:text-neutral-400">Booking Test Drive</a></li>
                        <li><a href="service_booking.php" class="text-slate-600 hover:text-gold dark:text-neutral-400">Booking Servis</a></li>
                        <li><a href="cek_status.php" class="text-slate-600 hover:text-gold dark:text-neutral-400">Cek Status Booking</a></li>
                        <li><a href="login.php" class="text-slate-600 hover:text-gold dark:text-neutral-400">Login</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">Jam Operasional</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-slate-600 dark:text-neutral-400">
                        <li class="flex justify-between gap-4"><span>Senin – Jumat</span><span class="font-medium text-slate-900 dark:text-neutral-200">08.00 – 17.00</span></li>
                        <li class="flex justify-between gap-4"><span>Sabtu</span><span class="font-medium text-slate-900 dark:text-neutral-200">08.00 – 15.00</span></li>
                        <li class="flex justify-between gap-4"><span>Minggu &amp; Libur</span><span class="font-medium text-slate-900 dark:text-neutral-200">Tutup</span></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">Kontak &amp; Alamat</h3>
                    <ul class="mt-4 space-y-3 text-sm text-slate-600 dark:text-neutral-400">
                        <li>Jl. Raya Puspiptek No. 46, Buaran, Serpong, Tangerang Selatan</li>
                        <li><a href="tel:+6221000000" class="hover:text-gold">(021) 000-0000</a></li>
                        <li><a href="mailto:info@penerbangroket.test" class="hover:text-gold">info@penerbangroket.test</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-paper-line pt-6 text-xs text-slate-500 dark:border-ink-line dark:text-neutral-500 sm:flex-row">
                <p>&copy; <?= $tahun ?> PT Penerbang Roket. Hak cipta dilindungi undang-undang.</p>
                <p>Harga dan ketersediaan unit dapat berubah sewaktu-waktu.</p>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            document.getElementById('themeToggle').addEventListener('click', function () {
                var dark = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
            });

            var btn = document.getElementById('menuToggle');
            var menu = document.getElementById('mobileMenu');
            var iOpen = document.getElementById('iconOpen');
            var iClose = document.getElementById('iconClose');
            btn.addEventListener('click', function () {
                var open = menu.classList.toggle('hidden') === false;
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                iOpen.classList.toggle('hidden', open);
                iClose.classList.toggle('hidden', !open);
            });
        })();
    </script>
</body>
</html>