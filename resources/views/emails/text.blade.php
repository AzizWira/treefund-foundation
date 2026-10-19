TREEFUND — {{ $payload['title'] }}

Halo {{ $recipientName }},

{{ $payload['body'] ?? '' }}
@if(isset($payload['code']))
Kode verifikasi: {{ $payload['code'] }}
Kode dan tautan berlaku {{ $payload['minutes'] }} menit. Cukup gunakan salah satunya.
@endif
@if(isset($payload['reference']))
Referensi: {{ $payload['reference'] }}
@endif
@if(isset($payload['amount']))
Nominal: Rp {{ number_format($payload['amount'],0,',','.') }}
@endif
@if(isset($payload['deadline']))
Batas pembayaran: {{ $payload['deadline'] }}
@endif
@if(isset($payload['note']))
Catatan: {{ $payload['note'] }}
@endif
@foreach(($payload['stats'] ?? []) as $label=>$count)
{{ $label }}: {{ $count }}
@endforeach

{{ $payload['action'] ?? 'Lihat Detail' }}:
{{ $payload['url'] ?? url('/') }}

Salam hangat,
Tim TreeFund
Pilihan email: {{ route('settings') }}
