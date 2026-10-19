@extends('layouts.auth')
@section('title','Lupa kata sandi?')
@section('content')
<p>Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat kata sandi baru.</p><form method="post" action="{{ route('password.email') }}" class="form-stack">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label><button class="button w-full">Kirim Tautan Reset</button></form><p class="auth-switch"><a href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
@endsection
