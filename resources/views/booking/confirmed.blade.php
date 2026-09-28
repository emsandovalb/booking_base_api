@extends('pwa.layout', ['experience' => 'b'])
@section('title', 'Cita confirmada · '.$business->name)
@section('content')
<header class="topbar"><div class="brandmark">✓</div><div><h1>{{ $business->name }}</h1><p>Reserva recibida</p></div></header>
<main>
    <section class="hero"><span class="eyebrow">Cita confirmada ✓</span><h2>¡Listo, {{ $booking->customer_name }}!</h2><p>Tu solicitud quedó registrada. El negocio puede confirmarla desde su agenda.</p></section>
    <div class="card summary" style="margin-top:24px">
        <div class="summary-row"><span>Código</span><strong>{{ $booking->booking_code }}</strong></div>
        <div class="summary-row"><span>Servicio</span><strong>{{ $booking->court->name }}</strong></div>
        <div class="summary-row"><span>Profesional</span><strong>{{ $booking->staff->name }}</strong></div>
        <div class="summary-row"><span>Fecha</span><strong>{{ $booking->date->locale('es')->translatedFormat('D j M, Y') }}</strong></div>
        <div class="summary-row"><span>Hora</span><strong>{{ $booking->date->format('g:i A') }}</strong></div>
        <div class="summary-row"><span>Estado</span><strong class="pill {{ $booking->status }}">{{ $booking->status }}</strong></div>
    </div>
    <a class="button primary" style="width:100%;margin-top:16px" href="{{ route('booking.public',$business->slug) }}">Hacer otra reserva</a>
    <section class="card install-banner" data-install>
        <h3>¿Quieres reservar más rápido la próxima vez?</h3><p class="muted">Añade {{ $business->name }} a tu pantalla de inicio. Es opcional.</p>
        <button class="button brand" type="button" onclick="installPwa()">Añadir a pantalla de inicio</button>
    </section>
    <section class="card browser-only" id="ios-guide" style="display:none;margin-top:18px"><h3 style="margin-top:0">En iPhone</h3><p class="muted">En Safari toca Compartir y luego “Añadir a pantalla de inicio”. Puedes ignorar este paso.</p></section>
</main>
@endsection
@push('scripts')<script>if(/iphone|ipad|ipod/i.test(navigator.userAgent)&&!matchMedia('(display-mode: standalone)').matches)document.querySelector('#ios-guide').style.display='block';</script>@endpush
