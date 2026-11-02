@extends('emails.layout')
@section('content')
<h1 style="font:700 29px/1.3 Georgia,serif;margin:0 0 18px">{{ $payload['title'] }}</h1><p style="font-size:14px;line-height:1.8">Halo {{ $recipientName }},</p><div style="padding:22px;background:#f1f6ed;border-radius:8px;font-size:14px;line-height:1.8">{{ $payload['body'] }}</div>
@endsection
