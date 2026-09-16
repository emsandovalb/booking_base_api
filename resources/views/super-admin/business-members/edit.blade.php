@extends('super-admin.layout')

@section('title', 'Edit Member | ' . $business->name)
@section('page_title', 'Edit Member')
@section('page_subtitle', $business->name . ' · Update business membership')
@section('page_actions')
    <a class="button" href="{{ route('super-admin.businesses.members.index', $business) }}">Back to members</a>
@endsection

@section('content')
    @include('super-admin.business-members._form', [
        'action' => route('super-admin.businesses.members.update', [$business, $member]),
        'method' => 'PUT',
        'business' => $business,
        'form' => $form,
        'member' => $member,
        'isEdit' => true,
        'heading' => 'Edit member membership',
        'description' => 'Role, status, and metadata live on the pivot. Email stays locked.',
        'submitLabel' => 'Save changes',
    ])

    <section class="section grid" style="margin-top: 18px; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
        <div class="card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Current membership</h2>
                    <p class="section-note">Read-only summary of the linked user.</p>
                </div>
            </div>
            <div class="stack">
                <div><span class="muted">Name</span><div>{{ $member->name }}</div></div>
                <div><span class="muted">Email</span><div>{{ $member->email }}</div></div>
                <div><span class="muted">Role</span><div>{{ ucfirst($member->pivot->role) }}</div></div>
                <div><span class="muted">Status</span><div>{{ ucfirst($member->pivot->status) }}</div></div>
            </div>
        </div>

        <div class="card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Metadata preview</h2>
                    <p class="section-note">Stored directly on the business_user pivot.</p>
                </div>
            </div>
            <div class="pre">{{ json_encode($member->pivot->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
        </div>
    </section>
@endsection
