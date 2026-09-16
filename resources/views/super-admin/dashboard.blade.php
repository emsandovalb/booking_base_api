@extends('super-admin.layout')

@section('title', 'Dashboard | Bemuss Booking')
@section('page_title', 'Dashboard')
@section('page_subtitle', 'Resumen ejecutivo de la plataforma')
@section('page_actions')
    <a class="button primary" href="{{ route('super-admin.businesses.create') }}">Crear negocio</a>
@endsection

@section('content')
    <section class="grid cards">
        <div class="card metric">
            <div class="label">Total negocios</div>
            <div class="value">{{ $summary['total_businesses'] }}</div>
            <div class="hint">Todos los tenants registrados</div>
        </div>
        <div class="card metric">
            <div class="label">Negocios activos</div>
            <div class="value">{{ $summary['active_businesses'] }}</div>
            <div class="hint">Operando normalmente</div>
        </div>
        <div class="card metric">
            <div class="label">Suspendidos</div>
            <div class="value">{{ $summary['suspended_businesses'] }}</div>
            <div class="hint">Bloqueados por el super admin</div>
        </div>
        <div class="card metric">
            <div class="label">Inactivos</div>
            <div class="value">{{ $summary['inactive_businesses'] }}</div>
            <div class="hint">No activos ni suspendidos</div>
        </div>
        <div class="card metric">
            <div class="label">Total usuarios</div>
            <div class="value">{{ $summary['total_users'] }}</div>
            <div class="hint">Usuarios globales del sistema</div>
        </div>
        <div class="card metric">
            <div class="label">Total reservas</div>
            <div class="value">{{ $summary['total_bookings'] }}</div>
            <div class="hint">Reservas históricas</div>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Negocios recientes</h2>
                <p class="section-note">Últimos registros creados o editados recientemente</p>
            </div>
            <a class="button ghost" href="{{ route('super-admin.businesses.index') }}">Ver todos</a>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Slug</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentBusinesses as $business)
                        <tr>
                            <td>{{ $business->name }}</td>
                            <td class="muted">{{ $business->slug }}</td>
                            <td>{{ ucfirst($business->business_type ?? '—') }}</td>
                            <td><span class="badge {{ $business->status }}">{{ ucfirst($business->status) }}</span></td>
                            <td class="muted">{{ $business->created_at?->format('d M Y') }}</td>
                            <td><a class="button" href="{{ route('super-admin.businesses.workspace', $business) }}">Workspace</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Aún no hay negocios registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Reservas recientes</h2>
                <p class="section-note">Vista rápida de actividad operativa</p>
            </div>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Negocio</th>
                        <th>Usuario</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentBookings as $booking)
                        <tr>
                            <td>{{ $booking->booking_code }}</td>
                            <td>{{ $booking->business?->name ?? '—' }}</td>
                            <td>{{ $booking->user?->name ?? '—' }}</td>
                            <td><span class="badge">{{ ucfirst($booking->status) }}</span></td>
                            <td class="muted">{{ $booking->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Aún no hay reservas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
