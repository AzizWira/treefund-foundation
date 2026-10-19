@if($paginator->hasPages())
<nav class="pagination action-row" aria-label="Halaman data">
    <span class="muted">{{ $paginator->total() }} data · halaman {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if($paginator->onFirstPage())<button class="button secondary small" type="button" disabled><span class="btn-arrow" aria-hidden="true">←</span><span>Sebelumnya</span></button>@else<a class="button secondary small" href="{{ $paginator->previousPageUrl() }}"><span class="btn-arrow" aria-hidden="true">←</span><span>Sebelumnya</span></a>@endif
    @foreach($elements as $element)
        @if(is_string($element))<span class="muted">{{ $element }}</span>@endif
        @if(is_array($element))
            @foreach($element as $page=>$url)
                <a class="button small {{ $page===$paginator->currentPage()?'':'secondary' }}" href="{{ $url }}" @if($page===$paginator->currentPage()) aria-current="page" @endif>{{ $page }}</a>
            @endforeach
        @endif
    @endforeach
    @if($paginator->hasMorePages())<a class="button secondary small" href="{{ $paginator->nextPageUrl() }}"><span>Berikutnya</span><span class="btn-arrow" aria-hidden="true">→</span></a>@else<button class="button secondary small" type="button" disabled><span>Berikutnya</span><span class="btn-arrow" aria-hidden="true">→</span></button>@endif
</nav>
@endif
