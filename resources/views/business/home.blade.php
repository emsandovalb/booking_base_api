@extends('pwa.layout', ['experience' => 'app'])
@section('title', 'Inicio · '.$business->name)
@section('content')
@include('business._header')
@php
    $brand = \App\Support\BrandingConfig::resolveForBusiness($business);
    $logoPath = data_get($brand, 'assets.logo_transparent');
    $logoUrl = $logoPath ? (str_starts_with($logoPath, 'http') ? $logoPath : asset(ltrim($logoPath, '/'))) : route('pwa.icon', ['slug' => $business->slug, 'size' => 192]);
@endphp
<main>
    <section class="owner-hero">
        <img class="hero-logo" src="{{ $logoUrl }}" alt="Logo de {{ $business->name }}">
        <span class="eyebrow">Centro administrativo</span>
        <h2>{{ $business->name }}</h2>
        <p>{{ now()->locale('es')->translatedFormat('l, j \d\e F') }}</p>
    </section>
    <section class="owner-grid" aria-label="Resumen de hoy">
        <article class="owner-card metric-card"><span class="metric-icon"><span class="material-symbols-rounded" aria-hidden="true">calendar_today</span></span><div><strong>{{ $today->count() }}</strong><span>Citas hoy</span></div></article>
        <article class="owner-card metric-card"><span class="metric-icon"><span class="material-symbols-rounded" aria-hidden="true">hourglass_top</span></span><div><strong>{{ $pending }}</strong><span>Pendientes</span></div></article>
        <article class="owner-card metric-card"><span class="metric-icon"><span class="material-symbols-rounded" aria-hidden="true">content_cut</span></span><div><strong>{{ $serviceCount }}</strong><span>Servicios activos</span></div></article>
        <article class="owner-card metric-card"><span class="metric-icon"><span class="material-symbols-rounded" aria-hidden="true">group</span></span><div><strong>{{ $staffCount }}</strong><span>Profesionales</span></div></article>
    </section>
    @if($next)<section><div class="section-head"><h3>Próxima cita</h3><a class="text-link" href="{{ route('business.agenda',$business->slug) }}">Ver agenda</a></div><div class="appointment-list">@include('business._appointment', ['booking' => $next])</div></section>@endif
    <section>
        <div class="section-head"><h3>Agenda de hoy</h3><a class="text-link" href="{{ route('business.agenda',$business->slug) }}">Ver todas</a></div>
        <div class="appointment-list">
            @forelse($today->take(3) as $booking)@include('business._appointment', ['booking' => $booking])@empty
                <div class="empty-state"><span class="empty-icon"><span class="material-symbols-rounded" aria-hidden="true">event_available</span></span><strong>Agenda despejada</strong><p>No hay citas programadas para hoy.</p></div>
            @endforelse
        </div>
    </section>
    <section><div class="section-head"><h3>Accesos rápidos</h3></div><div class="quick-actions">
        <a class="quick-action" href="{{ route('business.agenda',$business->slug) }}"><span class="material-symbols-rounded" aria-hidden="true">calendar_month</span>Agenda</a>
        <a class="quick-action" href="{{ route('business.services',$business->slug) }}"><span class="material-symbols-rounded" aria-hidden="true">add_circle</span>Servicio</a>
        <a class="quick-action" href="{{ route('business.staff',$business->slug) }}"><span class="material-symbols-rounded" aria-hidden="true">person_add</span>Personal</a>
    </div></section>
    <section class="owner-card install-banner browser-only" data-install style="align-items:center;gap:14px;margin-top:24px"><span class="metric-icon"><span class="material-symbols-rounded" aria-hidden="true">install_mobile</span></span><div style="flex:1"><strong>Instala tu agenda</strong><p class="muted" style="font-size:12px;margin:4px 0 0">Ábrela como una app en tu teléfono.</p></div><button class="button brand" style="min-height:42px;padding:9px 13px" onclick="installPwa()">Instalar</button></section>
</main>
@include('business._nav')
@endsection
