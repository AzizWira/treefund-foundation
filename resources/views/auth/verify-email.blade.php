@extends('layouts.auth')
@section('title','Verifikasi email Anda')
@section('content')
<p>Kode dan tautan dikirim ke <strong>{{ $user->email }}</strong>. Pilih salah satu untuk mengaktifkan akun.</p>
<form method="post" action="{{ route('verification.code') }}" class="form-stack">@csrf<label>Kode enam digit<input class="verification-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="000000" required></label><button class="button w-full">Verifikasi dengan Kode</button></form>
<div class="info-box">Kode dan tautan berlaku 15 menit. Setelah salah satunya berhasil, keduanya tidak perlu digunakan lagi.</div>
<form method="post" action="{{ route('verification.send') }}">@csrf<button class="button secondary w-full">Kirim Ulang Kode & Tautan</button></form><p class="muted">Pengiriman ulang tersedia setiap 60 detik. Periksa folder spam atau pastikan alamat email benar.</p>
<div class="action-row"><a class="text-link" href="{{ route('settings') }}">Ubah Alamat Email</a><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="text-link">Keluar akun</button></form></div>
@endsection
