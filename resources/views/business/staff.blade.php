@extends('pwa.layout', ['experience' => 'app'])
@section('title', 'Personal · '.$business->name)
@section('content')
@include('business._header')
<main>
    <header class="page-head"><div><span class="eyebrow">Equipo</span><h2>Personal</h2><p>{{ $staff->where('is_active', true)->count() }} disponibles para reservas</p></div></header>
    <div class="stack">
    @forelse($staff as $person)
        @php
            $avatar = $person->avatar
                ? (str_starts_with($person->avatar, 'http') ? $person->avatar : (str_starts_with(ltrim($person->avatar, '/'), 'assets/') ? asset(ltrim($person->avatar, '/')) : $person->avatar_url))
                : route('pwa.icon', ['slug' => $business->slug, 'size' => 192]);
        @endphp
        <article class="owner-card">
            <div class="staff-card">
                <img class="staff-avatar" src="{{ $avatar }}" alt="{{ $person->name }}">
                <div><div style="display:flex;align-items:start;justify-content:space-between;gap:8px"><h3>{{ $person->name }}</h3><span class="pill {{ $person->is_active?'confirmed':'cancelled' }}">{{ $person->is_active?'Activo':'Pausado' }}</span></div><div class="muted" style="font-size:12px;margin-bottom:8px">{{ $person->role?->name ?: 'Profesional' }} · {{ $person->courts->count() }} {{ $person->courts->count() === 1 ? 'servicio' : 'servicios' }}</div><span class="staff-status {{ $person->is_active?'active':'' }}">{{ $person->is_active?'Aceptando citas':'Reservas pausadas' }}</span></div>
            </div>
            <form method="post" action="{{ route('business.staff.toggle',[$business->slug,$person->id]) }}" style="margin-top:15px">@csrf @method('PATCH')<button class="button secondary" style="width:100%;min-height:44px">{{ $person->is_active?'Pausar reservas':'Activar reservas' }}</button></form>
        </article>
    @empty<div class="empty-state"><span class="empty-icon"><span class="material-symbols-rounded">group</span></span><strong>Aún no hay personal</strong><p>Agrega al primer profesional del equipo.</p></div>@endforelse
    </div>
</main>
<button class="floating-add" onclick="openSheet('new-staff')" aria-label="Añadir profesional"><span class="material-symbols-rounded">person_add</span></button>
<dialog class="sheet" id="new-staff"><div class="sheet-inner"><div class="sheet-handle"></div><div class="sheet-head"><h2>Nuevo profesional</h2><button class="sheet-close" onclick="closeSheet('new-staff')" aria-label="Cerrar"><span class="material-symbols-rounded">close</span></button></div><form method="post" action="{{ route('business.staff.store',$business->slug) }}" class="stack">@csrf<div class="field"><label>Nombre</label><input name="name" required></div><div class="field"><label>Teléfono</label><input type="tel" name="phone"></div><div class="field"><label>Servicios</label><select name="service_ids[]" multiple required style="min-height:140px">@foreach($business->courts()->where('status','active')->orderBy('name')->get() as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select></div><p class="muted" style="font-size:12px;margin:0">Mantén Ctrl o ⌘ para elegir varios servicios.</p><button class="button brand">Agregar profesional</button></form></div></dialog>
@include('business._nav')
@endsection
