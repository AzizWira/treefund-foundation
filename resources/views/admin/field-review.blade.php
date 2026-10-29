@extends('layouts.app')
@section('title', $heading)
@section('content')
<div class="page-heading"><div><span class="eyebrow">PEMERIKSAAN LAPANGAN</span><h1>{{ $heading }}</h1></div></div>
<x-filters :statuses="['pending' => 'Menunggu', 'approved' => 'Disetujui', 'revision' => 'Perlu revisi']"/>
<section class="panel">
@forelse($records as $record)
    <div class="work-row">
        <div><strong>{{ $record->tree->code }}</strong><p>{{ $record->tree->item->offer->plot->name }}</p><x-badge :status="$record->status"/></div>
        <a class="button secondary small" href="{{ route('files', ['type' => $kind, 'id' => $record->id, 'preview' => 1]) }}">Tinjau bukti</a>
    </div>
    @if($kind === 'planting')
        <p>{{ $record->planted_at->format('d M Y') }} · {{ $record->latitude }}, {{ $record->longitude }}</p>
    @else
        <p>{{ $record->observed_at->format('d M Y') }} · {{ $record->height }} cm · {{ ['healthy'=>'Sehat', 'care'=>'Perlu perawatan', 'dead'=>'Mati'][$record->health] ?? $record->health }}</p>
        <p>{{ $record->care_note }}</p>
    @endif
    @if($record->review_note)<p>{{ $record->review_note }}</p>@endif
    @if($record->status === 'pending')
        <form method="post" action="{{ route($kind === 'planting' ? 'admin.plantings.review' : 'admin.monitorings.review', $record) }}" class="form-stack">
            @csrf
            <input type="hidden" name="submission_version" value="{{ $record->submission_version }}">
            <label>Catatan pemeriksaan<textarea name="review_note" maxlength="2000"></textarea></label>
            <div class="action-button-group">
                <button class="button small" name="decision" value="approved">Setujui</button>
                <button class="button secondary small" name="decision" value="revision">Minta revisi</button>
            </div>
        </form>
    @endif
@empty
    <x-empty title="Tidak ada bukti yang cocok"/>
@endforelse
{{ $records->links() }}
</section>
@endsection
