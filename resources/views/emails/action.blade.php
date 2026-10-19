@if(!empty($payload['url']))
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:26px 0 12px"><tr><td style="border-radius:6px;background:{{ $accent }}"><a href="{{ $payload['url'] }}" style="display:inline-block;color:#fff;text-decoration:none;font-weight:bold;font-size:13px;padding:15px 24px">{{ $payload['action'] ?? 'Lihat Detail' }} →</a></td></tr></table>
@if(in_array($kind,['verification','password_reset']))
<p style="font-size:11px;color:#7b897e;line-height:1.7;word-break:break-all">Jika tombol tidak dapat dibuka, salin tautan ini:<br><a href="{{ $payload['url'] }}" style="color:#477657">{{ $payload['url'] }}</a></p>
@endif
@endif
