@extends('layouts.site')
@section('title',$title)
@section('content')
<div class="site-intro"><span class="eyebrow">{{ $title }}</span><h1>{{ $heading }}</h1><p>{{ $body }}</p></div>
<div class="site-info-grid">
    <section class="panel"><span class="material-symbols-outlined info-symbol" aria-hidden="true">forest</span><h2>Adopsi yang dapat ditelusuri.</h2><p>Setelah pembayaran terkonfirmasi, setiap pohon memiliki kode unik dan terhubung dengan lahan serta kelompok tani yang bertanggung jawab.</p></section>
    <section class="panel"><span class="material-symbols-outlined info-symbol" aria-hidden="true">verified_user</span><h2>Laporan diperiksa terlebih dahulu.</h2><p>Foto, tanggal, koordinat, dan kondisi pohon dikirim oleh Mitra. Admin menyetujui atau mengembalikan pengajuan dengan catatan perbaikan.</p></section>
    <section class="panel"><span class="material-symbols-outlined info-symbol" aria-hidden="true">groups</span><h2>Bertumbuh bersama Mitra.</h2><p>Kelompok tani mengelola penanaman dan perawatan. Pencairan mengikuti alokasi serta milestone pekerjaan yang tercatat.</p></section>
</div>
<section class="panel reading-panel"><h2>Alur Adopsi Pohon</h2><ol class="steps-list"><li>Pilih bibit dan lahan terverifikasi dari katalog.</li><li>Tentukan jumlah pohon dan nama pada sertifikat.</li><li>Selesaikan pembayaran dan unduh e-sertifikat dari akun Anda.</li><li>Mitra menanam pohon serta mengunggah bukti lapangan.</li><li>Ikuti laporan yang disetujui Admin melalui peta portofolio.</li></ol><p>Informasi pohon pribadi tersedia melalui dashboard Donatur. Halaman publik tetap dapat dibuka tanpa masuk akun.</p><div class="action-row"><a href="{{ route('catalog') }}" class="button"><span>Jelajahi Katalog</span><span class="btn-arrow" aria-hidden="true">→</span></a><a class="text-link" href="{{ route('faq') }}">Pertanyaan Umum</a></div></section>
@endsection
