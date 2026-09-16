@extends('super-admin.layout')

@section('title', 'Create Member | ' . $business->name)
@section('page_title', 'Create Member')
@section('page_subtitle', $business->name . ' · Add a new business member')
@section('page_actions')
    <a class="button" href="{{ route('super-admin.businesses.members.index', $business) }}">Back to members</a>
@endsection

@section('content')
    @include('super-admin.business-members._form', [
        'action' => route('super-admin.businesses.members.store', $business),
        'method' => 'POST',
        'business' => $business,
        'form' => $form,
        'member' => null,
        'isEdit' => false,
        'heading' => 'New member wizard',
        'description' => 'Attach an existing user or create a new user, then bind the membership to this business.',
        'submitLabel' => 'Create member',
    ])
@endsection
