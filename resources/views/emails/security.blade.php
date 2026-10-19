@extends('emails.layout')
@section('content')
<h1 style="font:700 29px/1.3 Georgia,serif;margin:0 0 16px">{{ $payload['title'] }}</h1><p style="font-size:14px;line-height:1.8">Halo {{ $recipientName }},</p><div style="background:#f0f6ec;padding:22px;border-radius:8px;border-left:4px solid #4b8558"><p style="font-size:14px;line-height:1.8;margin:0">{{ $payload['body'] }}</p><p style="font-size:11px;color:#6b7c6e;margin:18px 0 0">Dicatat pada {{ $payload['occurred_at'] ?? now()->format('d M Y, H:i') }} ({{ config('app.timezone') }})</p></div>
@endsection
