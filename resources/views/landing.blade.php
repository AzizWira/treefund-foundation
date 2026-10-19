<!DOCTYPE html><html lang="id" class="landing-document"><head><title>TreeFund · Adopsi Pohon Agroforestri</title><meta charset="utf-8"><meta content="width=device-width, initial-scale=1.0" name="viewport"><meta content="web_standard" name="shell-type">

<style>@layer base{html,body{margin:0;padding:0;}body{overscroll-behavior:none;}main>:first-child{margin-top:0!important;}main>:last-child{margin-bottom:0!important;}}::-webkit-scrollbar{display:none;}</style><meta name="csrf-token" content="{{ csrf_token() }}">@vite(['resources/css/app.css','resources/js/app.js'])<link rel="preload" href="/assets/fonts/material-symbols-outlined.ttf" as="font" type="font/ttf" crossorigin><link rel="stylesheet" href="/assets/fonts/fonts.css?v=2"></head><body class="site-landing bg-surface text-on-surface font-body-md text-body-md"><div class="landing-mode-note">TREEFUND · LINGKUNGAN PENGEMBANGAN · MIDTRANS SANDBOX</div><x-public-header/><main id="main-content" class="w-full pt-16 bg-surface min-h-[calc(100vh-16rem)]"><div class="flex flex-col w-full">
<!-- Hero Section -->
<section class="relative w-full overflow-hidden bg-inverse-surface -mt-16 pt-28 pb-32 md:pb-40 text-surface-container-lowest">
<!-- Atmospheric Visual Background with Scrim -->
<div class="hero-photo-bg absolute inset-0 w-full h-full bg-cover bg-center scale-105 transform motion-safe:transition-transform motion-safe:duration-1000 ease-out" data-alt="Kegiatan petani kelompok tani merawat tanaman kopi dan pohon pelindung agroforestri di lereng pegunungan Indonesia" style="background-image: url('/assets/photos/seedling.jpg')"></div>
<!-- Depth Gradient Scrims -->
<div class="hero-scrim-overlay absolute inset-0 pointer-events-none"></div>
<div class="relative max-w-7xl mx-auto px-margin z-10 flex flex-col items-center text-center">
<!-- Tag / Sub-badge -->
<div class="inline-flex items-center gap-space-xs bg-surface-container-lowest/15 backdrop-blur-md px-space-md py-1.5 rounded-full mb-space-lg text-primary-fixed shadow-sm">
<span class="material-symbols-outlined text-[18px]">satellite_alt</span>
<span class="font-label-sm text-label-sm uppercase tracking-wider text-surface-container-lowest">WebGIS &amp; Adopsi Agroforestri</span>
</div>
<!-- Main Headline -->
<h1 class="font-headline-xl text-headline-xl text-surface-container-lowest max-w-4xl tracking-tight leading-tight [text-shadow:_0_2px_12px_rgba(0,0,0,0.45)] mb-space-md">
        Bantu Hijaukan Bumi, Satu Pohon Setiap Harinya.
      </h1>
<!-- Sub-text -->
<p class="font-body-lg text-body-lg text-surface-container-low max-w-2xl font-normal leading-relaxed mb-space-xl [text-shadow:_0_1px_4px_rgba(0,0,0,0.3)]">
        Platform transparansi adopsi pohon agroforestri dengan pantauan WebGIS dan laporan lapangan, terintegrasi langsung dengan kelompok tani hutan nusantara.
      </p>
