@extends('layouts.auth')
@section('title','Buat kata sandi baru')
@section('content')
<p>Pilih kata sandi minimal delapan karakter yang belum digunakan pada akun lain.</p><form method="post" action="{{ route('password.update') }}" class="form-stack">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>Email<input type="email" name="email" value="{{ old('email',request('email')) }}" autocomplete="email" required></label><label>Kata sandi baru<input type="password" name="password" autocomplete="new-password" minlength="8" required></label><label>Konfirmasi kata sandi<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label><button class="button w-full">Simpan Kata Sandi Baru</button></form><p class="auth-switch"><a href="{{ route('password.request') }}">Minta tautan baru</a></p>
@endsection
