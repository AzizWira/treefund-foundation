@extends('layouts.app')
@section('title','Pengajuan Pencairan Dana')
@section('content')
@php
$stages = [
 'paid'=>['TAHAP 1 · PERSIAPAN & BIBIT','Persiapan Lahan & Distribusi Bibit','Pembayaran adopsi dikonfirmasi dan Work Order diterbitkan.','Pengadaan bibit unggul, pupuk organik dasar, dan persiapan lubang tanam.','spa'],
 'planted'=>['TAHAP 2 · REALISASI TANAM','Penanaman & Verifikasi Geotagging','Seluruh bukti tanam dan titik koordinat GPS disetujui Admin.','Operasional penanaman kelompok tani, pemasangan label kode pohon, dan pemeliharaan awal.','forest'],
 'monitored'=>['TAHAP 3 · EVALUASI MONITORING','Pemeliharaan & Monitoring Berkala','Seluruh pohon memiliki laporan Log Monitoring yang disetujui Admin.','Insentif perawatan lanjutan, penyulaman bibit, dan pelaporan berkala.','monitor_heart'],
];
@endphp
<div class="page-heading"><div><span class="eyebrow">PENDANAAN SESUAI PROGRES</span><h1>Pencairan Dana Bertahap</h1><p>Ajukan termin berdasarkan milestone pekerjaan yang sudah dipenuhi.</p></div></div>
<div class="info-box termin-proposal-banner"><strong>Skema Proposal Operasional Pencairan Dana (3 Tahap Pencapaian)</strong><p>Dana operasional kelompok tani dicairkan dalam 3 tahap pencapaian: <strong>Tahap 1 ({{ $rules->firstWhere('milestone','paid')?->percentage }}%)</strong> saat Work Order terbit untuk pengadaan bibit, <strong>Tahap 2 ({{ $rules->firstWhere('milestone','planted')?->percentage }}%)</strong> setelah bukti tanam &amp; GPS terverifikasi, dan <strong>Tahap 3 ({{ $rules->firstWhere('milestone','monitored')?->percentage }}%)</strong> setelah laporan Log Monitoring berkala disetujui Admin.</p></div>
<x-filters/>
@forelse($works as $work)
<section class="panel"><div class="panel-heading"><div><h2>WO-{{ $work->id }} · {{ $work->item->offer->species }}</h2><p>{{ $work->item->offer->plot->name }}</p></div><strong>Alokasi Rp {{ number_format($work->allocated_amount,0,',','.') }}</strong></div>
<div class="termin-grid">
@foreach($rules as $rule)
@php
$fund=$work->disbursements->firstWhere('termin_rule_id',$rule->id);
$met=app(\App\Services\DisbursementService::class)->eligible($work,$rule);
$eligible=$met && $work->allocated_amount>0;
[$stage,$title,$milestone,$use,$icon]=$stages[$rule->milestone];
@endphp
<article class="termin-card {{ $fund?->status==='paid'?'termin-paid':($met?'termin-ready':'') }}">
<div class="termin-card-top"><span class="termin-stage-pill">{{ $stage }}</span><span class="termin-step-icon material-symbols-outlined">{{ $icon }}</span></div>
<div class="termin-card-header"><div><span class="eyebrow">{{ $rule->name }}</span><h4 class="termin-stage-title">{{ $title }}</h4></div><div class="termin-percentage">{{ $rule->percentage }}%</div></div>
<h3 class="termin-amount">Rp {{ number_format(intdiv($work->allocated_amount*$rule->percentage,100),0,',','.') }}</h3>
<div class="termin-milestone-box"><div class="termin-milestone-status"><span class="material-symbols-outlined">{{ $met?'check_circle':'schedule' }}</span><span>{{ $met?'Tahap Pencapaian Terpenuhi':'Menunggu Pemenuhan Tahap' }}</span></div><p>Syarat Pencapaian: {{ $milestone }}</p><small class="termin-use-note">Peruntukan: {{ $use }}</small></div>
@if($fund)<div class="termin-fund-status"><x-badge :status="$fund->status"/>@if($fund->note)<p>{{ $fund->note }}</p>@endif @if($fund->proof)<a href="/files/transfer/{{ $fund->id }}?preview=1" class="text-link" target="_blank" rel="noopener">Lihat bukti transfer ↗</a>@endif</div>@endif
@if(!$fund || $fund->status==='revision')<form method="post" action="/partner/funds">@csrf<input type="hidden" name="work_order_id" value="{{ $work->id }}"><input type="hidden" name="termin_rule_id" value="{{ $rule->id }}"><button class="button w-full" @disabled(!$eligible)>{{ $eligible?'Ajukan Pencairan':($work->allocated_amount===0?'Menunggu Alokasi Admin':'Milestone Belum Terpenuhi') }}</button></form>@endif
</article>
@endforeach
</div></section>
@empty<section class="panel"><x-empty title="Belum ada alokasi penugasan"/></section>@endforelse
{{ $works->links() }}
@endsection
