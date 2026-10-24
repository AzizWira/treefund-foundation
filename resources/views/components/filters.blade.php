@props(['statuses'=>[], 'reset'=>null, 'prefix'=>''])
<form method="get" class="filter-bar" role="search" aria-label="Filter data">
@foreach(request()->except([$prefix.'q',$prefix.'status',$prefix.'page']) as $key=>$value)
@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
@endforeach
<div class="search-field"><span class="material-symbols-outlined">search</span><input name="{{ $prefix }}q" value="{{ request($prefix.'q') }}" placeholder="Cari data..." aria-label="Cari data"></div>
@if($statuses)<select name="{{ $prefix }}status" aria-label="Status pengajuan"><option value="">Semua status</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(request($prefix.'status')===$value)>{{ $label }}</option>@endforeach</select>@endif
<button class="button secondary small" type="submit" data-filter-submit>Terapkan Filter</button><a class="button secondary small filter-reset-btn" href="{{ $reset ?? request()->fullUrlWithoutQuery([$prefix.'q',$prefix.'status',$prefix.'page']) }}"><span class="material-symbols-outlined">restart_alt</span>Reset / Hapus Filter</a>
</form>