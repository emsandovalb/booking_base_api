@php
    $brand = \App\Support\BrandingConfig::resolveForBusiness($business);
    $logoPath = data_get($brand, 'assets.logo_transparent');
    $logoUrl = $logoPath ? (str_starts_with($logoPath, 'http') ? $logoPath : asset(ltrim($logoPath, '/'))) : route('pwa.icon', ['slug' => $business->slug, 'size' => 192]);
@endphp
<header class="topbar">
    <div class="brandmark"><img src="{{ $logoUrl }}" alt="Logo de {{ $business->name }}"></div>
    <div><h1>{{ $business->name }}</h1><p>Centro administrativo</p></div>
    <div class="top-spacer"></div>
    <a class="icon-button" href="{{ route('business.share', $business->slug) }}" aria-label="Abrir más opciones"><span class="material-symbols-rounded" aria-hidden="true">settings</span></a>
</header>
@if(session('status'))<div class="status" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
