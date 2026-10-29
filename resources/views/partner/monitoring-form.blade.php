<form method="post" action="{{ route('partner.monitoring.store', $tree) }}" enctype="multipart/form-data" class="form-stack">
    @csrf
    @if($entry)<input type="hidden" name="monitoring_id" value="{{ $entry->id }}">@endif
    <label>Tanggal pengamatan<input type="date" name="observed_at" min="{{ $tree->planting->planted_at->toDateString() }}" max="{{ now()->toDateString() }}" value="{{ $entry?->observed_at->toDateString() }}" required></label>
    <label>Tinggi (cm)<input type="number" name="height" min="0" max="100000" step="0.01" value="{{ $entry?->height }}" required></label>
    <label>Kondisi<select name="health" required>@foreach(['healthy' => 'Sehat', 'care' => 'Perlu perawatan', 'dead' => 'Mati'] as $value => $label)<option value="{{ $value }}" @selected($entry?->health === $value)>{{ $label }}</option>@endforeach</select></label>
    <label>Catatan perawatan<textarea name="care_note" maxlength="2000" required>{{ $entry?->care_note }}</textarea></label>
    <label>Foto<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
    <button class="button">{{ $entry ? 'Kirim revisi monitoring' : 'Kirim laporan monitoring' }}</button>
</form>