<!-- Action Buttons -->
<div class="flex flex-col sm:flex-row items-center justify-center gap-space-md w-full sm:w-auto">
<a class="w-full sm:w-auto inline-flex items-center justify-center gap-space-sm bg-primary-container hover:bg-primary text-surface-container-lowest font-headline-sm text-headline-sm px-8 py-3.5 rounded-lg shadow-md transition-all duration-200 transform hover:-translate-y-0.5" href="#katalog">
<span>Lihat Katalog Lahan</span>
<span class="material-symbols-outlined text-[20px]">arrow_forward</span>
</a>
<a class="w-full sm:w-auto inline-flex items-center justify-center gap-space-sm bg-surface-container-lowest/10 hover:bg-surface-container-lowest/20 backdrop-blur-sm text-surface-container-lowest font-headline-sm text-headline-sm px-7 py-3.5 rounded-lg shadow-sm transition-all duration-200" href="#cara-kerja">
<span class="material-symbols-outlined text-[20px]">play_circle</span>
<span>Pelajari Cara Kerja</span>
</a>
</div>
<!-- Floating Indicator Badges -->
<div class="mt-space-xl flex flex-wrap justify-center items-center gap-space-lg text-surface-container-low/80 font-label-md text-label-md">
<div class="flex items-center gap-space-xs">
<span class="material-symbols-outlined text-primary-fixed text-[16px]">verified</span>
<span>Mitra Terverifikasi</span>
</div>
<div class="flex items-center gap-space-xs">
<span class="material-symbols-outlined text-primary-fixed text-[16px]">pin_drop</span>
<span>Geotagging Titik Tanam GPS</span>
</div>
<div class="flex items-center gap-space-xs">
<span class="material-symbols-outlined text-primary-fixed text-[16px]">lock</span>
<span>Midtrans Sandbox</span>
</div>
</div>
</div>
</section>
<!-- Section 1.5: Floating Metric Counter Bar (Overlapping -mt-16) -->
<section class="relative z-20 px-margin">
<div class="max-w-5xl mx-auto -mt-16 bg-surface-container-lowest rounded-full shadow-xl p-space-lg overflow-hidden">
<!-- Top Micro Status Header -->
<div class="flex flex-wrap items-center justify-between pb-space-md mb-space-md bg-surface-container-low px-space-md py-space-xs rounded-lg gap-space-xs">
<div class="flex items-center gap-space-xs">
<span class="w-2.5 h-2.5 rounded-full bg-primary-container animate-pulse"></span>
<span class="font-label-sm text-label-sm text-primary font-semibold uppercase tracking-wider">Monitoring Lapangan</span>
</div>
<div class="flex items-center gap-space-sm text-on-surface-variant font-label-sm text-label-sm">
<span>Pembaruan berdasarkan laporan terverifikasi</span>
<span class="hidden sm:inline text-outline-variant">•</span>
<span class="hidden sm:inline">Laporan Mitra Petani</span>
</div>
</div>
<!-- Metric Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-space-lg items-center">
<!-- Metric 1: Total Pohon -->
<div class="flex items-center gap-space-md p-space-sm rounded-lg hover:bg-surface-container-low transition-colors">
<div class="w-14 h-14 rounded-full bg-surface-container flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary text-[32px]">forest</span>
</div>
<div class="flex flex-col">
<div class="flex items-baseline gap-space-xs">
<span class="font-headline-xl text-headline-xl text-primary font-bold tracking-tight">{{ $publicStats['planted'] }}</span>
<span class="font-label-sm text-label-sm text-primary font-semibold">Terverifikasi</span>
</div>
<span class="font-headline-sm text-headline-sm text-on-surface">Pohon Tertanam</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">Agroforestri terpetakan aktif</span>
</div>
</div>
<!-- Metric 2: Karbon Terserap -->
<div class="flex items-center gap-space-md p-space-sm rounded-lg hover:bg-surface-container-low transition-colors">
<div class="w-14 h-14 rounded-full bg-surface-container flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary-container text-[32px]">co2</span>
</div>
<div class="flex flex-col">
<span class="font-headline-xl text-headline-xl text-primary-container font-bold tracking-tight">{{ $publicStats['reports'] }}</span>
<span class="font-headline-sm text-headline-sm text-on-surface">Laporan Disetujui</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">Berdasarkan bukti lapangan</span>
</div>
</div>
<!-- Metric 3: Petani Mitra -->
<div class="flex items-center gap-space-md p-space-sm rounded-lg hover:bg-surface-container-low transition-colors">
<div class="w-14 h-14 rounded-full bg-surface-container flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-secondary text-[32px]">groups</span>
</div>
<div class="flex flex-col">
<span class="font-headline-xl text-headline-xl text-secondary font-bold tracking-tight">{{ \App\Models\Partner::where('status','approved')->count() }}</span>
<span class="font-headline-sm text-headline-sm text-on-surface">Mitra Petani Hutan</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">Kelompok tani terverifikasi</span>
</div>
</div>
</div>
</div>
</section>
<!-- Section 2: Cara Kerja Donasi Agroforestri -->
<section class="w-full bg-surface py-20 px-margin mt-space-lg" id="cara-kerja">
<div class="max-w-7xl mx-auto flex flex-col">
<!-- Section Title Header -->
<div class="text-center max-w-3xl mx-auto mb-space-xl">
<div class="inline-flex items-center gap-space-xs bg-surface-container-highest px-space-md py-1 rounded-full text-primary mb-space-sm">
<span class="material-symbols-outlined text-[16px]">account_tree</span>
<span class="font-label-sm text-label-sm font-semibold uppercase tracking-wider">Alur Transparansi Terbuka</span>
</div>
<h2 class="font-headline-xl text-headline-xl text-on-surface tracking-tight mb-space-sm">
          Bagaimana Donasi Anda Bekerja?
        </h2>
