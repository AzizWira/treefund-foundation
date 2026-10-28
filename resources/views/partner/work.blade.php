@extends('layouts.app')
@section('title', 'Detail Tugas Tanam')
@section('content')
<a href="{{ route('partner.works') }}" class="back-link">← Daftar tugas tanam</a>
<div class="page-heading">
    <div><span class="eyebrow">PENUGASAN PENANAMAN</span><h1>WO-{{ $work->id }}</h1>
    <p>{{ $work->item->offer->plot->name }} · {{ $work->item->quantity }} pohon {{ $work->item->offer->species }}</p></div>
    <x-badge :status="$work->status"/>
</div>
<section class="panel">
    <h2>Pohon dalam tugas ini</h2>
    @foreach($work->item->trees as $tree)
<div class="work-row">
    <div><strong>{{ $tree->code }}</strong><x-badge :status="$tree->planting?->status ?? 'waiting'"/></div>
    @if($tree->planting)
        <a href="{{ route('files', ['type' => 'planting', 'id' => $tree->planting->id, 'preview' => 1]) }}">Tinjau bukti</a>
        @if($tree->planting->review_note)
            <p>{{ $tree->planting->review_note }}</p>
        @endif
    @endif
</div>
@if(!$tree->planting || $tree->planting->status === 'revision')
<form action="{{ route('partner.planting', $tree) }}" method="post" enctype="multipart/form-data" class="form-stack">
    @csrf
    <label>Tanggal tanam<input type="date" name="planted_at" max="{{ now()->toDateString() }}" required></label>
    <label>Latitude<input type="number" step="any" name="latitude" min="-90" max="90" required></label>
    <label>Longitude<input type="number" step="any" name="longitude" min="-180" max="180" required></label>
    <label>Foto bukti<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
    <button class="button">Kirim bukti tanam</button>
</form>
@endif
    @endforeach
</section>
@endsection
