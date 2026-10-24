@extends('layouts.app')
@section('title',$title)
@section('content')
<div class="page-heading"><div><span class="eyebrow">BERKAS TREEFUND</span><h1>{{ $title }}</h1><p>Dokumen dan dokumentasi kegiatan yang terkait dengan akun Anda.</p></div><button type="button" class="button secondary" data-back>← Kembali</button></div>
<section class="panel">
@if(str_starts_with($mime,'image/'))<img class="evidence-image" src="{{ route('files',['type'=>$type,'id'=>$id]) }}" alt="{{ $title }}">
@elseif($mime==='application/pdf')<iframe class="document-frame" src="{{ route('files',['type'=>$type,'id'=>$id]) }}" title="{{ $title }}"></iframe>
@else<div class="info-box"><span class="material-symbols-outlined">description</span><h2>{{ $title }}</h2><p>Unduh berkas untuk membaca dokumen.</p></div>@endif
<a class="button" href="{{ route('files',['type'=>$type,'id'=>$id,'download'=>1]) }}">Unduh Berkas</a>
</section>
@endsection
