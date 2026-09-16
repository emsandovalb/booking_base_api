@extends('super-admin.layout')

@section('title', $business->name . ' Activity | Bemuss Booking')
@section('page_title', $business->name)
@section('page_subtitle', 'Business activity and audit trail')
@section('page_actions')
    <a class="button" href="{{ route('super-admin.businesses.workspace', $business) }}">Workspace</a>
    <a class="button primary" href="{{ route('super-admin.businesses.edit', $business) }}">Edit business</a>
@endsection

@section('content')
    <section class="grid cards">
        <div class="card metric">
            <div class="label">Status</div>
            <div class="value"><span class="badge {{ $business->status }}">{{ ucfirst($business->status) }}</span></div>
            <div class="hint">Current business state</div>
        </div>
        <div class="card metric">
            <div class="label">Members</div>
            <div class="value">{{ $business->members_count ?? 0 }}</div>
            <div class="hint">Linked business users</div>
        </div>
        <div class="card metric">
            <div class="label">Resources</div>
            <div class="value">{{ $business->resources_count ?? 0 }}</div>
            <div class="hint">Configured services</div>
        </div>
        <div class="card metric">
            <div class="label">Bookings</div>
            <div class="value">{{ $business->bookings_count ?? 0 }}</div>
            <div class="hint">Historical reservations</div>
        </div>
    </section>

    <div class="section">
        @include('super-admin.partials.audit-timeline', [
            'activityTitle' => 'Activity',
            'activityNote' => 'Filtered business audit history. Latest 20 events only.',
            'business' => $business,
            'activityLogs' => $activityLogs,
            'activityRows' => $activityRows,
            'activityFilters' => $activityFilters,
            'activityActionOptions' => $activityActionOptions,
            'activityUsers' => $activityUsers,
        ])
    </div>
@endsection
