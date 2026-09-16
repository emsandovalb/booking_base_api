@php
    $filters = $activityFilters ?? [];
    $rows = $activityRows ?? [];
    $actionOptions = $activityActionOptions ?? [];
    $users = $activityUsers ?? collect();
@endphp

<style>
    .audit-panel {
        display: grid;
        gap: 16px;
    }

    .audit-filters {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
        align-items: end;
    }

    .audit-field {
        display: grid;
        gap: 8px;
    }

    .audit-field label {
        font-size: 12px;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .audit-field input,
    .audit-field select {
        width: 100%;
    }

    .audit-cta {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
    }

    .audit-timeline {
        display: grid;
        gap: 12px;
    }

    .audit-row {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 14px;
        padding: 14px 16px;
        border-radius: 18px;
        border: 1px solid rgba(255, 255, 255, 0.06);
        background: rgba(255, 255, 255, 0.03);
    }

    .audit-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, rgba(244, 198, 106, 0.18), rgba(125, 211, 252, 0.12));
        border: 1px solid rgba(255, 255, 255, 0.08);
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
    }

    .audit-headline {
        font-weight: 700;
        color: var(--text);
    }

    .audit-detail {
        margin-top: 4px;
        color: var(--muted);
        font-size: 13px;
    }

    .audit-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    .audit-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.04);
        color: var(--muted);
        font-size: 12px;
    }

    .audit-empty {
        color: var(--muted);
        padding: 20px 0 6px;
    }

    @media (max-width: 1100px) {
        .audit-filters {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 720px) {
        .audit-filters {
            grid-template-columns: 1fr;
        }

        .audit-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="audit-panel">
    <div class="section-header">
        <div>
            <div class="eyebrow">Recent Activity</div>
            <h3 style="margin: 0;">{{ $activityTitle ?? 'Recent Activity' }}</h3>
            <p class="section-note">{{ $activityNote ?? 'Latest audit events for this business.' }}</p>
        </div>
        <div class="toolbar">
            <span class="meta-pill">{{ count($rows) }} events</span>
        </div>
    </div>

    <form method="GET" class="card" style="padding: 16px;">
        <input type="hidden" name="business" value="{{ $filters['business'] ?? ($business->id ?? '') }}">
        <div class="audit-filters">
            <div class="audit-field">
                <label for="audit-date">Date</label>
                <input id="audit-date" type="date" name="date" value="{{ $filters['date'] ?? '' }}">
            </div>
            <div class="audit-field">
                <label for="audit-action">Action</label>
                <select id="audit-action" name="action">
                    <option value="">All actions</option>
                    @foreach ($actionOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="audit-field">
                <label for="audit-user">User</label>
                <select id="audit-user" name="user">
                    <option value="">All users</option>
                    @foreach ($users as $member)
                        <option value="{{ $member->id }}" @selected((string) ($filters['user'] ?? '') === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="audit-field">
                <label for="audit-search">Search</label>
                <input id="audit-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Business, user, action">
            </div>
            <div class="audit-field">
                <label for="audit-business">Business</label>
                <input id="audit-business" type="text" value="{{ $business->name ?? ($filters['business'] ?? '') }}" disabled>
            </div>
        </div>
        <div class="audit-cta" style="margin-top: 14px;">
            <button class="button primary" type="submit">Apply filters</button>
            <a class="button" href="{{ request()->url() }}">Reset</a>
        </div>
    </form>

    <div class="audit-timeline">
        @forelse ($rows as $row)
            <article class="audit-row">
                <div class="audit-icon">{{ $row['icon'] }}</div>
                <div>
                    <div class="audit-headline">{{ $row['headline'] }}</div>
                    @if (!empty($row['detail']))
                        <div class="audit-detail">{{ $row['detail'] }}</div>
                    @endif
                    <div class="audit-meta">
                        <span class="audit-pill">{{ $row['action'] }}</span>
                        <span class="audit-pill">{{ $row['actor'] }}</span>
                        <span class="audit-pill">{{ $row['business'] }}</span>
                        <span class="audit-pill">{{ $row['date'] }}</span>
                    </div>
                </div>
            </article>
        @empty
            <div class="card audit-empty">No audit events yet.</div>
        @endforelse
    </div>
</div>
