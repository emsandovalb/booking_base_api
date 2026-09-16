@extends('super-admin.layout')

@section('title', $business->name . ' Workspace | Bemuss Booking')
@section('page_title', $business->name)
@section('page_subtitle', 'Dashboard > Businesses > ' . $business->name)
@section('page_actions')
    <a class="button" href="{{ route('super-admin.businesses.edit', $business) }}">Edit business</a>
    @if ($business->status === 'active')
        <form method="POST" action="{{ route('super-admin.businesses.suspend', $business) }}">
            @csrf
            @method('PATCH')
            <button class="button danger" type="submit">Suspend</button>
        </form>
    @else
        <form method="POST" action="{{ route('super-admin.businesses.activate', $business) }}">
            @csrf
            @method('PATCH')
            <button class="button success" type="submit">Activate</button>
        </form>
    @endif
@endsection

@section('content')
    <style>
        .workspace-shell {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        .workspace-nav {
            position: sticky;
            top: 24px;
        }

        .workspace-nav .nav-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: var(--accent);
            margin-bottom: 14px;
        }

        .workspace-nav-list {
            display: grid;
            gap: 8px;
        }

        .workspace-nav-list a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-radius: 14px;
            border: 1px solid transparent;
            padding: 12px 14px;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.02);
        }

        .workspace-nav-list a:hover {
            color: var(--text);
            border-color: var(--border);
            background: rgba(255, 255, 255, 0.05);
        }

        .workspace-main {
            display: grid;
            gap: 18px;
        }

        .workspace-hero {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 18px;
            align-items: center;
        }

        .workspace-logo {
            width: 86px;
            height: 86px;
            border-radius: 24px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(244, 198, 106, 0.22), rgba(125, 211, 252, 0.14));
            border: 1px solid var(--border);
            font-size: 26px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: .06em;
        }

        .workspace-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .meta-pill {
            padding: 8px 11px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.04);
            color: var(--muted);
            font-size: 12px;
        }

        .workspace-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .workspace-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .workspace-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .workspace-card {
            height: 100%;
        }

        .workspace-card .eyebrow {
            color: var(--accent);
            font-size: 12px;
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .workspace-card h3,
        .workspace-card h4 {
            margin: 0;
        }

        .workspace-card p {
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.5;
        }

        .workspace-card .stack {
            gap: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .info-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            color: var(--muted);
            font-size: 13px;
        }

        .info-value {
            text-align: right;
            font-weight: 600;
        }

        .feature-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .feature-badge {
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(52, 211, 153, 0.28);
            background: rgba(52, 211, 153, 0.10);
            color: #bbf7d0;
            font-size: 12px;
        }

        .module-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 190px;
        }

        .module-card .button {
            width: fit-content;
            margin-top: 14px;
        }

        .module-card .coming {
            color: var(--muted);
            font-size: 12px;
            margin-top: 12px;
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .section-anchor {
            scroll-margin-top: 24px;
        }

        @media (max-width: 1100px) {
            .workspace-shell,
            .workspace-grid-4,
            .workspace-grid-3,
            .workspace-grid-2 {
                grid-template-columns: 1fr 1fr;
            }

            .workspace-nav {
                position: relative;
                top: 0;
            }
        }

        @media (max-width: 760px) {
            .workspace-shell,
            .workspace-grid-4,
            .workspace-grid-3,
            .workspace-grid-2,
            .workspace-hero {
                grid-template-columns: 1fr;
            }

            .info-row {
                flex-direction: column;
            }

            .info-value {
                text-align: left;
            }
        }
    </style>

    <section class="workspace-shell">
        <aside class="card workspace-nav">
            <div class="nav-title">Workspace</div>
            <nav class="workspace-nav-list">
                <a href="#overview"><span>Overview</span><span>01</span></a>
                <a href="#reservations"><span>Reservations</span><span>02</span></a>
                <a href="#services"><span>Services</span><span>03</span></a>
                <a href="#staff"><span>Staff</span><span>04</span></a>
                <a href="#customers"><span>Customers</span><span>05</span></a>
                <a href="#gallery"><span>Gallery</span><span>06</span></a>
                <a href="#reviews"><span>Reviews</span><span>07</span></a>
                <a href="#branding"><span>Branding</span><span>08</span></a>
                <a href="#members"><span>Members</span><span>09</span></a>
                <a href="#activity"><span>Activity</span><span>10</span></a>
                <a href="#configuration"><span>Configuration</span><span>11</span></a>
            </nav>
        </aside>

        <div class="workspace-main">
            <section class="card workspace-hero" id="overview">
                <div class="workspace-logo">{{ $businessInitials }}</div>
                <div>
                    <div class="toolbar">
                        <span class="badge {{ $business->status }}">{{ ucfirst($business->status) }}</span>
                        <span class="meta-pill">{{ ucfirst($business->business_type ?? 'business') }}</span>
                        <span class="meta-pill">Rating {{ data_get($configPreview, 'identity.rating', 'N/A') }}</span>
                    </div>
                    <h2 style="margin: 14px 0 0; font-size: 28px;">{{ $business->name }}</h2>
                    <p style="margin: 10px 0 0; color: var(--muted); max-width: 760px;">
                        Tenant workspace for super admin operations. Use this panel as the launcher for reservations,
                        services, staff, branding, and tenant level configuration.
                    </p>

                    <div class="workspace-meta">
                        <span class="meta-pill">Slug: {{ $business->slug }}</span>
                        <span class="meta-pill">Created: {{ $business->created_at?->format('d M Y') ?? 'N/A' }}</span>
                        <span class="meta-pill">Members: {{ $business->members_count ?? 0 }}</span>
                    </div>
                </div>
            </section>

            <section class="grid cards">
                <div class="card metric">
                    <div class="label">Reservations</div>
                    <div class="value">{{ $business->reservations_count ?? 0 }}</div>
                    <div class="hint">Reservation history for this tenant</div>
                </div>
                <div class="card metric">
                    <div class="label">Services</div>
                    <div class="value">{{ $business->services_count ?? 0 }}</div>
                    <div class="hint">Catalog items and bookable services</div>
                </div>
                <div class="card metric">
                    <div class="label">Staff</div>
                    <div class="value">{{ $business->staff_count ?? 0 }}</div>
                    <div class="hint">Operational team members</div>
                </div>
                <div class="card metric">
                    <div class="label">Members</div>
                    <div class="value">{{ $business->members_count ?? 0 }}</div>
                    <div class="hint">Connected tenant users</div>
                </div>
                <div class="card metric">
                    <div class="label">Reviews</div>
                    <div class="value">{{ $reviewsCount }}</div>
                    <div class="hint">Published review count</div>
                </div>
                <div class="card metric">
                    <div class="label">Gallery Photos</div>
                    <div class="value">{{ $galleryPhotosCount }}</div>
                    <div class="hint">Photos attached to services</div>
                </div>
            </section>

            <section class="section grid workspace-grid-4">
                <div class="card metric">
                    <div class="label">Total Members</div>
                    <div class="value">{{ $business->members_count ?? 0 }}</div>
                    <div class="hint">All linked business users</div>
                </div>
                <div class="card metric">
                    <div class="label">Active Members</div>
                    <div class="value">{{ $business->active_members_count ?? 0 }}</div>
                    <div class="hint">Members with active status</div>
                </div>
                <div class="card metric">
                    <div class="label">Pending</div>
                    <div class="value">{{ $business->pending_members_count ?? 0 }}</div>
                    <div class="hint">Invited but not active yet</div>
                </div>
                <div class="card metric">
                    <div class="label">Admins</div>
                    <div class="value">{{ $business->admins_count ?? 0 }}</div>
                    <div class="hint">Owners and admins</div>
                </div>
            </section>

            <section class="section grid workspace-grid-2">
                <div class="card workspace-card section-anchor" id="business-information">
                    <div class="eyebrow">1. Business Information</div>
                    <div class="stack">
                        <div class="info-row">
                            <div class="info-label">Name</div>
                            <div class="info-value">{{ $business->name }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Slug</div>
                            <div class="info-value">{{ $business->slug }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Status</div>
                            <div class="info-value">{{ ucfirst($business->status) }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Created</div>
                            <div class="info-value">{{ $business->created_at?->format('d M Y') ?? 'N/A' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Type</div>
                            <div class="info-value">{{ ucfirst($business->business_type ?? 'business') }}</div>
                        </div>
                    </div>
                </div>

                <div class="card workspace-card section-anchor" id="branding">
                    <div class="eyebrow">2. Brand Configuration</div>
                    <div class="stack">
                        <div class="info-row">
                            <div class="info-label">App name</div>
                            <div class="info-value">{{ data_get($configPreview, 'identity.app_name', 'N/A') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Display name</div>
                            <div class="info-value">{{ data_get($configPreview, 'identity.display_name', 'N/A') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Logo</div>
                            <div class="info-value">{{ data_get($configPreview, 'identity.logo', 'Placeholder') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Hero image</div>
                            <div class="info-value">{{ data_get($configPreview, 'identity.hero_image', 'Placeholder') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Primary color</div>
                            <div class="info-value">{{ data_get($configPreview, 'colors.primary_gold', 'N/A') }}</div>
                        </div>
                        <div class="toolbar">
                            <a class="button" href="{{ route('super-admin.businesses.edit', $business) }}">Open Branding</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section grid workspace-grid-2">
                <div class="card workspace-card section-anchor" id="contact">
                    <div class="eyebrow">3. Contact</div>
                    <div class="stack">
                        <div class="info-row">
                            <div class="info-label">Phone</div>
                            <div class="info-value">{{ data_get($configPreview, 'contact.phone', 'N/A') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email</div>
                            <div class="info-value">{{ data_get($configPreview, 'contact.email', 'N/A') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Instagram</div>
                            <div class="info-value">{{ data_get($configPreview, 'contact.instagram', 'N/A') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Website</div>
                            <div class="info-value">{{ data_get($configPreview, 'contact.website', 'N/A') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address</div>
                            <div class="info-value">{{ data_get($configPreview, 'contact.address', 'N/A') }}</div>
                        </div>
                    </div>
                </div>

                <div class="card workspace-card section-anchor" id="configuration">
                    <div class="eyebrow">4. Operational Summary</div>
                    <div class="stack">
                        <div class="info-row">
                            <div class="info-label">Reservations</div>
                            <div class="info-value">{{ $business->reservations_count ?? 0 }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Staff</div>
                            <div class="info-value">{{ $business->staff_count ?? 0 }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Services</div>
                            <div class="info-value">{{ $business->services_count ?? 0 }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Members</div>
                            <div class="info-value">{{ $business->members_count ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section card workspace-card section-anchor" id="features">
                <div class="eyebrow">5. Features Enabled</div>
                <div class="feature-badges">
                    @forelse ($enabledFeatures as $feature)
                        <span class="feature-badge">{{ $feature }}</span>
                    @empty
                        <span class="meta-pill">No enabled features detected</span>
                    @endforelse
                </div>
            </section>

            <section class="section card workspace-card section-anchor" id="activity">
                @include('super-admin.partials.audit-timeline', [
                    'activityTitle' => 'Recent Activity',
                    'activityNote' => 'Latest 20 audit events for this workspace business.',
                    'business' => $business,
                    'activityLogs' => $activityLogs,
                    'activityRows' => $activityRows,
                    'activityFilters' => $activityFilters,
                    'activityActionOptions' => $activityActionOptions,
                    'activityUsers' => $activityUsers,
                ])
            </section>

            <section class="section section-anchor" id="modules">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Quick Modules</h2>
                        <p class="section-note">Launcher cards for tenant administration</p>
                    </div>
                </div>

                <div class="workspace-grid-4">
                    <div class="card workspace-card module-card section-anchor" id="reservations">
                        <div>
                            <div class="eyebrow">Reservations</div>
                            <h4>Manage reservations</h4>
                            <p>Open the reservations launcher for this tenant.</p>
                        </div>
                        <a class="button" href="#overview">Go to overview</a>
                    </div>

                    <div class="card workspace-card module-card section-anchor" id="services">
                        <div>
                            <div class="eyebrow">Services</div>
                            <h4>Manage services</h4>
                            <p>Open the services launcher for this tenant.</p>
                        </div>
                        <a class="button" href="#overview">Go to overview</a>
                    </div>

                    <div class="card workspace-card module-card section-anchor" id="staff">
                        <div>
                            <div class="eyebrow">Staff</div>
                            <h4>Manage staff</h4>
                            <p>Open the staff launcher for this tenant.</p>
                        </div>
                        <a class="button" href="#overview">Go to overview</a>
                    </div>

                    <div class="card workspace-card module-card section-anchor" id="gallery">
                        <div>
                            <div class="eyebrow">Gallery</div>
                            <h4>Open gallery</h4>
                            <p>Browse gallery photos and media for this tenant.</p>
                        </div>
                        <a class="button" href="#overview">Go to overview</a>
                    </div>

                    <div class="card workspace-card module-card section-anchor" id="reviews">
                        <div>
                            <div class="eyebrow">Reviews</div>
                            <h4>Open reviews</h4>
                            <p>Track the published review count and reputation data.</p>
                        </div>
                        <a class="button" href="#overview">Go to overview</a>
                    </div>

                    <div class="card workspace-card module-card">
                        <div>
                            <div class="eyebrow">Branding</div>
                            <h4>Open branding</h4>
                            <p>Use the existing configuration editor for brand settings.</p>
                        </div>
                        <a class="button" href="{{ route('super-admin.businesses.edit', $business) }}">Edit business</a>
                    </div>

                    <div class="card workspace-card module-card" id="members">
                        <div>
                            <div class="eyebrow">Members</div>
                            <h4>Manage Members</h4>
                            <p>Open the business membership module and manage roles, statuses, and access.</p>
                        </div>
                        <a class="button" href="{{ route('super-admin.businesses.members.index', $business) }}">Open members</a>
                    </div>

                    <div class="card workspace-card module-card">
                        <div>
                            <div class="eyebrow">Configuration</div>
                            <h4>Coming soon</h4>
                            <p>Advanced tenant configuration will land in a later batch.</p>
                        </div>
                        <span class="coming">Coming Soon</span>
                    </div>
                </div>
            </section>

            <section class="section grid workspace-grid-2">
                <div class="card workspace-card section-anchor" id="customers">
                    <div class="eyebrow">Customers</div>
                    <h4>Placeholder</h4>
                    <p>Customer management is reserved for a future workspace batch.</p>
                </div>

                <div class="card workspace-card section-anchor">
                    <div class="eyebrow">Members</div>
                    <h4>Tenant members</h4>
                    <div class="stack" style="margin-top: 12px;">
                        @forelse ($business->users->take(5) as $user)
                            <div class="info-row">
                                <div class="info-label">{{ $user->name }}</div>
                                <div class="info-value">{{ $user->email }}</div>
                            </div>
                        @empty
                            <div class="muted">No linked users yet.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </section>
@endsection
