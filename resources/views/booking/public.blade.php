@extends('pwa.layout', ['experience' => 'b'])

@section('title', 'Reservar en '.$business->name)

@section('content')
<header class="topbar">
    <div class="brandmark">{{ collect(explode(' ', $business->name))->take(2)->map(fn($p) => mb_substr($p,0,1))->join('') }}</div>
    <div><h1>{{ $business->name }}</h1><p>Reserva en pocos pasos</p></div>
</header>
<main>
    <section class="hero">
        <span class="eyebrow">Agenda en línea</span>
        <h2>Tu próximo corte, sin llamadas.</h2>
        <p>Elige servicio, profesional y horario. No necesitas cuenta ni instalar una app.</p>
    </section>

    <div class="progress" aria-label="Progreso"><span class="active"></span><span></span><span></span><span></span><span></span></div>
    @if($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif

    <form method="post" action="{{ route('booking.store', $business->slug) }}" id="booking-form">
        @csrf
        <section class="step" data-step="0">
            <h3>¿Qué necesitas?</h3><p class="hint">Selecciona un servicio.</p>
            <div class="stack">
                @forelse($services as $service)
                <label class="choice">
                    <input type="radio" name="service_id" value="{{ $service->id }}" data-name="{{ $service->name }}" data-price="{{ number_format($service->price_per_hour, 2) }}" @checked(old('service_id') == $service->id)>
                    <span class="choice-body"><span class="choice-title">{{ $service->name }}</span><span class="choice-meta"><span>{{ $service->duration_minutes ?: ($service->duration_hours * 60) }} min</span><strong>Q{{ number_format($service->price_per_hour, 2) }}</strong></span></span>
                </label>
                @empty<div class="empty card">Este negocio aún no publicó servicios.</div>@endforelse
            </div>
            <div class="actions"><button class="button primary" type="button" data-next>Continuar</button></div>
        </section>

        <section class="step" data-step="1" hidden>
            <h3>Elige profesional</h3><p class="hint">Solo mostramos quienes realizan el servicio.</p>
            <div class="grid two" id="staff-list"></div>
            <div class="actions"><button class="button secondary" type="button" data-back>Atrás</button><button class="button primary" type="button" data-next>Continuar</button></div>
        </section>

        <section class="step" data-step="2" hidden>
            <h3>Fecha y hora</h3><p class="hint">Los horarios ocupados desaparecen automáticamente.</p>
            <div class="field"><label for="date">Fecha</label><input id="date" name="date" type="date" min="{{ today()->toDateString() }}" max="{{ today()->addDays(60)->toDateString() }}" value="{{ old('date', today()->addDay()->toDateString()) }}" required></div>
            <p class="muted" id="slot-status">Selecciona fecha y profesional.</p><div class="slots" id="slots"></div>
            <div class="actions"><button class="button secondary" type="button" data-back>Atrás</button><button class="button primary" type="button" data-next>Continuar</button></div>
        </section>

        <section class="step" data-step="3" hidden>
            <h3>Tus datos</h3><p class="hint">Solo los usaremos para gestionar esta cita.</p>
            <div class="stack">
                <div class="field"><label for="customer_name">Nombre</label><input id="customer_name" name="customer_name" autocomplete="name" value="{{ old('customer_name') }}" required maxlength="120"></div>
                <div class="field"><label for="customer_phone">Teléfono</label><input id="customer_phone" name="customer_phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('customer_phone') }}" required maxlength="40"></div>
                <div class="field"><label for="customer_email">Email <span class="muted">(opcional)</span></label><input id="customer_email" name="customer_email" type="email" autocomplete="email" value="{{ old('customer_email') }}"></div>
            </div>
            <div class="actions"><button class="button secondary" type="button" data-back>Atrás</button><button class="button primary" type="button" data-next>Revisar</button></div>
        </section>

        <section class="step" data-step="4" hidden>
            <h3>Revisa tu cita</h3><p class="hint">Confirma que todo esté correcto.</p>
            <div class="card summary" id="review"></div>
            <div class="actions"><button class="button secondary" type="button" data-back>Atrás</button><button class="button brand" type="submit">Confirmar cita</button></div>
        </section>
    </form>
</main>
@endsection

@push('scripts')
<script>
const catalog=@json($services->mapWithKeys(fn($s)=>[$s->id=>['staff'=>$s->staff->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'bio'=>$p->bio])]]));
const form=document.querySelector('#booking-form'),steps=[...document.querySelectorAll('.step')],bars=[...document.querySelectorAll('.progress span')];let step=0;
function selected(name){return form.querySelector(`[name="${name}"]:checked`)}
function show(index){step=Math.max(0,Math.min(4,index));steps.forEach((el,i)=>el.hidden=i!==step);bars.forEach((el,i)=>el.classList.toggle('active',i<=step));scrollTo({top:0,behavior:'smooth'});if(step===4)review()}
function staff(){const service=selected('service_id');if(!service)return;const list=document.querySelector('#staff-list');list.innerHTML=(catalog[service.value]?.staff||[]).map(p=>`<label class="choice"><input type="radio" name="staff_id" value="${p.id}" data-name="${p.name}"><span class="choice-body"><span class="avatar">${p.name.split(' ').map(x=>x[0]).slice(0,2).join('')}</span><span class="choice-title">${p.name}</span><span class="choice-meta"><span>${p.bio||'Profesional del equipo'}</span></span></span></label>`).join('')||'<div class="empty card">No hay profesionales disponibles para este servicio.</div>';}
async function slots(){const service=selected('service_id'),person=selected('staff_id'),date=form.date.value,box=document.querySelector('#slots'),status=document.querySelector('#slot-status');if(!service||!person||!date)return;box.innerHTML='';status.textContent='Buscando horarios…';const url=new URL('{{ route('booking.availability',$business->slug) }}');url.search=new URLSearchParams({service_id:service.value,staff_id:person.value,date});try{const response=await fetch(url,{headers:{Accept:'application/json'}});const data=await response.json();if(!response.ok)throw new Error(data.message||'Error');box.innerHTML=data.slots.map(slot=>`<label class="choice"><input type="radio" name="time" value="${slot.value}" data-label="${slot.label}"><span class="choice-body">${slot.label}</span></label>`).join('');status.textContent=data.slots.length?'Elige un horario disponible.':'No quedan horarios ese día.';}catch(e){status.textContent='No pudimos cargar horarios. Intenta de nuevo.'}}
function valid(){if(step===0&&!selected('service_id'))return alert('Selecciona un servicio.'),false;if(step===1&&!selected('staff_id'))return alert('Selecciona un profesional.'),false;if(step===2&&!selected('time'))return alert('Selecciona un horario.'),false;if(step===3&&!form.reportValidity())return false;return true}
function review(){const service=selected('service_id'),person=selected('staff_id'),time=selected('time');document.querySelector('#review').innerHTML=[['Servicio',service.dataset.name],['Profesional',person.dataset.name],['Fecha',new Date(form.date.value+'T12:00:00').toLocaleDateString('es',{weekday:'long',day:'numeric',month:'long'})],['Hora',time.dataset.label],['Cliente',form.customer_name.value],['Total','Q'+service.dataset.price]].map(([a,b])=>`<div class="summary-row"><span>${a}</span><strong>${b}</strong></div>`).join('')}
document.querySelectorAll('[data-next]').forEach(btn=>btn.onclick=()=>{if(valid()){if(step===0)staff();if(step===1)slots();show(step+1)}});document.querySelectorAll('[data-back]').forEach(btn=>btn.onclick=()=>show(step-1));form.date.addEventListener('change',slots);document.querySelector('#staff-list').addEventListener('change',slots);
</script>
@endpush
