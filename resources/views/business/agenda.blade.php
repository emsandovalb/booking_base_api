@extends('pwa.layout', ['experience' => 'app'])
@section('title', 'Agenda · '.$business->name)
@section('content')
@include('business._header')
<main>
    <header class="page-head"><div><span class="eyebrow">Agenda</span><h2>{{ $selectedDate->isToday() ? 'Citas de hoy' : $selectedDate->locale('es')->translatedFormat('j \d\e F') }}</h2><p>{{ $bookings->count() }} {{ $bookings->count() === 1 ? 'reservación' : 'reservaciones' }}</p></div></header>
    <form method="get" class="date-control"><span class="material-symbols-rounded" aria-hidden="true">date_range</span><input aria-label="Día de la agenda" type="date" name="date" value="{{ $selectedDate->toDateString() }}"><button type="submit">Ver</button></form>
    <div class="appointment-list">
        @forelse($bookings as $booking)@include('business._appointment', ['booking' => $booking])@empty
            <div class="empty-state"><span class="empty-icon"><span class="material-symbols-rounded" aria-hidden="true">event_busy</span></span><strong>Sin citas este día</strong><p>Selecciona otra fecha para revisar la agenda.</p></div>
        @endforelse
    </div>
</main>
@include('business._nav')
@endsection
