@extends('layouts.app')
@section('title', 'Work Order Penanaman')
@section('content')
<div class="page-heading"><div><span class="eyebrow">PENUGASAN LAPANGAN</span><h1>Work Order Penanaman</h1><p>Lihat tugas dari pesanan adopsi yang telah dibayar.</p></div></div>
<x-filters :statuses="['waiting' => 'Menunggu tanam', 'pending' => 'Dalam pemeriksaan', 'revision' => 'Revisi', 'planted' => 'Terverifikasi']"/>
<section class="panel">
    @forelse($works as $work)
        <div class="work-row">
            <div><strong>WO-{{ $work->id }}</strong><p>{{ $work->item->offer->plot->name }} · {{ $work->item->quantity }} pohon {{ $work->item->offer->species }}</p></div>
            <x-badge :status="$work->status"/>
            <a class="button small secondary" href="{{ route('partner.work', $work) }}">Lihat detail</a>
        </div>
    @empty
        <x-empty title="Belum ada tugas tanam"/>
    @endforelse
    {{ $works->links() }}
</section>
@endsection
