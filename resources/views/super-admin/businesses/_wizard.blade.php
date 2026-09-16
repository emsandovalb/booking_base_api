@php
    $businessTypes = $businessTypes ?? [];
    $featureLabels = $featureLabels ?? [];
    $weekDays = $weekDays ?? [];
    $initialStep = $errors->any() ? 1 : 1;
@endphp

<style>
    .wizard-shell {
        display: grid;
        gap: 18px;
    }

    .wizard-hero {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 16px;
    }

    .wizard-banner {
        position: relative;
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(244, 198, 106, 0.18), transparent 30%),
            linear-gradient(180deg, rgba(22, 36, 62, 0.98), rgba(12, 20, 35, 0.98));
        border: 1px solid var(--border);
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.30);
    }

    .wizard-banner::after {
        content: '';
        position: absolute;
        inset: auto -15% -40% auto;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: rgba(244, 198, 106, 0.10);
        filter: blur(10px);
        pointer-events: none;
    }

    .wizard-kicker {
        color: var(--accent);
        text-transform: uppercase;
        letter-spacing: 0.18em;
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .wizard-banner h2 {
        font-size: 28px;
        line-height: 1.1;
        margin: 0 0 12px;
    }

    .wizard-banner p {
        margin: 0;
        color: var(--muted);
        max-width: 62ch;
    }

    .wizard-note {
        display: grid;
        gap: 10px;
    }

    .note-card {
        background: linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 16px;
    }

    .note-card .label {
        color: var(--muted);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 8px;
    }

    .note-card .value {
        font-size: 16px;
        font-weight: 650;
        line-height: 1.4;
    }

    .wizard-card {
        background: linear-gradient(180deg, rgba(22, 36, 62, 0.92), rgba(14, 23, 40, 0.96));
        border: 1px solid var(--border);
        border-radius: 24px;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.24);
        overflow: hidden;
    }

    .wizard-progress {
        padding: 18px 20px 10px;
        border-bottom: 1px solid var(--border);
        background: rgba(255,255,255,0.02);
    }

    .wizard-steps {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .step-pill {
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 10px 12px;
        background: rgba(255,255,255,0.03);
        color: var(--muted);
        min-height: 68px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 4px;
    }

    .step-pill.active {
        border-color: rgba(244, 198, 106, 0.42);
        background: rgba(244, 198, 106, 0.10);
        color: var(--text);
    }

    .step-pill.done {
        border-color: rgba(52, 211, 153, 0.30);
        background: rgba(52, 211, 153, 0.08);
        color: var(--text);
    }

    .step-pill .num {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.14em;
    }

    .step-pill .title {
        font-size: 14px;
        font-weight: 650;
    }

    .progress-track {
        width: 100%;
        height: 8px;
        border-radius: 999px;
        background: rgba(255,255,255,0.06);
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        width: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--accent), #f7dca2);
        transition: width 220ms ease;
    }

    .wizard-body {
        padding: 20px;
    }

    .wizard-panel {
        display: none;
        animation: wizardFade 180ms ease;
    }

    .wizard-panel.active {
        display: block;
    }

    @keyframes wizardFade {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .panel-heading {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }

    .panel-heading h3 {
        margin: 0 0 8px;
        font-size: 22px;
    }

    .panel-heading p {
        margin: 0;
        color: var(--muted);
    }

    .panel-badge {
        align-self: flex-start;
        padding: 8px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.04);
        color: var(--accent);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
    }

    .wizard-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 16px;
    }

    .wizard-field {
        grid-column: span 6;
    }

    .wizard-field.span-4 { grid-column: span 4; }
    .wizard-field.span-3 { grid-column: span 3; }
    .wizard-field.full { grid-column: 1 / -1; }

    .field-tile {
        display: grid;
        gap: 8px;
        height: 100%;
    }

    .field-tile label {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .field-tile input[type="checkbox"] {
        width: auto;
        margin: 0;
    }

    .placeholder-stack {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .placeholder {
        min-height: 180px;
        border-radius: 18px;
        border: 1px dashed rgba(244, 198, 106, 0.35);
        background:
            radial-gradient(circle at top left, rgba(244, 198, 106, 0.12), transparent 42%),
            rgba(255,255,255,0.03);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--muted);
        padding: 18px;
    }

    .placeholder strong {
        display: block;
        color: var(--text);
        margin-bottom: 6px;
    }

    .hours-grid {
        display: grid;
        gap: 12px;
    }

    .hour-row {
        display: grid;
        grid-template-columns: 220px repeat(2, minmax(0, 1fr));
        gap: 12px;
        align-items: center;
        padding: 14px;
        border-radius: 18px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border);
    }

    .hour-row .day {
        font-weight: 650;
    }

    .hour-row .toggle {
        display: flex;
        justify-content: flex-start;
    }

    .hour-row .toggle label {
        margin: 0;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .summary-card {
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.03);
        border-radius: 18px;
        padding: 16px;
    }

    .summary-card h4 {
        margin: 0 0 12px;
        font-size: 16px;
    }

    .summary-list {
        display: grid;
        gap: 10px;
        color: var(--text);
    }

    .summary-list .row {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        color: var(--muted);
    }

    .summary-list .row strong {
        color: var(--text);
        font-weight: 600;
        text-align: right;
    }

    .feature-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .chip {
        padding: 8px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.04);
    }

    .wizard-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-top: 20px;
    }

    .wizard-footer .actions {
        margin-top: 0 !important;
    }

    .slug-preview {
        border: 1px solid rgba(125, 211, 252, 0.22);
        background: rgba(125, 211, 252, 0.08);
        color: #d7f3ff;
        padding: 12px 14px;
        border-radius: 14px;
        font-size: 13px;
        word-break: break-word;
    }

    .inline-feedback {
        font-size: 12px;
        color: #fca5a5;
        display: none;
    }

    .inline-feedback.visible {
        display: block;
    }

    @media (max-width: 1100px) {
        .wizard-hero,
        .summary-grid,
        .placeholder-stack {
            grid-template-columns: 1fr;
        }

        .wizard-steps {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hour-row {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 720px) {
        .wizard-body,
        .wizard-progress {
            padding-left: 14px;
            padding-right: 14px;
        }

        .wizard-field,
        .wizard-field.span-4,
        .wizard-field.span-3 {
            grid-column: 1 / -1;
        }

        .wizard-footer {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<form
    id="businessWizard"
    method="POST"
    action="{{ $action }}"
    data-existing-slugs='@json($existingSlugs ?? [])'
>
    @csrf

    <div class="wizard-shell">
        <div class="wizard-hero">
            <div class="wizard-banner">
                <div class="wizard-kicker">Super Admin onboarding</div>
                <h2>Multi-step business creation wizard</h2>
                <p>Use this flow to provision a brand new business, its initial owner account, and the core Bemuss config in one controlled finish step.</p>
            </div>

            <div class="wizard-note">
                <div class="note-card">
                    <div class="label">Flow</div>
                    <div class="value">Identity, brand, contact, hours, owner, features, review.</div>
                </div>
                <div class="note-card">
                    <div class="label">Guardrails</div>
                    <div class="value">Validation before step changes, slug uniqueness check, creation only on Finish.</div>
                </div>
            </div>
        </div>

        <div class="wizard-card">
            <div class="wizard-progress">
                <div class="wizard-steps" id="wizardStepper">
                    <div class="step-pill" data-step-pill="1"><span class="num">Step 1</span><span class="title">Business</span></div>
                    <div class="step-pill" data-step-pill="2"><span class="num">Step 2</span><span class="title">Brand</span></div>
                    <div class="step-pill" data-step-pill="3"><span class="num">Step 3</span><span class="title">Contact</span></div>
                    <div class="step-pill" data-step-pill="4"><span class="num">Step 4</span><span class="title">Hours</span></div>
                    <div class="step-pill" data-step-pill="5"><span class="num">Step 5</span><span class="title">Owner</span></div>
                    <div class="step-pill" data-step-pill="6"><span class="num">Step 6</span><span class="title">Features</span></div>
                    <div class="step-pill" data-step-pill="7"><span class="num">Step 7</span><span class="title">Review</span></div>
                </div>
                <div class="progress-track" aria-hidden="true">
                    <div class="progress-fill" id="wizardProgress"></div>
                </div>
            </div>

            <div class="wizard-body">
                <section class="wizard-panel active" data-panel="1">
                    <div class="panel-heading">
                        <div>
                            <h3>Business Identity</h3>
                            <p>Define the core tenant record and the live slug.</p>
                        </div>
                        <div class="panel-badge">Identity</div>
                    </div>

                    <div class="wizard-grid">
                        <div class="wizard-field">
                            <label>Business Name</label>
                            <input
                                name="business[name]"
                                value="{{ old('business.name', $form['business']['name'] ?? '') }}"
                                required
                                data-field="business-name"
                            >
                            @error('business.name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Business Type</label>
                            <select name="business[business_type]" required>
                                @foreach ($businessTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('business.business_type', $form['business']['business_type'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('business.business_type') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Slug</label>
                            <input
                                name="business[slug]"
                                value="{{ old('business.slug', $form['business']['slug'] ?? '') }}"
                                required
                                data-field="business-slug"
                                autocomplete="off"
                            >
                            <div class="help">Must be unique. Used in the business workspace URL.</div>
                            <div class="inline-feedback" id="slugFeedback"></div>
                            @error('business.slug') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Status</label>
                            <select name="business[status]" required>
                                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('business.status', $form['business']['status'] ?? 'active') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('business.status') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field full">
                            <label>Legal Name (optional)</label>
                            <input name="business[legal_name]" value="{{ old('business.legal_name', $form['business']['legal_name'] ?? '') }}">
                            @error('business.legal_name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field full">
                            <label>Live slug preview</label>
                            <div class="slug-preview" id="slugPreview">{{ old('business.slug', $form['business']['slug'] ?? '') ?: 'slug-preview' }}</div>
                        </div>
                    </div>
                </section>

                <section class="wizard-panel" data-panel="2">
                    <div class="panel-heading">
                        <div>
                            <h3>Brand</h3>
                            <p>Set the visible app identity and the core brand colors.</p>
                        </div>
                        <div class="panel-badge">Branding</div>
                    </div>

                    <div class="wizard-grid">
                        <div class="wizard-field">
                            <label>App Name</label>
                            <input name="brand[app_name]" value="{{ old('brand.app_name', $form['brand']['app_name'] ?? '') }}" required>
                            @error('brand.app_name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Display Name</label>
                            <input name="brand[display_name]" value="{{ old('brand.display_name', $form['brand']['display_name'] ?? '') }}" required>
                            @error('brand.display_name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Short Name</label>
                            <input name="brand[short_name]" value="{{ old('brand.short_name', $form['brand']['short_name'] ?? '') }}" required>
                            @error('brand.short_name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Tagline</label>
                            <input name="brand[tagline]" value="{{ old('brand.tagline', $form['brand']['tagline'] ?? '') }}">
                            @error('brand.tagline') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field full">
                            <label>Subtitle</label>
                            <input name="brand[subtitle]" value="{{ old('brand.subtitle', $form['brand']['subtitle'] ?? '') }}">
                            @error('brand.subtitle') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Primary Color</label>
                            <input name="brand[primary_color]" value="{{ old('brand.primary_color', $form['brand']['primary_color'] ?? '') }}" placeholder="#D4A84F" required>
                            @error('brand.primary_color') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Secondary Color</label>
                            <input name="brand[secondary_color]" value="{{ old('brand.secondary_color', $form['brand']['secondary_color'] ?? '') }}" placeholder="#E8C36A" required>
                            @error('brand.secondary_color') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Background Color</label>
                            <input name="brand[background_color]" value="{{ old('brand.background_color', $form['brand']['background_color'] ?? '') }}" placeholder="#07111f" required>
                            @error('brand.background_color') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field full">
                            <div class="placeholder-stack">
                                <div class="placeholder">
                                    <div>
                                        <strong>Logo placeholder</strong>
                                        <div>No upload yet. Reserve this space for future branding assets.</div>
                                    </div>
                                </div>
                                <div class="placeholder">
                                    <div>
                                        <strong>Hero placeholder</strong>
                                        <div>Hero image upload is intentionally deferred in this batch.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="wizard-panel" data-panel="3">
                    <div class="panel-heading">
                        <div>
                            <h3>Contact</h3>
                            <p>Public-facing contact channels for the business.</p>
                        </div>
                        <div class="panel-badge">Contact</div>
                    </div>

                    <div class="wizard-grid">
                        <div class="wizard-field">
                            <label>Phone</label>
                            <input name="contact[phone]" value="{{ old('contact.phone', $form['contact']['phone'] ?? '') }}">
                            @error('contact.phone') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>WhatsApp</label>
                            <input name="contact[whatsapp]" value="{{ old('contact.whatsapp', $form['contact']['whatsapp'] ?? '') }}">
                            @error('contact.whatsapp') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Email</label>
                            <input name="contact[email]" type="email" value="{{ old('contact.email', $form['contact']['email'] ?? '') }}">
                            @error('contact.email') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Website</label>
                            <input name="contact[website]" value="{{ old('contact.website', $form['contact']['website'] ?? '') }}">
                            @error('contact.website') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Instagram</label>
                            <input name="contact[instagram]" value="{{ old('contact.instagram', $form['contact']['instagram'] ?? '') }}">
                            @error('contact.instagram') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Facebook</label>
                            <input name="contact[facebook]" value="{{ old('contact.facebook', $form['contact']['facebook'] ?? '') }}">
                            @error('contact.facebook') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Country</label>
                            <input name="contact[country]" value="{{ old('contact.country', $form['contact']['country'] ?? '') }}">
                            @error('contact.country') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>City</label>
                            <input name="contact[city]" value="{{ old('contact.city', $form['contact']['city'] ?? '') }}">
                            @error('contact.city') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field full">
                            <label>Address</label>
                            <input name="contact[address]" value="{{ old('contact.address', $form['contact']['address'] ?? '') }}">
                            @error('contact.address') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </section>

                <section class="wizard-panel" data-panel="4">
                    <div class="panel-heading">
                        <div>
                            <h3>Business Hours</h3>
                            <p>Weekly schedule editor with simple open and closed states.</p>
                        </div>
                        <div class="panel-badge">Hours</div>
                    </div>

                    <div class="wizard-grid">
                        <div class="wizard-field span-3">
                            <label>Cancellation window (hours)</label>
                            <input
                                type="number"
                                min="0"
                                max="168"
                                name="hours[cancellation_window_hours]"
                                value="{{ old('hours.cancellation_window_hours', $form['hours']['cancellation_window_hours'] ?? 4) }}"
                                required
                            >
                            @error('hours.cancellation_window_hours') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field full">
                            <label>Cancellation policy</label>
                            <textarea name="hours[cancellation_policy_text]">{{ old('hours.cancellation_policy_text', $form['hours']['cancellation_policy_text'] ?? '') }}</textarea>
                            @error('hours.cancellation_policy_text') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field full">
                            <label>Weekly schedule</label>
                            <div class="hours-grid">
                                @foreach ($weekDays as $dayKey => $dayLabel)
                                    @php
                                        $day = old("hours.schedule.$dayKey", $form['hours']['schedule'][$dayKey] ?? ['is_open' => false, 'opens_at' => '09:00', 'closes_at' => '18:00']);
                                    @endphp
                                    <div class="hour-row" data-hour-row="{{ $dayKey }}">
                                        <div class="day">{{ $dayLabel }}</div>
                                        <div class="toggle">
                                            <label>
                                                <input type="hidden" name="hours[schedule][{{ $dayKey }}][is_open]" value="0">
                                                <input
                                                    type="checkbox"
                                                    name="hours[schedule][{{ $dayKey }}][is_open]"
                                                    value="1"
                                                    {{ !empty($day['is_open']) ? 'checked' : '' }}
                                                    data-open-toggle="{{ $dayKey }}"
                                                >
                                                Open
                                            </label>
                                        </div>
                                        <div>
                                            <label style="margin-bottom: 6px;">Opens at</label>
                                            <input
                                                type="time"
                                                name="hours[schedule][{{ $dayKey }}][opens_at]"
                                                value="{{ $day['opens_at'] ?? '09:00' }}"
                                                data-time-input="{{ $dayKey }}"
                                            >
                                        </div>
                                        <div>
                                            <label style="margin-bottom: 6px;">Closes at</label>
                                            <input
                                                type="time"
                                                name="hours[schedule][{{ $dayKey }}][closes_at]"
                                                value="{{ $day['closes_at'] ?? '18:00' }}"
                                                data-time-input="{{ $dayKey }}"
                                            >
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                <section class="wizard-panel" data-panel="5">
                    <div class="panel-heading">
                        <div>
                            <h3>Owner Account</h3>
                            <p>Create the first administrator and attach it to the new business.</p>
                        </div>
                        <div class="panel-badge">Owner</div>
                    </div>

                    <div class="wizard-grid">
                        <div class="wizard-field">
                            <label>Full Name</label>
                            <input name="owner[full_name]" value="{{ old('owner.full_name', $form['owner']['full_name'] ?? '') }}" required>
                            @error('owner.full_name') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Email</label>
                            <input type="email" name="owner[email]" value="{{ old('owner.email', $form['owner']['email'] ?? '') }}" required>
                            @error('owner.email') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Password</label>
                            <input type="password" name="owner[password]" required>
                            @error('owner.password') <div class="error">{{ $message }}</div> @enderror
                        </div>
                        <div class="wizard-field">
                            <label>Confirm Password</label>
                            <input type="password" name="owner[password_confirmation]" required>
                        </div>
                        <div class="wizard-field full">
                            <div class="note-card">
                                <div class="label">Role</div>
                                <div class="value">Owner</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="wizard-panel" data-panel="6">
                    <div class="panel-heading">
                        <div>
                            <h3>Features</h3>
                            <p>Turn on the default capabilities for the new tenant.</p>
                        </div>
                        <div class="panel-badge">Feature Flags</div>
                    </div>

                    <div class="wizard-grid">
                        @foreach ($featureLabels as $key => $label)
                            <div class="wizard-field span-4">
                                <div class="note-card field-tile">
                                    <label>
                                        <input type="hidden" name="features[{{ $key }}]" value="0">
                                        <input
                                            type="checkbox"
                                            name="features[{{ $key }}]"
                                            value="1"
                                            {{ old('features.' . $key, $form['features'][$key] ?? false) ? 'checked' : '' }}
                                        >
                                        <span>{{ $label }}</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="wizard-panel" data-panel="7">
                    <div class="panel-heading">
                        <div>
                            <h3>Review</h3>
                            <p>Confirm the tenant, the owner account, and the final config before creating anything.</p>
                        </div>
                        <div class="panel-badge">Finish</div>
                    </div>

                    <div class="summary-grid">
                        <div class="summary-card">
                            <h4>Business</h4>
                            <div class="summary-list">
                                <div class="row"><span>Name</span><strong id="summaryBusinessName">-</strong></div>
                                <div class="row"><span>Slug</span><strong id="summaryBusinessSlug">-</strong></div>
                                <div class="row"><span>Type</span><strong id="summaryBusinessType">-</strong></div>
                                <div class="row"><span>Status</span><strong id="summaryBusinessStatus">-</strong></div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <h4>Brand</h4>
                            <div class="summary-list">
                                <div class="row"><span>App</span><strong id="summaryBrandApp">-</strong></div>
                                <div class="row"><span>Display</span><strong id="summaryBrandDisplay">-</strong></div>
                                <div class="row"><span>Primary</span><strong id="summaryBrandPrimary">-</strong></div>
                                <div class="row"><span>Secondary</span><strong id="summaryBrandSecondary">-</strong></div>
                                <div class="row"><span>Background</span><strong id="summaryBrandBackground">-</strong></div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <h4>Contact</h4>
                            <div class="summary-list">
                                <div class="row"><span>Phone</span><strong id="summaryContactPhone">-</strong></div>
                                <div class="row"><span>Email</span><strong id="summaryContactEmail">-</strong></div>
                                <div class="row"><span>Website</span><strong id="summaryContactWebsite">-</strong></div>
                                <div class="row"><span>Location</span><strong id="summaryContactLocation">-</strong></div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <h4>Hours</h4>
                            <div class="summary-list">
                                <div class="row"><span>Cancellation window</span><strong id="summaryHoursWindow">-</strong></div>
                                <div class="row"><span>Policy</span><strong id="summaryHoursPolicy">-</strong></div>
                                <div class="row"><span>Weekly schedule</span><strong id="summaryHoursSchedule">-</strong></div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <h4>Owner</h4>
                            <div class="summary-list">
                                <div class="row"><span>Name</span><strong id="summaryOwnerName">-</strong></div>
                                <div class="row"><span>Email</span><strong id="summaryOwnerEmail">-</strong></div>
                                <div class="row"><span>Role</span><strong>Owner</strong></div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <h4>Enabled Features</h4>
                            <div class="feature-chips" id="summaryFeatures"></div>
                        </div>
                    </div>

                    <div class="note-card" style="margin-top: 16px;">
                        <div class="label">Final action</div>
                        <div class="value">Press Finish to create the business, configs, owner user, and business membership, then redirect to the workspace.</div>
                    </div>
                </section>

                <div class="wizard-footer">
                    <div class="muted" id="wizardStepLabel">Step 1 of 7</div>
                    <div class="actions">
                        <button type="button" class="button ghost" id="wizardBack">Back</button>
                        <button type="button" class="button primary" id="wizardNext">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(() => {
    const form = document.getElementById('businessWizard');
    if (!form) return;

    const existingSlugs = new Set(JSON.parse(form.dataset.existingSlugs || '[]').map(value => String(value).toLowerCase()));
    const panels = Array.from(form.querySelectorAll('.wizard-panel'));
    const pills = Array.from(form.querySelectorAll('[data-step-pill]'));
    const progress = document.getElementById('wizardProgress');
    const backButton = document.getElementById('wizardBack');
    const nextButton = document.getElementById('wizardNext');
    const stepLabel = document.getElementById('wizardStepLabel');
    const slugInput = form.querySelector('[data-field="business-slug"]');
    const businessNameInput = form.querySelector('[data-field="business-name"]');
    const slugPreview = document.getElementById('slugPreview');
    const slugFeedback = document.getElementById('slugFeedback');

    const summary = {
        businessName: document.getElementById('summaryBusinessName'),
        businessSlug: document.getElementById('summaryBusinessSlug'),
        businessType: document.getElementById('summaryBusinessType'),
        businessStatus: document.getElementById('summaryBusinessStatus'),
        brandApp: document.getElementById('summaryBrandApp'),
        brandDisplay: document.getElementById('summaryBrandDisplay'),
        brandPrimary: document.getElementById('summaryBrandPrimary'),
        brandSecondary: document.getElementById('summaryBrandSecondary'),
        brandBackground: document.getElementById('summaryBrandBackground'),
        contactPhone: document.getElementById('summaryContactPhone'),
        contactEmail: document.getElementById('summaryContactEmail'),
        contactWebsite: document.getElementById('summaryContactWebsite'),
        contactLocation: document.getElementById('summaryContactLocation'),
        hoursWindow: document.getElementById('summaryHoursWindow'),
        hoursPolicy: document.getElementById('summaryHoursPolicy'),
        hoursSchedule: document.getElementById('summaryHoursSchedule'),
        ownerName: document.getElementById('summaryOwnerName'),
        ownerEmail: document.getElementById('summaryOwnerEmail'),
        features: document.getElementById('summaryFeatures'),
    };

    let currentStep = 1;

    function slugify(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-{2,}/g, '-');
    }

    function panelFor(step) {
        return panels.find(panel => Number(panel.dataset.panel) === Number(step));
    }

    function setButtonState() {
        backButton.disabled = currentStep === 1;
        backButton.style.opacity = currentStep === 1 ? '0.5' : '1';
        nextButton.textContent = currentStep === panels.length ? 'Finish' : 'Next';
        stepLabel.textContent = `Step ${currentStep} of ${panels.length}`;
        progress.style.width = `${((currentStep - 1) / (panels.length - 1)) * 100}%`;

        pills.forEach(pill => {
            const step = Number(pill.dataset.stepPill);
            pill.classList.toggle('active', step === currentStep);
            pill.classList.toggle('done', step < currentStep);
        });
    }

    function showStep(step) {
        currentStep = Math.max(1, Math.min(panels.length, step));
        panels.forEach(panel => panel.classList.toggle('active', Number(panel.dataset.panel) === currentStep));
        setButtonState();
        syncSummary();
    }

    function fieldsForStep(step) {
        const panel = panelFor(step);
        if (!panel) return [];
        return Array.from(panel.querySelectorAll('input, select, textarea')).filter(el => !el.disabled);
    }

    function validateSlug() {
        if (!slugInput) return true;
        const slug = slugInput.value.trim().toLowerCase();
        if (!slug) return true;

        const duplicate = existingSlugs.has(slug);
        if (duplicate) {
            slugFeedback.textContent = 'Slug already exists. Choose another value.';
            slugFeedback.classList.add('visible');
            slugInput.setCustomValidity('Slug already exists');
            return false;
        }

        slugFeedback.textContent = '';
        slugFeedback.classList.remove('visible');
        slugInput.setCustomValidity('');
        return true;
    }

    function validateStep(step) {
        const fields = fieldsForStep(step);
        let valid = true;

        fields.forEach(field => {
            if (!field.checkValidity()) {
                valid = false;
            }
        });

        if (step === 1 && !validateSlug()) {
            valid = false;
        }

        if (!valid && fields.length > 0) {
            fields.find(field => !field.checkValidity())?.reportValidity();
        }

        return valid;
    }

    function scheduleLine(dayKey) {
        const toggle = form.querySelector(`[data-open-toggle="${dayKey}"]`);
        const openInput = form.querySelector(`input[name="hours[schedule][${dayKey}][opens_at]"]`);
        const closeInput = form.querySelector(`input[name="hours[schedule][${dayKey}][closes_at]"]`);
        const label = toggle?.closest('.hour-row')?.querySelector('.day')?.textContent || dayKey;
        if (!toggle) return `${label} closed`;
        if (!toggle.checked) return `${label} closed`;
        return `${label} ${openInput?.value || '09:00'} - ${closeInput?.value || '18:00'}`;
    }

    function syncHoursState() {
        form.querySelectorAll('[data-open-toggle]').forEach(toggle => {
            const dayKey = toggle.dataset.openToggle;
            const openInput = form.querySelector(`input[name="hours[schedule][${dayKey}][opens_at]"]`);
            const closeInput = form.querySelector(`input[name="hours[schedule][${dayKey}][closes_at]"]`);
            const disabled = !toggle.checked;

            if (openInput) openInput.disabled = disabled;
            if (closeInput) closeInput.disabled = disabled;
            if (openInput) openInput.required = !disabled;
            if (closeInput) closeInput.required = !disabled;
        });
    }

    function syncSummary() {
        const getValue = selector => (form.querySelector(selector)?.value || '').trim() || '—';
        const checkboxValues = Array.from(form.querySelectorAll('input[type="checkbox"][name^="features["]'))
            .filter(input => input.checked)
            .map(input => input.nextElementSibling?.textContent?.trim() || input.name);

        if (summary.businessName) summary.businessName.textContent = getValue('[name="business[name]"]');
        if (summary.businessSlug) summary.businessSlug.textContent = getValue('[name="business[slug]"]');
        if (summary.businessType) summary.businessType.textContent = form.querySelector('[name="business[business_type]"]')?.selectedOptions[0]?.textContent?.trim() || '—';
        if (summary.businessStatus) summary.businessStatus.textContent = form.querySelector('[name="business[status]"]')?.selectedOptions[0]?.textContent?.trim() || '—';

        if (summary.brandApp) summary.brandApp.textContent = getValue('[name="brand[app_name]"]');
        if (summary.brandDisplay) summary.brandDisplay.textContent = getValue('[name="brand[display_name]"]');
        if (summary.brandPrimary) summary.brandPrimary.textContent = getValue('[name="brand[primary_color]"]');
        if (summary.brandSecondary) summary.brandSecondary.textContent = getValue('[name="brand[secondary_color]"]');
        if (summary.brandBackground) summary.brandBackground.textContent = getValue('[name="brand[background_color]"]');

        if (summary.contactPhone) summary.contactPhone.textContent = getValue('[name="contact[phone]"]');
        if (summary.contactEmail) summary.contactEmail.textContent = getValue('[name="contact[email]"]');
        if (summary.contactWebsite) summary.contactWebsite.textContent = getValue('[name="contact[website]"]');
        if (summary.contactLocation) {
            const city = getValue('[name="contact[city]"]');
            const country = getValue('[name="contact[country]"]');
            summary.contactLocation.textContent = [city, country].filter(value => value !== '—').join(', ') || '—';
        }

        if (summary.hoursWindow) summary.hoursWindow.textContent = `${getValue('[name="hours[cancellation_window_hours]"]')} hours`;
        if (summary.hoursPolicy) summary.hoursPolicy.textContent = getValue('[name="hours[cancellation_policy_text]"]');
        if (summary.hoursSchedule) {
            summary.hoursSchedule.textContent = Array.from(form.querySelectorAll('[data-open-toggle]'))
                .map(toggle => scheduleLine(toggle.dataset.openToggle))
                .slice(0, 3)
                .join(' | ');
        }

        if (summary.ownerName) summary.ownerName.textContent = getValue('[name="owner[full_name]"]');
        if (summary.ownerEmail) summary.ownerEmail.textContent = getValue('[name="owner[email]"]');
        if (summary.features) {
            summary.features.innerHTML = '';
            checkboxValues.length ? checkboxValues.forEach(label => {
                const chip = document.createElement('span');
                chip.className = 'chip';
                chip.textContent = label;
                summary.features.appendChild(chip);
            }) : summary.features.innerHTML = '<span class="muted">No features enabled.</span>';
        }
    }

    function syncSlugPreview() {
        if (!slugInput || !slugPreview) return;
        const autoSlug = slugify(businessNameInput?.value || '');
        const current = slugInput.value.trim();
        if (!current && autoSlug) {
            slugPreview.textContent = autoSlug;
        } else {
            slugPreview.textContent = current || autoSlug || 'slug-preview';
        }
        validateSlug();
    }

    function initialStep() {
        const errorPanel = panels.find(panel => panel.querySelector('.error'));
        if (errorPanel) {
            return Number(errorPanel.dataset.panel);
        }
        return 1;
    }

    form.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('input', () => {
            syncHoursState();
            syncSummary();
            if (field === businessNameInput || field === slugInput) {
                syncSlugPreview();
            }
            if (field === slugInput) {
                validateSlug();
            }
        });
        field.addEventListener('change', () => {
            syncHoursState();
            syncSummary();
            if (field === businessNameInput || field === slugInput) {
                syncSlugPreview();
            }
        });
    });

    form.querySelectorAll('[data-open-toggle]').forEach(toggle => {
        toggle.addEventListener('change', () => {
            syncHoursState();
            syncSummary();
        });
    });

    if (businessNameInput && slugInput) {
        businessNameInput.addEventListener('blur', () => {
            if (!slugInput.value.trim()) {
                slugInput.value = slugify(businessNameInput.value);
            }
            syncSlugPreview();
        });
    }

    backButton.addEventListener('click', () => {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    });

    nextButton.addEventListener('click', () => {
        if (!validateStep(currentStep)) {
            return;
        }

        if (currentStep === panels.length) {
            form.submit();
            return;
        }

        showStep(currentStep + 1);
    });

    form.addEventListener('submit', () => {
        if (slugInput) {
            slugInput.setCustomValidity('');
        }
    });

    syncHoursState();
    syncSummary();
    syncSlugPreview();
    showStep(initialStep());
})();
</script>
