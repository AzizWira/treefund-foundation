@extends('emails.layout')
@section('content')
<div style="background:#e8f2e3;border-radius:10px;padding:25px;color:#1c5735"><span style="font-size:34px">❧</span><h1 style="font:700 29px/1.3 Georgia,serif;margin:12px 0 0">Ada kabar dari pohon Anda.</h1></div><p style="font-size:14px;line-height:1.8;margin-top:24px">Halo {{ $recipientName }}, {{ $payload['body'] }}</p>@if(isset($payload['tree_code']))<div style="padding:16px 0;border-bottom:1px solid #e4ebdf;font-size:12px"><span style="color:#6d7e70">Identitas pohon</span><br><strong>{{ $payload['tree_code'] }}</strong></div>@endif<p style="font-size:12px;line-height:1.8;color:#6d7e70">Laporan ini telah diperiksa Admin. Foto lapangan dan timeline lengkap dapat dilihat dengan masuk ke portofolio Anda.</p>
@endsection
