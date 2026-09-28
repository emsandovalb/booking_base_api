<nav class="bottom-nav" aria-label="Navegación principal">
    <a class="{{ request()->routeIs('business.home')?'active':'' }}" href="{{ route('business.home',$business->slug) }}"><span class="material-symbols-rounded" aria-hidden="true">home</span>Inicio</a>
    <a class="{{ request()->routeIs('business.agenda','business.bookings.*')?'active':'' }}" href="{{ route('business.agenda',$business->slug) }}"><span class="material-symbols-rounded" aria-hidden="true">calendar_month</span>Agenda</a>
    <a class="{{ request()->routeIs('business.services')?'active':'' }}" href="{{ route('business.services',$business->slug) }}"><span class="material-symbols-rounded" aria-hidden="true">content_cut</span>Servicios</a>
    <a class="{{ request()->routeIs('business.staff')?'active':'' }}" href="{{ route('business.staff',$business->slug) }}"><span class="material-symbols-rounded" aria-hidden="true">group</span>Personal</a>
    <a class="{{ request()->routeIs('business.share')?'active':'' }}" href="{{ route('business.share',$business->slug) }}"><span class="material-symbols-rounded" aria-hidden="true">more_horiz</span>Más</a>
</nav>
