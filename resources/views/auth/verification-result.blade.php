@extends('layouts.auth')
@section('title',$success ? 'Email berhasil diverifikasi' : 'Tautan sudah tidak berlaku')
@section('content')
@if($success)
<p>Terima kasih. Alamat email telah terkonfirmasi. Silakan lanjutkan ke akun TreeFund Anda.</p><a class="button w-full" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Lanjut ke Akun Saya →</a>
@else
<p>Tautan mungkin kedaluwarsa atau telah diganti oleh email yang lebih baru. Masuk ke akun Anda untuk meminta kode dan tautan verifikasi baru.</p><a class="button w-full" href="{{ auth()->check() ? route('verification.notice') : route('login') }}">Minta Verifikasi Baru →</a>
@endif
@endsection
