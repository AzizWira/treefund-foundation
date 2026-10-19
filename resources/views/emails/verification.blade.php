@extends('emails.layout')
@section('content')
<h1 style="font:700 30px/1.25 Georgia,serif;margin:0 0 18px;color:#1a4830">Satu langkah lagi,<br>perjalanan hijaumu dimulai.</h1><p style="font-size:14px;line-height:1.8">Halo {{ $recipientName }}, konfirmasikan email Anda dengan kode berikut atau tombol verifikasi di bawah. <strong>Cukup gunakan salah satunya.</strong></p>
<div style="text-align:center;border:1px dashed #99b79a;background:#f0f7ec;border-radius:10px;padding:23px 12px;margin:22px 0"><span style="font-size:10px;color:#6c876e;letter-spacing:2px">KODE VERIFIKASI ANDA</span><p style="font-size:36px;letter-spacing:8px;font-weight:bold;color:#215a39;margin:12px 0">{{ $payload['code'] }}</p><span style="font-size:11px;color:#637c66">Berlaku {{ $payload['minutes'] }} menit · Sekali pakai</span></div><p style="font-size:12px;line-height:1.7;color:#6d7e70">Jika Anda tidak membuat akun ini, abaikan email ini. Jangan membagikan kode kepada orang lain.</p>
@endsection