<p class="font-body-lg text-body-lg text-on-surface-variant">
          4 langkah transparan dari pendanaan bibit hingga pertumbuhan terpantau melalui laporan secara akuntabel.
        </p>
</div>
<!-- 4 Columns Process Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-space-md relative">
<!-- Step 1 -->
<div class="group bg-surface-container-lowest p-space-lg rounded-xl shadow-sm hover:shadow-md transition-all duration-300 flex flex-col justify-between relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-surface-container-low rounded-bl-full -mr-6 -mt-6 transition-all group-hover:bg-primary-fixed/30"></div>
<div>
<div class="flex items-center justify-between mb-space-md relative z-10">
<span class="w-12 h-12 rounded-xl bg-surface-container-high flex items-center justify-center text-primary font-headline-md text-headline-md font-bold">
                01
              </span>
<span class="material-symbols-outlined text-[32px] text-primary">travel_explore</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-on-surface mb-space-xs font-bold">Pilih Lahan</h3>
<p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
              Pilih lokasi lahan kritis atau agroforestri produktif yang membutuhkan adopsi pohon berdasarkan profil ekologis &amp; risiko bencana lokal.
            </p>
</div>
<div class="mt-space-lg pt-space-sm flex items-center gap-space-xs text-primary font-label-md text-label-md">
<span>Lokasi Lahan Terverifikasi</span>
<span class="material-symbols-outlined text-[16px]">chevron_right</span>
</div>
</div>
<!-- Step 2 -->
<div class="group bg-surface-container-lowest p-space-lg rounded-xl shadow-sm hover:shadow-md transition-all duration-300 flex flex-col justify-between relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-surface-container-low rounded-bl-full -mr-6 -mt-6 transition-all group-hover:bg-secondary-fixed/40"></div>
<div>
<div class="flex items-center justify-between mb-space-md relative z-10">
<span class="w-12 h-12 rounded-xl bg-surface-container-high flex items-center justify-center text-secondary font-headline-md text-headline-md font-bold">
                02
              </span>
<span class="material-symbols-outlined text-[32px] text-secondary">payments</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-on-surface mb-space-xs font-bold">Berdonasi</h3>
<p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
              Kustomisasi jenis pohon &amp; sertifikat atas nama Anda atau korporasi. Simulasi transaksi aman melalui payment gateway instan resmi.
            </p>
