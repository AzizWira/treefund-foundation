@php
    $restoreInput = (string) old('offer_id', '') === (string) ($offer?->id ?? '');
    $selectedPlot = $restoreInput ? old('plot_id', $offer?->plot_id) : $offer?->plot_id;
@endphp
<form method="post" action="/admin/catalog" enctype="multipart/form-data" class="form-stack">
    @csrf
    @if($offer)<input type="hidden" name="offer_id" value="{{ $offer->id }}">@endif
    <div class="form-grid">
        <label>Petak lahan terverifikasi<select name="plot_id" required>
            <option value="">Pilih lahan terverifikasi</option>
            @foreach($plots as $plot)<option value="{{ $plot->id }}" @selected((string) $selectedPlot === (string) $plot->id)>{{ $plot->name }}</option>@endforeach
        </select></label>
        <label>Jenis bibit<input name="species" maxlength="120" value="{{ $restoreInput ? old('species', $offer?->species) : $offer?->species }}" required></label>
    </div>
    <label>Deskripsi penawaran<textarea name="description" rows="3" maxlength="3000" required>{{ $restoreInput ? old('description', $offer?->description) : $offer?->description }}</textarea></label>
    <div class="form-grid">
        <label>Harga adopsi (Rp / pohon)<input type="number" name="price" min="1000" max="100000000" step="1" value="{{ $restoreInput ? old('price', $offer?->price) : $offer?->price }}" required></label>
        <label>Foto bibit / lahan<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, WebP · maksimal 5 MB</small></label>
    </div>
    @if($restoreInput && session()->hasOldInput())
        <label class="check"><input type="checkbox" name="active" value="1" @checked((bool) old('active', false))>Aktifkan penawaran pada katalog publik</label>
    @else
        <label class="check"><input type="checkbox" name="active" value="1" @checked($offer?->active ?? true)>Aktifkan penawaran pada katalog publik</label>
    @endif
    <button class="button" @disabled($plots->isEmpty())>Simpan Penawaran</button>
    @if($plots->isEmpty())<p class="muted">Belum ada lahan dan Mitra terverifikasi. Selesaikan verifikasi sebelum membuat penawaran.</p>@endif
</form>
