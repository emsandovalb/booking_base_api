@extends('super-admin.layout')

@section('title', 'Negocios | Bemuss Booking')
@section('page_title', 'Negocios')
@section('page_subtitle', 'Gestión central de todos los tenants')
@section('page_actions')
    <a class="button primary" href="{{ route('super-admin.businesses.create') }}">Crear negocio</a>
@endsection

@section('content')
    <section class="card">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Slug</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Rating</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($businesses as $business)
                    <tr>
                        <td>
                            <strong>{{ $business->name }}</strong>
                            <div class="muted">{{ $business->legal_name ?? 'Sin razón social' }}</div>
                        </td>
                        <td class="muted">{{ $business->slug }}</td>
                        <td>{{ ucfirst($business->business_type ?? '—') }}</td>
                        <td><span class="badge {{ $business->status }}">{{ ucfirst($business->status) }}</span></td>
                        <td>{{ data_get($business->app_config, 'identity.rating', '—') }}</td>
                        <td class="muted">{{ $business->created_at?->format('d M Y') }}</td>
                        <td>
                            <div class="actions">
                                <a class="button" href="{{ route('super-admin.businesses.workspace', $business) }}">Workspace</a>
                                <a class="button" href="{{ route('super-admin.businesses.show', $business) }}">Activity</a>
                                <a class="button" href="{{ route('super-admin.businesses.edit', $business) }}">Editar config</a>
                                @if ($business->status === 'active')
                                    <form method="POST" action="{{ route('super-admin.businesses.suspend', $business) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="button danger" type="submit">Suspender</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('super-admin.businesses.activate', $business) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="button success" type="submit">Activar</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No hay negocios creados aún.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="section">
        {{ $businesses->links() }}
    </div>
@endsection
