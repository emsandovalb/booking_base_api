@extends('pwa.layout', ['experience' => 'app'])
@section('title', 'Servicios · '.$business->name)
@section('content')
@include('business._header')
<main>
    <header class="page-head"><div><span class="eyebrow">Catálogo</span><h2>Servicios</h2><p>{{ $services->count() }} {{ $services->count() === 1 ? 'servicio' : 'servicios' }} configurados</p></div></header>
    <div class="stack">
    @forelse($services as $service)
        @php
            $rawImage = collect((array)$service->images)->first();
            $serviceImage = $rawImage
                ? (str_starts_with($rawImage, 'http') || str_starts_with($rawImage, 'data:')
                    ? $rawImage
                    : (str_starts_with(ltrim($rawImage, '/'), 'assets/') ? asset(ltrim($rawImage, '/')) : asset('storage/'.ltrim($rawImage, '/'))))
                : route('pwa.icon', ['slug' => $business->slug, 'size' => 192]);
        @endphp
        <article class="owner-card service-card">
            <img class="service-image" src="{{ $serviceImage }}" alt="{{ $service->name }}">
            <div><h3>{{ $service->name }}</h3><div class="service-meta"><span><span class="material-symbols-rounded" aria-hidden="true">schedule</span>{{ $service->duration_minutes ?: 60 }} min</span><span><span class="material-symbols-rounded" aria-hidden="true">group</span>{{ $service->staff_count }}</span></div><div class="service-price">₡{{ number_format((float)$service->price_per_hour, 0) }}</div></div>
            <button class="icon-button" onclick="openSheet('service-{{ $service->id }}')" aria-label="Editar {{ $service->name }}"><span class="material-symbols-rounded" aria-hidden="true">edit</span></button>
        </article>
        <dialog class="sheet" id="service-{{ $service->id }}"><div class="sheet-inner"><div class="sheet-handle"></div><div class="sheet-head"><h2>Editar servicio</h2><button class="sheet-close" onclick="closeSheet('service-{{ $service->id }}')" aria-label="Cerrar"><span class="material-symbols-rounded">close</span></button></div><form method="post" action="{{ route('business.services.update',[$business->slug,$service->id]) }}" class="stack">@csrf @method('PUT')
            <div class="field"><label>Nombre</label><input name="name" value="{{ $service->name }}" required></div><div class="grid two"><div class="field"><label>Precio</label><input type="number" step="0.01" min="0" name="price_per_hour" value="{{ $service->price_per_hour }}" required></div><div class="field"><label>Duración (min)</label><input type="number" min="10" name="duration_minutes" value="{{ $service->duration_minutes ?: 60 }}" required></div><div class="field"><label>Abre</label><input type="time" name="open_hour" value="{{ substr($service->open_hour ?: '09:00',0,5) }}" required></div><div class="field"><label>Cierra</label><input type="time" name="close_hour" value="{{ substr($service->close_hour ?: '18:00',0,5) }}" required></div></div><div class="field"><label>Estado</label><select name="status"><option value="active" @selected($service->status==='active')>Activo</option><option value="inactive" @selected($service->status==='inactive')>Inactivo</option></select></div><button class="button brand">Guardar servicio</button>
        </form></div></dialog>
    @empty<div class="empty-state"><span class="empty-icon"><span class="material-symbols-rounded">content_cut</span></span><strong>Aún no hay servicios</strong><p>Crea el primero para empezar a recibir reservas.</p></div>@endforelse
    </div>
</main>
<button class="floating-add" onclick="openSheet('new-service')" aria-label="Añadir servicio"><span class="material-symbols-rounded">add</span></button>
<dialog class="sheet" id="new-service"><div class="sheet-inner"><div class="sheet-handle"></div><div class="sheet-head"><h2>Nuevo servicio</h2><button class="sheet-close" onclick="closeSheet('new-service')" aria-label="Cerrar"><span class="material-symbols-rounded">close</span></button></div><form method="post" action="{{ route('business.services.store',$business->slug) }}" class="stack">@csrf<div class="field"><label>Nombre</label><input name="name" required></div><div class="grid two"><div class="field"><label>Precio</label><input type="number" step="0.01" min="0" name="price_per_hour" required></div><div class="field"><label>Duración (min)</label><input type="number" min="10" name="duration_minutes" value="30" required></div><div class="field"><label>Abre</label><input type="time" name="open_hour" value="09:00" required></div><div class="field"><label>Cierra</label><input type="time" name="close_hour" value="18:00" required></div></div><input type="hidden" name="status" value="active"><button class="button brand">Crear servicio</button></form></div></dialog>
@include('business._nav')
@endsection
