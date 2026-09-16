@extends('super-admin.layout')

@section('title', 'Crear negocio | Bemuss Booking')
@section('page_title', 'Crear negocio')
@section('page_subtitle', 'Business creation wizard para nuevos tenants')
@section('page_actions')
    <a class="button ghost" href="{{ route('super-admin.businesses.index') }}">Volver a negocios</a>
@endsection

@section('content')
    @include('super-admin.businesses._wizard', [
        'action' => route('super-admin.businesses.store'),
        'form' => $form,
        'existingSlugs' => $existingSlugs,
        'businessTypes' => $businessTypes,
        'featureLabels' => $featureLabels,
        'weekDays' => $weekDays,
    ])
@endsection
