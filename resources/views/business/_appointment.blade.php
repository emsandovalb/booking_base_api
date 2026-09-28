@php
    $statusLabels = ['pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'rejected' => 'Rechazada', 'completed' => 'Completada'];
    $customer = $booking->customer_name ?: $booking->user?->name ?: 'Cliente';
@endphp
<a class="appointment-row" href="{{ route('business.bookings.show', [$business->slug, $booking->id]) }}" aria-label="Ver cita de {{ $customer }} a las {{ $booking->date->format('g:i A') }}">
    <div class="appointment-time">{{ $booking->date->format('g:i') }}<small>{{ $booking->date->format('A') }}</small></div>
    <div class="appointment-main">
        <div class="appointment-top"><strong>{{ $customer }}</strong><span class="pill {{ $booking->status }}">{{ $statusLabels[$booking->status] ?? ucfirst($booking->status) }}</span></div>
        <div class="appointment-meta"><span class="material-symbols-rounded" aria-hidden="true">content_cut</span>{{ $booking->court->name }}</div>
        <div class="appointment-meta"><span class="material-symbols-rounded" aria-hidden="true">person</span>{{ $booking->staff?->name ?: 'Sin asignar' }}</div>
    </div>
    <span class="material-symbols-rounded chevron" aria-hidden="true">chevron_right</span>
</a>