</div>
<div class="mt-space-lg pt-space-sm flex items-center gap-space-xs text-secondary font-label-md text-label-md">
<span>E-Sertifikat Adopsi</span>
<span class="material-symbols-outlined text-[16px]">chevron_right</span>
</div>
</div>
<!-- Step 3 -->
<div class="group bg-surface-container-lowest p-space-lg rounded-xl shadow-sm hover:shadow-md transition-all duration-300 flex flex-col justify-between relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-surface-container-low rounded-bl-full -mr-6 -mt-6 transition-all group-hover:bg-tertiary-fixed/40"></div>
<div>
<div class="flex items-center justify-between mb-space-md relative z-10">
<span class="w-12 h-12 rounded-xl bg-surface-container-high flex items-center justify-center text-tertiary font-headline-md text-headline-md font-bold">
                03
              </span>
<span class="material-symbols-outlined text-[32px] text-tertiary">agriculture</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-on-surface mb-space-xs font-bold">Petani Menanam</h3>
<p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
              Kelompok tani mitra menanam bibit unggul, memupuk organik, dan mencatat koordinat geolokasi presisi langsung di lapangan via aplikasi terpadu.
            </p>
</div>
<div class="mt-space-lg pt-space-sm flex items-center gap-space-xs text-tertiary font-label-md text-label-md">
<span>Pemberdayaan Desa</span>
<span class="material-symbols-outlined text-[16px]">chevron_right</span>
</div>
</div>
<!-- Step 4 -->
<div class="group bg-surface-container-lowest p-space-lg rounded-xl shadow-sm hover:shadow-md transition-all duration-300 flex flex-col justify-between relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-surface-container-low rounded-bl-full -mr-6 -mt-6 transition-all group-hover:bg-primary-fixed/40"></div>
<div>
<div class="flex items-center justify-between mb-space-md relative z-10">
<span class="w-12 h-12 rounded-xl bg-surface-container-high flex items-center justify-center text-primary-container font-headline-md text-headline-md font-bold">
                04
              </span>
<span class="material-symbols-outlined text-[32px] text-primary-container">map</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-on-surface mb-space-xs font-bold">Pantau via Peta</h3>
<p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
              Akses WebGIS interaktif pribadi dengan foto pertumbuhan berkala, tinggi pohon, status kesehatan, dan catatan perawatan.
            </p>
</div>
<div class="mt-space-lg pt-space-sm flex items-center gap-space-xs text-primary-container font-label-md text-label-md">
<span>Laporan Monitoring Berkala</span>
<span class="material-symbols-outlined text-[16px]">chevron_right</span>
</div>
</div>
</div>
</div>
</section>
<!-- Section 3: Lahan Mendesak Membutuhkan Adopsi -->
<section class="w-full bg-surface-container-lowest py-20 px-margin" id="katalog"><div class="max-w-7xl mx-auto"><div class="page-heading"><div><span class="eyebrow">PELUANG ADOPSI AGROFORESTRI</span><h2>Lahan terverifikasi, siap untuk bertumbuh.</h2><p>Pilih penawaran dari kelompok tani yang telah melalui pemeriksaan.</p></div><a class="text-link" href="/catalog">Lihat Semua Lahan →</a></div><div class="catalog-grid">@forelse($featuredOffers as $offer)<article class="catalog-card"><div class="catalog-image"><img loading="lazy" decoding="async" src="{{ $offer->photo ? asset(str_starts_with($offer->photo,'assets/') ? $offer->photo : 'storage/'.$offer->photo) : asset('assets/forest.svg') }}" alt="{{ $offer->species }}"><span class="badge badge-green">{{ $offer->plot->location }}</span></div><div class="catalog-body"><span class="eyebrow">{{ $offer->plot->partner->group_name }}</span><h2>{{ $offer->plot->name }}</h2><p>{{ Str::limit($offer->description,120) }}</p><span class="species-tag">{{ $offer->species }}</span><div class="quota-line"><span>Kuota tersedia</span><strong>{{ $offer->plot->available }} pohon</strong></div><div class="catalog-bottom"><strong>Rp {{ number_format($offer->price,0,',','.') }}<small>/pohon</small></strong><a class="button small" href="/catalog?q={{ urlencode($offer->species) }}">Lihat Penawaran →</a></div></div></article>@empty<section class="panel"><x-empty title="Penawaran segera hadir" text="Lahan akan muncul setelah pemeriksaan Mitra dan publikasi katalog."/></section>@endforelse</div></div></section>
<!-- Section 4: WebGIS Satellite Transparency Banner -->
<section class="w-full bg-surface py-16 px-margin">
<div class="max-w-7xl mx-auto bg-inverse-surface rounded-2xl overflow-hidden shadow-xl text-surface-container-lowest grid grid-cols-1 lg:grid-cols-12 items-stretch">
<!-- Left Editorial Text Content -->
<div class="lg:col-span-6 p-space-xl flex flex-col justify-center">
<div class="inline-flex items-center gap-space-xs text-primary-fixed mb-space-sm font-label-sm text-label-sm uppercase font-semibold">
<span class="material-symbols-outlined text-[16px]">public</span>
<span>Teknologi WebGIS Donatur</span>
</div>
<h2 class="font-headline-xl text-headline-xl text-surface-container-lowest tracking-tight mb-space-md">
          Pantau Pertumbuhan Pohon Anda Melalui WebGIS.
        </h2>
