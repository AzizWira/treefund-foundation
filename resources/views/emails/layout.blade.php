<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $payload['title'] }}</title></head>
@php
$accent = match($kind) {'password_reset','reminder','payment_reminder'=>'#8c611d','certificate'=>'#806126','funds'=>'#245c62',default=>'#286348'};
$category = match($kind) {'verification'=>'VERIFIKASI AKUN','password_reset','security'=>'KEAMANAN AKUN','payment_pending','payment_success','payment_failed'=>'TRANSAKSI ADOPSI','certificate'=>'SERTIFIKAT KONTRIBUSI','field_report'=>'KABAR DARI LAPANGAN','partnership'=>'KEMITRAAN TREEFUND','work_order'=>'PENUGASAN PENANAMAN','funds'=>'PENCAIRAN KEMITRAAN','reminder','payment_reminder'=>'PENGINGAT UNTUK ANDA','admin_digest','admin_request','payment_review'=>'KONSOL VERIFIKATOR',default=>'KABAR TREEFUND'};
@endphp
<body style="margin:0;padding:0;background:#f1f5ee;color:#233c2d;font-family:Arial,Helvetica,sans-serif">
<div style="display:none;font-size:1px;max-height:0;overflow:hidden;color:#f1f5ee">{{ $payload['body'] ?? $payload['title'] }}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5ee"><tr><td align="center" style="padding:36px 12px">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px"><tr><td style="padding:0 6px 20px"><a href="{{ url('/') }}" style="font-family:Georgia,serif;font-size:29px;font-weight:bold;color:#1e5439;text-decoration:none">TreeFund<span style="color:#8ca98d"> ·</span></a><span style="font-size:11px;color:#6c7e70;float:right;padding-top:12px">TUMBUH BERSAMA ALAM</span></td></tr>
<tr><td style="background:#fff;border:1px solid #e0e8dc;border-top:5px solid {{ $accent }};border-radius:12px;padding:34px 30px">
<p style="font-size:10px;font-weight:bold;letter-spacing:2px;color:{{ $accent }};margin:0 0 20px">{{ $category }}</p>
@yield('content')
@include('emails.action')
<p style="font-size:12px;line-height:1.8;color:#748173;margin:26px 0 0">Salam hangat,<br><strong style="color:#35573e">Tim TreeFund</strong></p>
</td></tr><tr><td style="padding:22px 16px;text-align:center;color:#7a897c;font-size:11px;line-height:1.8">Email ini terkait aktivitas akun TreeFund Anda.<br><a href="{{ route('settings') }}" style="color:#477657">Atur pilihan email</a> · <a href="{{ url('/informasi/privasi') }}" style="color:#477657">Privasi</a><br>© {{ date('Y') }} TreeFund · Gotong royong memulihkan bumi.</td></tr></table>
</td></tr></table></body></html>
