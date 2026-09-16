@extends('super-admin.layout')

@section('title', 'Editar negocio | Bemuss Booking')
@section('page_title', 'Editar negocio')
@section('page_subtitle', $business->name)

@section('content')
    @include('super-admin.businesses._form', [
        'action' => route('super-admin.businesses.update', $business),
        'method' => 'PUT',
        'mode' => 'edit',
    ])
@endsection
