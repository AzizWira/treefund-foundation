<header class="site-header">
    <div class="site-header-inner">
        <a class="site-brand" href="{{ route('home') }}"><img src="{{ asset('assets/logo.png') }}" alt="Logo TreeFund"><strong>TreeFund</strong></a>
        <button class="site-menu-toggle icon-button" type="button" data-public-menu aria-label="Buka menu" aria-controls="website-nav" aria-expanded="false"><span class="material-symbols-outlined" aria-hidden="true">menu</span></button>
            <nav id="website-nav" class="site-nav" aria-label="Navigasi website">
                @foreach([['home','Beranda'],['catalog','Katalog Lahan'],['public.map','Peta Lahan'],['about','Tentang Kami'],['faq','FAQ']] as [$name,$label])
                    <a href="{{ route($name) }}" @if(request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        <div class="site-account">
            @auth
                <a class="button small" href="{{ route('dashboard') }}"><span class="material-symbols-outlined" aria-hidden="true">account_circle</span>Dashboard Saya</a>
            @else
                <a class="text-link" href="{{ route('login') }}">Masuk</a><a class="button small" href="{{ route('register') }}">Mulai Adopsi</a>
            @endauth
        </div>
    </div>
</header>
