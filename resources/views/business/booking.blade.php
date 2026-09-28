@extends('pwa.layout', ['experience' => 'app'])
@section('title', 'Detalle de cita · '.$business->name)
@section('content')
@include('business._header')
@php
    $statusLabels = ['pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'rejected' => 'Rechazada', 'completed' => 'Completada'];
    $customer = $booking->customer_name ?: $booking->user?->name ?: 'Cliente';
    $phone = $booking->customer_phone ?: $booking->user?->phone;
    $duration = $booking->duration_minutes ?: $booking->court?->duration_minutes ?: 60;
    $price = $booking->total_price ?? $booking->court?->price_per_hour;
@endphp
<main>
    <header class="detail-top"><a class="back-button" href="{{ route('business.agenda', ['slug' => $business->slug, 'date' => $booking->date->toDateString()]) }}" aria-label="Volver"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span></a><div><span class="eyebrow">Reservación</span><h2>Detalle de cita</h2></div></header>
    <section class="owner-card detail-hero"><span class="metric-icon"><span class="material-symbols-rounded" aria-hidden="true">person</span></span><h3>{{ $customer }}</h3><p>{{ $booking->court?->name }}</p><div style="margin-top:13px"><span class="pill {{ $booking->status }}">{{ $statusLabels[$booking->status] ?? ucfirst($booking->status) }}</span></div></section>
    <section class="owner-card detail-list">
        <div class="detail-row"><span class="material-symbols-rounded" aria-hidden="true">call</span><div><span class="detail-label">Teléfono</span><div class="detail-value">@if($phone)<a href="tel:{{ $phone }}">{{ $phone }}</a>@else No disponible @endif</div></div></div>
        <div class="detail-row"><span class="material-symbols-rounded" aria-hidden="true">content_cut</span><div><span class="detail-label">Servicio</span><div class="detail-value">{{ $booking->court?->name }}</div></div></div>
        <div class="detail-row"><span class="material-symbols-rounded" aria-hidden="true">badge</span><div><span class="detail-label">Profesional</span><div class="detail-value">{{ $booking->staff?->name ?: 'Sin asignar' }}</div></div></div>
        <div class="detail-row"><span class="material-symbols-rounded" aria-hidden="true">calendar_today</span><div><span class="detail-label">Fecha y hora</span><div class="detail-value">{{ $booking->date->locale('es')->translatedFormat('l, j \d\e F') }} · {{ $booking->date->format('g:i A') }}</div></div></div>
        <div class="detail-row"><span class="material-symbols-rounded" aria-hidden="true">schedule</span><div><span class="detail-label">Duración</span><div class="detail-value">{{ $duration }} minutos</div></div></div>
        @if($price !== null)<div class="detail-row"><span class="material-symbols-rounded" aria-hidden="true">payments</span><div><span class="detail-label">Precio</span><div class="detail-value">₡{{ number_format((float)$price, 0) }}</div></div></div>@endif
    </section>
    @if(in_array($booking->status, ['pending','confirmed']))
        <section class="detail-actions">
            @if($booking->status === 'pending')<form method="post" action="{{ route('business.bookings.transition',[$business->slug,$booking->id,'confirm']) }}">@csrf<button class="button success" style="width:100%"><span class="material-symbols-rounded" aria-hidden="true">check_circle</span>Confirmar cita</button></form>@endif
            <button class="button secondary" onclick="openSheet('reschedule-sheet')"><span class="material-symbols-rounded" aria-hidden="true">edit_calendar</span>Reprogramar</button>
            <button class="button danger" onclick="openSheet('cancel-sheet')"><span class="material-symbols-rounded" aria-hidden="true">cancel</span>Cancelar cita</button>
        </section>
    @endif
</main>
@if(in_array($booking->status, ['pending','confirmed']))
<dialog class="sheet" id="reschedule-sheet"><div class="sheet-inner"><div class="sheet-handle"></div><div class="sheet-head"><h2>Reprogramar cita</h2><button class="sheet-close" onclick="closeSheet('reschedule-sheet')" aria-label="Cerrar"><span class="material-symbols-rounded">close</span></button></div><form method="post" action="{{ route('business.bookings.reschedule',[$business->slug,$booking->id]) }}" class="stack">@csrf<div class="field"><label for="new-date">Nueva fecha</label><input id="new-date" type="date" name="date" min="{{ today()->toDateString() }}" value="{{ $booking->date->toDateString() }}" required></div><div class="field"><label for="new-time">Nueva hora</label><input id="new-time" type="time" name="time" value="{{ $booking->date->format('H:i') }}" required></div><button class="button brand">Guardar cambio</button></form></div></dialog>
<dialog class="sheet" id="cancel-sheet"><div class="sheet-inner"><div class="sheet-handle"></div><div class="sheet-head"><h2>Cancelar cita</h2><button class="sheet-close" onclick="closeSheet('cancel-sheet')" aria-label="Cerrar"><span class="material-symbols-rounded">close</span></button></div><p class="muted">La cita de {{ $customer }} quedará marcada como cancelada.</p><form method="post" action="{{ route('business.bookings.transition',[$business->slug,$booking->id,'cancel']) }}">@csrf<button class="button danger" style="width:100%;margin-top:16px">Sí, cancelar cita</button></form></div></dialog>
@endif
@include('business._nav')
@endsection
