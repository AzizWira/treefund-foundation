@extends('layouts.app') @section('title','Detail Adopsi') @section('content')<a href="/orders" class="back-link">← Riwayat transaksi</a><div class="page-heading"><div><span class="eyebrow">RINCIAN KONTRIBUSI</span><h1>{{ $order->reference }}</h1><p>{{ $order->created_at->format('d F Y, H:i') }} · Atas nama {{ $order->certificate_name }}</p></div>@if($order->status==='pending')<span class="badge badge-amber">Menunggu pembayaran</span>@else<x-badge :status="$order->status"/>@endif</div>@if($order->is_demo)<div class="info-box">Pesanan ini merupakan data demonstrasi. Bukan hasil transaksi Midtrans.</div>@endif<div class="form-layout"><section class="panel"><h2>Rincian Pohon Adopsi</h2>@foreach($order->items as $item)<div class="work-row"><span class="work-symbol material-symbols-outlined">eco</span><div><h3>{{ $item->offer->species }}</h3><p>{{ $item->offer->plot->name }} · {{ $item->quantity }} pohon</p></div><strong>Rp {{ number_format($item->unit_price*$item->quantity,0,',','.') }}</strong></div> @endforeach</section><aside class="panel"><h2>Ringkasan Pembayaran</h2><div class="summary-total"><span>Total</span><strong>Rp {{ number_format($order->total,0,',','.') }}</strong></div>@if($order->status === 'pending')<p>Batas waktu: {{ $order->expires_at->format('d M Y, H:i') }}</p>
@endif

@if(config('treefund.midtrans.enabled') && !$order->is_demo && $order->status === 'pending' && $order->expires_at->isFuture())
<x-payment-methods :order="$order"/><p data-payment-message role="status"></p>
<div data-payment-watch data-sync-url="{{ route('orders.sync',$order) }}" data-payment-issued="{{ $order->snap_token ? 'true' : 'false' }}">
<p data-payment-watch-status role="status"></p>
</div>
<form method="post" action="{{ route('orders.sync',$order) }}">@csrf<button class="button secondary">Periksa Status Pembayaran</button></form>
@push('scripts')
<script src="{{ config('treefund.midtrans.script_url') }}" data-client-key="{{ config('treefund.midtrans.client_key') }}"></script>
@endpush

@endif

@if($order->status === 'paid')
    <a class="button secondary" href="{{ route('orders.certificate', [$order, 'preview' => 1]) }}">Unduh E-Sertifikat</a>
    <a class="button secondary" href="{{ route('trees') }}">Pantau Pohon</a>
@endif
</aside></div>@endsection
