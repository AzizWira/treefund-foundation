@extends('layouts.app')
@section('title', 'Log Monitoring')
@section('content')
<div class="page-heading"><div><span class="eyebrow">PEMANTAUAN POHON</span><h1>Log Monitoring</h1><p>Laporkan kondisi dan perawatan pohon yang penanamannya sudah diverifikasi.</p></div></div>
@forelse($trees as $tree)
    <section class="panel">
        <h2>{{ $tree->code }} · {{ $tree->item->offer->species }}</h2>
        @foreach($tree->monitorings as $report)
            <div class="work-row">
                <div>{{ $report->observed_at->format('d M Y') }} · {{ $report->care_note }} <x-badge :status="$report->status"/></div>
                <a href="{{ route('files', ['type' => 'monitoring', 'id' => $report->id, 'preview' => 1]) }}">Tinjau foto</a>
            </div>
            @if($report->status === 'revision')
                <p>{{ $report->review_note }}</p>
                @include('partner.monitoring-form', ['entry' => $report])
            @endif
        @endforeach
        @include('partner.monitoring-form', ['entry' => null])
    </section>
@empty
    <div class="panel"><x-empty title="Belum ada pohon siap dimonitor"/></div>
@endforelse
{{ $trees->links() }}
@endsection
