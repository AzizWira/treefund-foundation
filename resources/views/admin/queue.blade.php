@extends('layouts.app') @php $titles=['partners'=>'Verifikasi Profil Kemitraan','plots'=>'Verifikasi Lahan Baru','plantings'=>'Verifikasi Bukti Tanam','monitorings'=>'Verifikasi Monitoring Berkala']; @endphp @section('title',$titles[$type]) @section('content')<div class="page-heading"><div><span class="eyebrow">KONSOL VERIFIKATOR</span><h1>{{ $titles[$type] }}</h1><p>Periksa kelengkapan dokumen, kesesuaian data, dan bukti lapangan sebelum menyetujui.</p></div><span class="badge badge-green">{{ $records->total() }} pengajuan</span></div>@if($work)<div class="info-box">Menampilkan data WO-{{ $work->id }} · {{ $work->partner->group_name }} · {{ $work->item->offer->plot->name }}. <a class="text-link" href="/admin/disbursements">Kembali ke pencairan</a> · <a class="text-link" href="{{ url()->current() }}">Lihat semua pengajuan</a></div>@endif
@if(request('review'))<div class="info-box">Menampilkan pengajuan #{{ request('review') }}. <a href="{{ url()->current() }}" class="text-link">Lihat semua pengajuan</a></div>@endif
<x-filters :statuses="['pending'=>'Menunggu verifikasi','approved'=>'Disetujui','revision'=>'Perlu perbaikan','rejected'=>'Ditolak']"/>

<section class="panel"><div class="table-scroll"><table><thead><tr><th>PENGAJUAN</th><th>KELOMPOK / LAHAN</th><th>TANGGAL</th><th>STATUS</th><th>AKSI</th></tr></thead><tbody>
@forelse($records as $record)
<tr><td><strong>#{{ $record->id }} · {{ match($type){'partners'=>$record->group_name,'plots'=>$record->name,default=>$record->tree->code} }}</strong></td><td>{{ match($type){'partners'=>$record->address,'plots'=>$record->partner->group_name,default=>$record->tree->item->offer->plot->partner->group_name} }}@if(in_array($type,['plantings','monitorings']))<small class="block muted">{{ $record->tree->item->offer->species }} · {{ $record->tree->item->offer->plot->name }}</small>@endif</td><td>{{ $record->created_at->format('d M Y') }}</td><td><x-badge :status="$record->status"/></td><td class="table-action-cell"><button type="button" class="button small secondary" data-review-target="review-{{ $record->id }}">Tinjau Detail →</button></td></tr>
@empty
<tr><td colspan="5"><x-empty title="Tidak ada pengajuan pada filter ini"/></td></tr>
@endforelse
</tbody></table></div>{{ $records->links() }}</section>
@foreach($records as $record)
<dialog class="review-dialog" id="review-{{ $record->id }}" aria-label="Detail pengajuan {{ $record->id }}"><div class="review-dialog-header"><div><span class="eyebrow">{{ $titles[$type] }}</span><strong>Detail Pengajuan #{{ $record->id }}</strong></div><form method="dialog"><button class="icon-button" aria-label="Tutup detail"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></form></div>@include('admin.review')</dialog>
@endforeach
@endsection