@extends('layouts.site')
@section('title','Peta Lahan Agroforestri')
@section('content')
<div class="site-intro"><span class="eyebrow">JELAJAHI AGROFORESTRI NUSANTARA</span><h1>Temukan lahan, lihat peluang tumbuhnya.</h1><p>Lokasi lahan yang telah disetujui dan memiliki penawaran aktif. Peta ini menampilkan lokasi lahan, bukan titik pohon pribadi milik Donatur.</p></div>
<section class="panel public-map-panel"><div class="panel-heading"><h2>Persebaran Lahan Mitra</h2><span class="badge badge-green">Lahan terverifikasi</span></div><div id="public-map" class="public-map" data-map-url="{{ route('public.map.data') }}"><button type="button" class="button secondary small map-reset" data-public-map-fit>Tampilkan semua lahan</button></div><p data-public-map-status class="muted" role="status">Memuat lahan yang tersedia…</p></section>
<div class="site-cta"><div><h2>Sudah memiliki pohon adopsi?</h2><p>Laporan dan titik tanam pribadi tersedia di portofolio akun Donatur.</p></div><a class="button secondary" href="{{ route('trees') }}"><span>Buka Portofolio Saya</span><span class="btn-arrow" aria-hidden="true">→</span></a><a class="button" href="{{ route('catalog') }}">Lihat Katalog</a></div>
@endsection
