@extends('emails.layout')
@section('content')
<div style="width:48px;padding:14px 0;border-radius:12px;background:#fbf1dc;text-align:center;color:#8c611d;font-size:24px">↻</div><h1 style="font:700 29px/1.3 Georgia,serif;margin:20px 0 14px">Mari pulihkan akses Anda.</h1><p style="font-size:14px;line-height:1.8">Halo {{ $recipientName }}, {{ $payload['body'] }}</p><div style="padding:16px 18px;background:#fcf8ee;border-left:3px solid #bc995a;font-size:13px;line-height:1.8">Tautan berlaku <strong>{{ $payload['minutes'] }} menit</strong> dan hanya dapat digunakan sekali. Kata sandi Anda belum berubah sampai formulir reset diselesaikan.</div><p style="font-size:12px;color:#748173;line-height:1.8">Tidak meminta reset? Abaikan email ini dan tetap gunakan kata sandi Anda saat ini.</p>
@endsection
