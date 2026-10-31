@extends('layouts.app')
@section('title','E-Sertifikat Adopsi')
@section('content')
<div class="page-heading"><div><span class="eyebrow">BUKTI KONTRIBUSI ANDA</span><h1>E-Sertifikat Adopsi</h1><p>Sertifikat tersedia untuk adopsi yang pembayarannya sudah dikonfirmasi.</p></div><a class="button secondary" href="{{ route('orders') }}"><span>Riwayat Transaksi</span><span class="btn-arrow" aria-hidden="true">→</span></a></div>
<x-filters/><div class="certificate-grid">
    @forelse($orders as $order)
        <article class="panel certificate-card"><div class="certificate-emblem"><span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span></div><span class="eyebrow">SERTIFIKAT ADOPSI POHON</span><h2>{{ $order->certificate_name }}</h2><p>{{ $order->certificate->number }}</p><div class="certificate-details">@foreach($order->items as $item)<strong>{{ $item->quantity }} pohon {{ $item->offer->species }}</strong><span>{{ $item->offer->plot->name }}</span>@endforeach</div><small>Diterbitkan {{ $order->paid_at?->format('d M Y') }}</small>@if($order->is_demo)<span class="badge badge-amber">Data demonstrasi</span>@endif<div class="action-button-group justify-center"><a class="button small" href="{{ route('orders.certificate',[$order,'preview'=>1]) }}"><span class="material-symbols-outlined" aria-hidden="true">download</span>Unduh PDF</a><a class="button small secondary" href="{{ route('orders.show',$order) }}"><span>Detail Adopsi</span><span class="btn-arrow" aria-hidden="true">→</span></a></div></article>
    @empty
        <section class="panel"><x-empty title="Belum ada e-sertifikat" text="Selesaikan pembayaran adopsi untuk menerbitkan sertifikat atas nama Anda."/><a class="button" href="{{ route('donor.catalog') }}">Pilih Pohon Adopsi</a></section>
    @endforelse
</div>{{ $orders->links() }}
@endsection
