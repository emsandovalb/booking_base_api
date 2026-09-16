@extends('super-admin.layout')

@section('title', $business->name . ' Members | Bemuss Booking')
@section('page_title', 'Members')
@section('page_subtitle', $business->name . ' · Business membership management')
@section('page_actions')
    <a class="button" href="{{ route('super-admin.businesses.workspace', $business) }}">Back to workspace</a>
    <a class="button primary" href="{{ route('super-admin.businesses.members.create', $business) }}">Manage Members</a>
@endsection

@section('content')
    <style>
        .members-shell {
            display: grid;
            gap: 18px;
        }

        .members-hero {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 16px;
        }

        .hero-copy h2 {
            margin: 0;
            font-size: 28px;
        }

        .hero-copy p {
            margin: 10px 0 0;
            color: var(--muted);
            line-height: 1.55;
            max-width: 760px;
        }

        .hero-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .summary-chip {
            border-radius: 16px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.04);
            padding: 14px;
        }

        .summary-chip .label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .summary-chip .value {
            font-size: 26px;
            font-weight: 750;
            margin-top: 8px;
        }

        .filters {
            display: grid;
            grid-template-columns: 1.4fr .8fr .8fr auto;
            gap: 12px;
            align-items: end;
        }

        .filter-field label {
            display: block;
            margin-bottom: 8px;
            color: #dbe4f0;
            font-size: 13px;
        }

        .filter-field input,
        .filter-field select {
            width: 100%;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .members-table {
            min-width: 1050px;
        }

        .member-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(244, 198, 106, 0.28), rgba(125, 211, 252, 0.18));
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-weight: 700;
            color: var(--text);
            letter-spacing: .04em;
        }

        .member-main {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .member-name {
            font-weight: 700;
        }

        .member-email,
        .muted-cell {
            color: var(--muted);
            font-size: 13px;
        }

        .badge-role,
        .badge-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 11px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .role-owner { background: rgba(244, 198, 106, 0.14); color: #f5d06c; border-color: rgba(244, 198, 106, 0.25); }
        .role-admin { background: rgba(59, 130, 246, 0.14); color: #93c5fd; border-color: rgba(59, 130, 246, 0.25); }
        .role-manager { background: rgba(168, 85, 247, 0.14); color: #d8b4fe; border-color: rgba(168, 85, 247, 0.25); }
        .role-staff { background: rgba(52, 211, 153, 0.14); color: #86efac; border-color: rgba(52, 211, 153, 0.25); }
        .role-viewer { background: rgba(148, 163, 184, 0.12); color: #cbd5e1; border-color: rgba(148, 163, 184, 0.22); }
        .role-client { background: rgba(75, 85, 99, 0.16); color: #d1d5db; border-color: rgba(75, 85, 99, 0.28); }

        .status-active { background: rgba(52, 211, 153, 0.12); color: #86efac; border-color: rgba(52, 211, 153, 0.25); }
        .status-pending { background: rgba(251, 146, 60, 0.12); color: #fdba74; border-color: rgba(251, 146, 60, 0.25); }
        .status-suspended { background: rgba(248, 113, 113, 0.12); color: #fca5a5; border-color: rgba(248, 113, 113, 0.25); }

        .member-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empty-state {
            display: grid;
            gap: 8px;
            justify-items: start;
        }

        .empty-state h3 {
            margin: 0;
        }

        .empty-state p {
            margin: 0;
            color: var(--muted);
        }

        @media (max-width: 1100px) {
            .members-hero,
            .filters {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <section class="members-shell">
        <section class="card members-hero">
            <div class="hero-copy">
                <div class="badge primary" style="margin-bottom: 12px;">Workspace / Members</div>
                <h2>Manage business members</h2>
                <p>
                    This module manages the people attached to this business. It works on the
                    `business_user` pivot only, so users are preserved while memberships, roles,
                    and statuses are controlled centrally by Super Admin.
                </p>
                <div class="toolbar" style="margin-top: 16px;">
                    <a class="button primary" href="{{ route('super-admin.businesses.members.create', $business) }}">Add member</a>
                    <a class="button" href="{{ route('super-admin.businesses.workspace', $business) }}">Open workspace</a>
                </div>
            </div>

            <div class="hero-summary">
                <div class="summary-chip">
                    <div class="label">Total members</div>
                    <div class="value">{{ $business->members_count ?? 0 }}</div>
                </div>
                <div class="summary-chip">
                    <div class="label">Active members</div>
                    <div class="value">{{ $business->active_members_count ?? 0 }}</div>
                </div>
                <div class="summary-chip">
                    <div class="label">Pending</div>
                    <div class="value">{{ $business->pending_members_count ?? 0 }}</div>
                </div>
                <div class="summary-chip">
                    <div class="label">Admins</div>
                    <div class="value">{{ $business->admins_count ?? 0 }}</div>
                </div>
            </div>
        </section>

        <section class="card">
            <form class="filters" method="GET">
                <div class="filter-field">
                    <label for="search">Search</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" placeholder="Name or email">
                </div>
                <div class="filter-field">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="">All roles</option>
                        @foreach ($roleOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="member-actions">
                    <button class="button primary" type="submit">Apply</button>
                    <a class="button ghost" href="{{ route('super-admin.businesses.members.index', $business) }}">Reset</a>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Members</h2>
                    <p class="section-note">Desktop table with role, status, joined date, and actions.</p>
                </div>
                <span class="badge">{{ $members->total() }} results</span>
            </div>

            <div class="table-wrap">
                <table class="members-table">
                    <thead>
                        <tr>
                            <th>Avatar</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined date</th>
                            <th>Last login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($members as $member)
                            @php
                                $joinedAt = $member->pivot->accepted_at
                                    ?? $member->pivot->invited_at
                                    ?? $member->pivot->created_at
                                    ?? $member->created_at;
                                $roleClass = $roleBadges[$member->pivot->role] ?? 'role-viewer';
                                $statusClass = $statusBadges[$member->pivot->status] ?? 'status-active';
                                $initials = collect(preg_split('/\s+/', trim($member->name)) ?: [])
                                    ->filter()
                                    ->take(2)
                                    ->map(fn ($part) => mb_substr($part, 0, 1))
                                    ->implode('');
                                $initials = strtoupper($initials ?: mb_substr($member->name, 0, 2));
                            @endphp
                            <tr>
                                <td>
                                    <div class="member-avatar">{{ $initials }}</div>
                                </td>
                                <td>
                                    <div class="member-main">
                                        <div>
                                            <div class="member-name">{{ $member->name }}</div>
                                            <div class="member-email">{{ $member->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="muted-cell">{{ $member->email }}</td>
                                <td><span class="badge-role {{ $roleClass }}">{{ ucfirst($member->pivot->role) }}</span></td>
                                <td><span class="badge-status {{ $statusClass }}">{{ ucfirst($member->pivot->status) }}</span></td>
                                <td class="muted-cell">{{ $joinedAt?->format('d M Y') ?? '—' }}</td>
                                <td class="muted-cell">—</td>
                                <td>
                                    <div class="member-actions">
                                        <a class="button" href="{{ route('super-admin.businesses.members.edit', [$business, $member]) }}">Edit</a>
                                        <form method="POST" action="{{ route('super-admin.businesses.members.destroy', [$business, $member]) }}" onsubmit="return confirm('Remove this membership? The user record will stay.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button danger" type="submit">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <h3>No members found</h3>
                                        <p>Try adjusting the filters or add the first business member.</p>
                                        <a class="button primary" href="{{ route('super-admin.businesses.members.create', $business) }}">Add member</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 16px;">
                {{ $members->links() }}
            </div>
        </section>
    </section>
@endsection
