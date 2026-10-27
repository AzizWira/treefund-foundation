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
        <div class="work-row"><strong>{{ $tree->code }}</strong><x-badge :status="$work->status"/></div>
    @endforeach
</section>
@endsection