<p class="font-body-md text-body-md text-surface-container-high leading-relaxed mb-space-lg">
          Bukan sekadar menanam, TreeFund memberikan tautan peta interaktif WebGIS khusus untuk setiap pohon Anda. Periksa foto lapangan geotagged, tinggi pohon, status kesehatan, dan catatan perawatan secara berkala dari kenyamanan ponsel Anda.
        </p>
<div class="flex flex-wrap items-center gap-space-md">
<a class="inline-flex items-center gap-space-sm bg-primary-fixed text-on-primary-fixed hover:bg-surface-container-lowest font-headline-sm text-headline-sm px-6 py-3 rounded-lg shadow-sm transition-colors" data-path="peta-webgis-donatur" href="/peta">
<span>Jelajahi Peta Lahan</span>
<span class="material-symbols-outlined text-[20px]">explore</span>
</a>
<span class="font-label-sm text-label-sm text-surface-container-high flex items-center gap-1">
<span class="material-symbols-outlined text-[16px] text-primary-fixed">check</span>
            Koordinat penanaman terverifikasi
          </span>
</div>
</div>
<!-- Right Interactive Map Showcase Mockup -->
<div class="lg:col-span-6 relative min-h-[300px] lg:min-h-full bg-surface-container-highest">
<div class="w-full h-full bg-cover bg-center min-h-[320px]" data-location="Mount Semeru Agroforestry Reserve, Lumajang, Indonesia" style="background-image: url('/assets/reference/dbc915f3ca6e4fbc351e.jpg')"></div>
<!-- GIS Map HUD Overlay -->
<div class="absolute bottom-4 left-4 right-4 bg-inverse-surface/85 backdrop-blur-md p-space-md rounded-lg shadow-lg text-surface-container-lowest flex items-center justify-between gap-space-sm">
<div class="flex items-center gap-space-sm">
<span class="material-symbols-outlined text-primary-fixed text-[24px]">satellite</span>
<div>
<span class="font-label-sm text-label-sm block text-primary-fixed font-semibold">Ilustrasi tampilan WebGIS</span>
<span class="font-body-sm text-body-sm text-surface-container-high">Data lahan tersedia pada peta publik</span>
</div>
</div>
<div class="text-right">
<span class="font-label-sm text-label-sm block text-surface-container-high">Status Pertumbuhan</span>
<span class="font-headline-sm text-headline-sm text-primary-fixed font-bold">Laporan Terverifikasi</span>
</div>
</div>
</div>
</div>
</section>
</div></main><x-public-footer/></body></html>
