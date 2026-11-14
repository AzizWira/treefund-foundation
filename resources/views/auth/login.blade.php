<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk · TreeFund</title>@vite(['resources/css/app.css','resources/js/app.js'])<link rel="preload" href="/assets/fonts/material-symbols-outlined.ttf" as="font" type="font/ttf" crossorigin><link rel="stylesheet" href="/assets/fonts/fonts.css?v=2"></head><body><div class="auth-shell"><section class="auth-art"><a class="brand" href="/"><img src="{{ asset('assets/logo.png') }}" alt="TreeFund"><strong>TreeFund</strong></a><span class="eyebrow">TUMBUH BERSAMA ALAM</span><h1>Setiap pohon punya cerita.<br>Mulai ceritamu di sini.</h1><p>Adopsi pohon, dukung kelompok tani, dan ikuti pertumbuhannya melalui laporan lapangan yang terverifikasi.</p><div class="auth-tree" aria-hidden="true">✳</div><small>Transparansi penanaman, dari akar hingga kanopi.</small></section><section class="auth-form"><a class="back-link" href="/">← Kembali ke beranda</a><div class="auth-form-inner"><span class="eyebrow">SELAMAT DATANG KEMBALI</span><h1>Masuk ke TreeFund</h1><p class="muted">Lanjutkan perjalanan hijaumu hari ini.</p>@if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif @if(session('status'))<div class="flash success">{{ session('status') }}</div>@endif<form method="post" action="/login" class="form-stack">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required autofocus autocomplete="email"></label><label>Kata sandi<input type="password" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password"></label><label class="check"><input type="checkbox" name="remember" value="1">Ingat saya di perangkat ini</label><a class="text-link" href="{{ route('password.request') }}">Lupa kata sandi?</a><button class="button w-full" type="submit"><span>Masuk ke dashboard</span><span class="btn-arrow" aria-hidden="true">→</span></button></form><p class="auth-switch">Belum punya akun? <a href="/register">Daftar sekarang</a></p>
@php
    $demoLabels = [
        'donatur@treefund.test' => 'Donatur',
        'mitra@treefund.test' => 'Mitra',
        'admin@treefund.test' => 'Admin',
    ];
    $demoPasswords = app()->environment('local') ? array_fill_keys(array_keys($demoLabels), 'TreeFund!2026') : [];
    $demoFile = config('treefund.demo_accounts_file');
    if (is_string($demoFile) && $demoFile !== '' && is_file($demoFile) && is_readable($demoFile)) {
        $demoPasswords = [];
        $demoEntries = json_decode(file_get_contents($demoFile), true);
        foreach (is_array($demoEntries) ? $demoEntries : [] as $demoEntry) {
            $email = $demoEntry['email'] ?? null;
            $password = $demoEntry['password'] ?? null;
            if (is_string($email) && isset($demoLabels[$email]) && is_string($password) && $password !== '') {
                $demoPasswords[$email] = $password;
            }
        }
    }
@endphp
@if($demoPasswords)
    <div class="info-box">
        <strong>Akun demo presentasi</strong>
        <p>Pilih peran untuk mengisi email dan kata sandi akun demo.</p>
        <div class="action-row demo-role-actions">
            @foreach($demoLabels as $email => $label)
                @isset($demoPasswords[$email])
                    <button type="button" class="button secondary small" data-demo-account="{{ $email }}" data-demo-password="{{ $demoPasswords[$email] }}">{{ $label }}</button>
                @endisset
            @endforeach
        </div>
    </div>
@endif
</div><footer class="auth-footer"><span>© {{ date('Y') }} TreeFund · Tumbuh bersama, pulihkan bumi.</span></footer></section></div></body></html>