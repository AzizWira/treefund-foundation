@extends('layouts.site')
@section('title','Pertanyaan Umum')
@section('content')
<div class="site-intro"><span class="eyebrow">KAMI SIAP MEMBANTU</span><h1>Pertanyaan yang sering diajukan.</h1><p>Kenali cara mengadopsi pohon dan mengikuti perkembangannya.</p></div>
<section class="faq-list">
    @foreach([
        ['Bagaimana cara mengadopsi pohon?','Daftar sebagai Donatur, pilih bibit dan lahan dari katalog, tentukan jumlah pohon serta nama pada sertifikat, kemudian selesaikan pembayaran pada detail pesanan.'],
        ['Apakah pembayaran ini menggunakan uang asli?','Versi pengembangan ini menggunakan Midtrans Sandbox. Selesaikan transaksi melalui simulator resmi Midtrans, bukan transfer atau pemindaian QR dari aplikasi pembayaran asli.'],
        ['Kapan e-sertifikat tersedia?','Sertifikat PDF diterbitkan setelah status pembayaran dikonfirmasi oleh server. Anda dapat mengunduhnya melalui menu E-Sertifikat Adopsi pada dashboard Donatur.'],
        ['Mengapa pohon belum tampil pada peta portofolio?','Identitas pohon tersedia setelah pembayaran berhasil. Titik tanam muncul setelah Mitra mengunggah bukti penanaman dan Admin menyetujuinya.'],
        ['Apa perbedaan peta publik dan peta portofolio?','Peta publik menampilkan lahan yang tersedia pada katalog. Peta portofolio di dashboard menampilkan pohon adopsi milik Anda beserta laporan terverifikasi.'],
        ['Bagaimana kelompok tani menjadi Mitra?','Daftar sebagai Mitra, lengkapi profil kelompok dan rekening, serta unggah dokumen legalitas. Setelah profil disetujui, ajukan lahan untuk diverifikasi.'],
        ['Bagaimana dana dicairkan kepada Mitra?','Admin Keuangan menetapkan alokasi work order. Mitra mengajukan termin setelah milestone terpenuhi, kemudian Admin meninjau dan mencatat bukti transfer manual.'],
        ['Pembayaran sudah disimulasikan tetapi masih menunggu?','Buka detail pesanan lalu klik Periksa Status Pembayaran. Aplikasi meminta status langsung dari Midtrans; menutup popup tidak membatalkan atau mengesahkan pembayaran.'],
    ] as [$question,$answer])
        <details class="faq-item"><summary>{{ $question }}<span class="material-symbols-outlined" aria-hidden="true">expand_more</span></summary><p>{{ $answer }}</p></details>
    @endforeach
</section><div class="site-cta"><h2>Siap memulai perjalanan hijaumu?</h2><a class="button" href="{{ route('catalog') }}"><span>Jelajahi Katalog</span><span class="btn-arrow" aria-hidden="true">→</span></a></div>
@endsection
