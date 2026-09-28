@extends('pwa.layout', ['experience' => 'app'])
@section('title', 'Más · '.$business->name)
@section('content')
@include('business._header')
<main>
    <header class="page-head"><div><span class="eyebrow">Cuenta</span><h2>Más opciones</h2><p>Comparte tu agenda y administra la aplicación.</p></div></header>
    <section class="owner-card" style="margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:13px;margin-bottom:14px"><span class="metric-icon"><span class="material-symbols-rounded">share</span></span><div><strong>Enlace de reservas</strong><div class="muted" style="font-size:12px;margin-top:3px">Listo para compartir con clientes</div></div></div>
        <div class="link-box"><span class="material-symbols-rounded" aria-hidden="true" style="font-size:18px;color:var(--brand)">link</span><span id="public-url">{{ $publicUrl }}</span></div>
        <div class="actions"><button class="button secondary" onclick="copyLink()"><span class="material-symbols-rounded">content_copy</span>Copiar</button><button class="button brand" onclick="shareLink()"><span class="material-symbols-rounded">ios_share</span>Compartir</button></div>
    </section>
    <div class="menu-list">
        <button class="menu-row logout-button browser-only install-banner" data-install onclick="installPwa()"><span class="menu-icon"><span class="material-symbols-rounded">install_mobile</span></span><span><strong>Instalar aplicación</strong><small>Añádela a la pantalla de inicio</small></span><span class="material-symbols-rounded chevron">chevron_right</span></button>
        <a class="menu-row" target="_blank" href="{{ $publicUrl }}"><span class="menu-icon"><span class="material-symbols-rounded">storefront</span></span><span><strong>Ver página pública</strong><small>Comprueba la experiencia del cliente</small></span><span class="material-symbols-rounded chevron">chevron_right</span></a>
        <button class="menu-row logout-button" onclick="openSheet('qr-sheet')"><span class="menu-icon"><span class="material-symbols-rounded">qr_code_2</span></span><span><strong>Código QR</strong><small>Muéstralo o imprímelo en el local</small></span><span class="material-symbols-rounded chevron">chevron_right</span></button>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="menu-row logout-button" type="submit"><span class="menu-icon"><span class="material-symbols-rounded">logout</span></span><span><strong>Cerrar sesión</strong><small>Salir de la cuenta del negocio</small></span><span class="material-symbols-rounded chevron">chevron_right</span></button></form>
    </div>
</main>
<dialog class="sheet" id="qr-sheet"><div class="sheet-inner"><div class="sheet-handle"></div><div class="sheet-head"><h2>Código QR</h2><button class="sheet-close" onclick="closeSheet('qr-sheet')" aria-label="Cerrar"><span class="material-symbols-rounded">close</span></button></div><img class="qr" alt="Código QR del enlace de reservas" src="https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data={{ urlencode($publicUrl) }}"><p class="muted" style="text-align:center">Escanea para abrir la reserva pública.</p></div></dialog>
@include('business._nav')
@endsection
@push('scripts')<script>
async function copyLink(){await navigator.clipboard.writeText(document.querySelector('#public-url').textContent.trim());}
async function shareLink(){const url=document.querySelector('#public-url').textContent.trim();if(navigator.share)await navigator.share({title:'Reserva en {{ addslashes($business->name) }}',text:'Elige tu cita aquí:',url});else await copyLink();}
</script>@endpush
