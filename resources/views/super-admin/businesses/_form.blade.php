@php
    $mode = $mode ?? 'create';
    $branding = $form['branding'] ?? [];
    $identity = $branding['identity'] ?? [];
    $assets = $branding['assets'] ?? [];
    $colors = $branding['colors'] ?? [];
    $appearance = $branding['appearance'] ?? [];
    $terminology = $branding['terminology'] ?? [];

    $themePresets = \App\Support\BrandingConfig::themePresetLabels();
    $themePresetValue = old('branding.appearance.theme_preset', $appearance['theme_preset'] ?? 'elegant_light');
    if (! array_key_exists($themePresetValue, $themePresets)) {
        $themePresetValue = array_key_first($themePresets) ?: 'elegant_light';
    }
    $primaryColor = old('branding.colors.primary', $colors['primary'] ?? $colors['primary_gold'] ?? '#D4A84F');
    $accentColor = old('branding.colors.accent', $colors['accent'] ?? '#A86E63');
    $previewDisplayName = old('branding.identity.display_name', $identity['display_name'] ?? 'Marca');
    $previewShortName = old('branding.identity.short_name', $identity['short_name'] ?? 'BM');
    $previewTagline = old('branding.identity.tagline', $identity['tagline'] ?? '');
    $previewSubtitle = old('branding.identity.subtitle', $identity['subtitle'] ?? '');
    $previewLocation = old('branding.identity.location_short', $identity['location_short'] ?? '');
    $previewBackground = $colors['background'] ?? '#090909';
    $previewSurface = $colors['surface'] ?? '#151C31';
    $previewCard = $colors['card'] ?? '#10172A';
    $previewBorder = $colors['border'] ?? 'rgba(255,255,255,0.13)';
    $previewText = $colors['text_primary'] ?? '#F5F7FB';
    $previewSecondaryText = $colors['text_secondary'] ?? '#94A3B8';
    $previewInputBackground = $colors['input_background'] ?? $previewSurface;
    $previewPlaceholder = $colors['placeholder'] ?? $previewSecondaryText;
    $previewPrimaryText = $colors['text_on_primary'] ?? '#090909';

    $assetFields = [
        'logo_transparent' => 'Logo transparente',
        'logo_dark' => 'Logo oscuro',
        'logo_light' => 'Logo claro',
        'hero_background' => 'Hero',
        'login_background' => 'Login',
        'onboarding_background' => 'Onboarding',
        'service_placeholder' => 'Placeholder de servicio',
        'premium_service_placeholder' => 'Placeholder premium',
        'staff_placeholder' => 'Placeholder de staff',
        'profile_placeholder' => 'Placeholder de perfil',
    ];
    $termFields = [
        'business' => 'Negocio',
        'business_profile' => 'Perfil del negocio',
        'service' => 'Servicio',
        'services' => 'Servicios',
        'appointment' => 'Cita',
        'appointments' => 'Citas',
        'staff' => 'Staff',
        'staff_plural' => 'Staff plural',
        'staff_display_name' => 'Nombre de staff',
        'manager' => 'Encargado',
        'gallery' => 'Galeria',
        'reviews' => 'Opiniones',
    ];
    $advancedColorFields = [
        'primary_light' => 'Primary light',
        'primary_dark' => 'Primary dark',
        'secondary' => 'Secondary',
        'background' => 'Background',
        'surface' => 'Surface',
        'card' => 'Card',
        'border' => 'Border',
        'text_primary' => 'Text primary',
        'text_secondary' => 'Text secondary',
        'text_on_primary' => 'Text on primary',
        'input_background' => 'Input background',
        'placeholder' => 'Placeholder',
        'disabled_text' => 'Disabled text',
        'disabled_background' => 'Disabled background',
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Danger',
        'primary_gold' => 'Primary gold',
        'primary_gold_light' => 'Primary gold light',
        'primary_gold_dark' => 'Primary gold dark',
    ];
    $advancedAppearanceFields = [
        'card_radius' => ['label' => 'Card radius', 'type' => 'number', 'min' => 0, 'max' => 64],
        'input_radius' => ['label' => 'Input radius', 'type' => 'number', 'min' => 0, 'max' => 64],
        'button_radius' => ['label' => 'Button radius', 'type' => 'number', 'min' => 0, 'max' => 64],
        'use_cinematic_backgrounds' => ['label' => 'Cinematic backgrounds', 'type' => 'checkbox'],
        'use_logo_glow' => ['label' => 'Logo glow', 'type' => 'checkbox'],
        'use_heavy_blur' => ['label' => 'Heavy blur', 'type' => 'checkbox'],
    ];
@endphp

<style>
    .branding-simple-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
        gap: 18px;
        align-items: start;
    }

    .branding-preview {
        position: sticky;
        top: 20px;
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.10);
        background:
            radial-gradient(circle at top right, rgba(244, 198, 106, 0.16), transparent 28%),
            linear-gradient(180deg, rgba(16, 29, 51, 0.98), rgba(10, 18, 31, 0.98));
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
    }

    .branding-preview__inner {
        padding: 18px;
        display: grid;
        gap: 14px;
    }

    .branding-preview__eyebrow {
        text-transform: uppercase;
        letter-spacing: 0.16em;
        font-size: 11px;
        color: #f4c66a;
        font-weight: 700;
    }

    .branding-preview__hero {
        display: flex;
        gap: 14px;
        align-items: center;
    }

    .branding-preview__mark {
        width: 62px;
        height: 62px;
        flex: 0 0 62px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        font-weight: 800;
        font-size: 20px;
        letter-spacing: 0.06em;
        background: var(--preview-primary, #d4a84f);
        color: var(--preview-primary-text, #090909);
        box-shadow: 0 14px 28px rgba(0, 0, 0, 0.22);
    }

    .branding-preview__title {
        margin: 0;
        font-size: 24px;
        line-height: 1.08;
        color: var(--preview-text, #f5f7fb);
    }

    .branding-preview__subtitle,
    .branding-preview__meta {
        margin: 4px 0 0;
        color: var(--preview-secondary-text, #94a3b8);
        font-size: 13px;
    }

    .branding-preview__panel {
        border-radius: 20px;
        border: 1px solid var(--preview-border, rgba(255, 255, 255, 0.08));
        background: var(--preview-surface, rgba(255, 255, 255, 0.05));
        padding: 14px;
        display: grid;
        gap: 12px;
    }

    .branding-preview__search {
        display: flex;
        align-items: center;
        gap: 10px;
        border-radius: 18px;
        border: 1px solid var(--preview-border, rgba(255, 255, 255, 0.08));
        background: var(--preview-input-bg, rgba(255, 255, 255, 0.05));
        color: var(--preview-placeholder, #94a3b8);
        padding: 12px 14px;
        font-size: 13px;
    }

    .branding-preview__card {
        display: grid;
        gap: 10px;
        border-radius: 20px;
        border: 1px solid var(--preview-border, rgba(255, 255, 255, 0.08));
        background: var(--preview-card, rgba(255, 255, 255, 0.06));
        padding: 16px;
    }

    .branding-preview__card-label {
        font-size: 12px;
        color: var(--preview-secondary-text, #94a3b8);
        text-transform: uppercase;
        letter-spacing: 0.12em;
    }

    .branding-preview__secondary {
        color: var(--preview-secondary-text, #94a3b8);
        font-size: 13px;
        line-height: 1.45;
    }

    .branding-preview__button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        border-radius: 16px;
        border: 0;
        background: var(--preview-primary, #d4a84f);
        color: var(--preview-primary-text, #090909);
        font: inherit;
        font-weight: 800;
        letter-spacing: 0.01em;
        padding: 0 18px;
        width: 100%;
    }

    .branding-preview__swatches {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .branding-preview__swatch {
        border-radius: 16px;
        padding: 12px;
        min-height: 76px;
        border: 1px solid var(--preview-border, rgba(255, 255, 255, 0.08));
        display: flex;
        align-items: flex-end;
        font-size: 12px;
        color: var(--preview-text, #f5f7fb);
    }

    .color-pair {
        display: grid;
        gap: 8px;
    }

    .color-pair__row {
        display: grid;
        grid-template-columns: 52px minmax(0, 1fr);
        gap: 10px;
        align-items: center;
    }

    .color-pair__hex {
        text-transform: uppercase;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .advanced-details {
        margin-top: 18px;
        border: 1px solid var(--border);
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.03);
        overflow: hidden;
    }

    .advanced-details summary {
        cursor: pointer;
        list-style: none;
        padding: 16px 18px;
        font-weight: 700;
    }

    .advanced-details summary::-webkit-details-marker {
        display: none;
    }

    .advanced-details__body {
        padding: 0 18px 18px;
    }

    @media (max-width: 1100px) {
        .branding-simple-grid {
            grid-template-columns: 1fr;
        }

        .branding-preview {
            position: relative;
            top: 0;
        }
    }

    /* Phone mockup: same Login/Home preview as the business creation
       wizard, but colors come from this page's own preset/palette engine
       (--preview-*) instead of raw brand[...] fields. */
    .brand-preview-tabs {
        display: flex;
        gap: 6px;
        justify-content: center;
        margin-bottom: 12px;
    }

    .brand-preview-tab {
        border: 1px solid var(--preview-border, rgba(255, 255, 255, 0.12));
        background: rgba(255, 255, 255, 0.04);
        color: var(--preview-secondary-text, #94a3b8);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        padding: 6px 14px;
        border-radius: 999px;
        cursor: pointer;
    }

    .brand-preview-tab.active {
        background: rgba(244, 198, 106, 0.16);
        border-color: rgba(244, 198, 106, 0.4);
        color: var(--preview-text, #f5f7fb);
    }

    .brand-preview-phone {
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 28px;
        padding: 10px;
        background: #05070d;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
    }

    .brand-preview-screen {
        position: relative;
        background: var(--preview-background, #07111f) center / cover no-repeat;
        color: var(--preview-text, #f5f7fb);
        border-radius: 20px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 480px;
        font-family: inherit;
        transition: background-color .15s ease, color .15s ease;
    }

    .brand-preview-hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(5,5,5,.55), rgba(9,9,9,.35), rgba(5,5,5,.68));
        display: none;
        pointer-events: none;
    }

    .brand-preview-hero-overlay.visible { display: block; }

    .brand-preview-panel {
        position: relative;
        display: none;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }

    .brand-preview-panel.active { display: flex; }

    .brand-preview-topspace { height: 18px; }

    .brand-preview-logo-wrap {
        display: flex;
        justify-content: center;
        padding: 6px 0 10px;
    }

    .brand-preview-logo {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--preview-primary, #d4a84f);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 20px;
        color: var(--preview-primary-text, #090909);
        overflow: hidden;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.08);
    }

    .brand-preview-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .brand-preview-card {
        margin: 4px 14px 14px;
        padding: 16px 14px;
        border-radius: 16px;
        background: var(--preview-card, rgba(255, 255, 255, 0.06));
        border: 1px solid var(--preview-border, rgba(255, 255, 255, 0.10));
    }

    .brand-preview-name {
        font-weight: 800;
        font-size: 15px;
        text-align: center;
        color: var(--preview-primary, #d4a84f);
        margin-bottom: 2px;
        overflow-wrap: anywhere;
    }

    .brand-preview-tagline {
        font-size: 11px;
        text-align: center;
        color: var(--preview-secondary-text, #94a3b8);
        margin-bottom: 12px;
        overflow-wrap: anywhere;
    }

    .brand-preview-input {
        background: var(--preview-input-bg, rgba(255, 255, 255, 0.07));
        border: 1px solid var(--preview-border, rgba(255, 255, 255, 0.10));
        border-radius: 10px;
        padding: 9px 10px;
        font-size: 11px;
        color: var(--preview-placeholder, #94a3b8);
        margin-bottom: 8px;
    }

    .brand-preview-button {
        margin-top: 4px;
        background: var(--preview-primary, #d4a84f);
        color: var(--preview-primary-text, #090909);
        text-align: center;
        font-weight: 700;
        font-size: 12px;
        padding: 10px;
        border-radius: 10px;
    }

    .brand-preview-navbar {
        margin-top: auto;
        display: flex;
        border-top: 1px solid var(--preview-border, rgba(255, 255, 255, 0.08));
        padding: 10px 4px 14px;
    }

    .brand-preview-nav-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        font-size: 9px;
        color: var(--preview-secondary-text, #94a3b8);
    }

    .brand-preview-nav-item.active {
        color: var(--preview-primary, #d4a84f);
        font-weight: 700;
    }

    .brand-preview-nav-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
    }

    .brand-preview-home-header {
        padding: 16px 14px 10px;
        background: var(--preview-primary, #d4a84f);
    }

    .brand-preview-home-greeting {
        font-size: 10px;
        font-weight: 700;
        opacity: .85;
        color: var(--preview-primary-text, #090909);
    }

    .brand-preview-home-title {
        font-size: 18px;
        font-weight: 800;
        color: var(--preview-primary-text, #090909);
    }

    .brand-preview-home-search {
        margin: 12px 14px 0;
        padding: 9px 12px;
        border-radius: 10px;
        background: var(--preview-input-bg, rgba(255, 255, 255, 0.08));
        border: 1px solid var(--preview-border, rgba(255, 255, 255, 0.12));
        font-size: 11px;
        color: var(--preview-placeholder, #94a3b8);
    }

    .brand-preview-home-section-title {
        margin: 16px 14px 8px;
        font-size: 11px;
        font-weight: 700;
        color: var(--preview-secondary-text, #94a3b8);
    }

    .brand-preview-home-cards {
        margin: 0 14px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .brand-preview-home-card {
        background: var(--preview-card, rgba(255, 255, 255, 0.06));
        border: 1px solid var(--preview-border, rgba(255, 255, 255, 0.08));
        border-radius: 12px;
        overflow: hidden;
        padding-bottom: 8px;
    }

    .brand-preview-home-card-thumb {
        height: 52px;
        background: linear-gradient(135deg, var(--preview-primary, #d4a84f), var(--preview-accent, #e8c36a));
    }

    .brand-preview-home-card-name {
        font-size: 10.5px;
        font-weight: 700;
        margin: 6px 8px 2px;
        color: var(--preview-text, #f5f7fb);
    }

    .brand-preview-home-card-price {
        font-size: 10px;
        color: var(--preview-secondary-text, #94a3b8);
        margin: 0 8px;
    }
</style>

<form method="POST" action="{{ $action }}" data-branding-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="card section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Identidad</h2>
                <p class="section-note">Datos base del negocio y su marca</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label>Nombre</label>
                <input name="name" value="{{ old('name', $form['name'] ?? '') }}" required>
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Slug</label>
                <input name="slug" value="{{ old('slug', $form['slug'] ?? '') }}" required>
                <div class="help">Debe ser unico. Se usa para identificar el tenant.</div>
                @error('slug') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Razon social</label>
                <input name="legal_name" value="{{ old('legal_name', $form['legal_name'] ?? '') }}">
                @error('legal_name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Tipo de negocio</label>
                <input name="business_type" value="{{ old('business_type', $form['business_type'] ?? '') }}" required>
                @error('business_type') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status" required>
                    @foreach (['active' => 'Activo', 'inactive' => 'Inactivo', 'suspended' => 'Suspendido'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $form['status'] ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>
    </section>

    <section class="card section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Branding simple</h2>
                <p class="section-note">Preset, color primario, color de acento y la identidad visible</p>
            </div>
        </div>

        <div class="branding-simple-grid">
            <div class="stack">
                <div class="form-grid">
                    <div class="field">
                        <label>Theme preset</label>
                        <select name="branding[appearance][theme_preset]" data-branding-preset>
                            @foreach ($themePresets as $value => $label)
                                <option value="{{ $value }}" @selected($themePresetValue === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="help">El preset define el modo visual automaticamente.</div>
                        @error('branding.appearance.theme_preset') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label>Color primario</label>
                        <div class="color-pair">
                            <div class="color-pair__row">
                                <input type="color" value="{{ $primaryColor }}" data-branding-primary-picker>
                                <input class="color-pair__hex" name="branding[colors][primary]" value="{{ $primaryColor }}" placeholder="#RRGGBB" data-branding-primary-hex>
                            </div>
                            <div class="help">Se sincroniza con el selector nativo y valida HEX.</div>
                            @error('branding.colors.primary') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label>Color de acento (opcional)</label>
                        <div class="color-pair">
                            <div class="color-pair__row">
                                <input type="color" value="{{ $accentColor }}" data-branding-accent-picker>
                                <input class="color-pair__hex" name="branding[colors][accent]" value="{{ $accentColor }}" placeholder="#RRGGBB" data-branding-accent-hex>
                            </div>
                            <div class="help">Si lo dejas como esta, el preset aporta el acento base.</div>
                            @error('branding.colors.accent') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label>App name</label>
                        <input name="branding[identity][app_name]" value="{{ old('branding.identity.app_name', $identity['app_name'] ?? '') }}" required data-preview-field="app_name">
                        @error('branding.identity.app_name') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label>Display name</label>
                        <input name="branding[identity][display_name]" value="{{ $previewDisplayName }}" required data-preview-field="display_name">
                        @error('branding.identity.display_name') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label>Short name</label>
                        <input name="branding[identity][short_name]" value="{{ $previewShortName }}" required data-preview-field="short_name">
                        @error('branding.identity.short_name') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label>Tagline</label>
                        <input name="branding[identity][tagline]" value="{{ $previewTagline }}" data-preview-field="tagline">
                        @error('branding.identity.tagline') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field full">
                        <label>Subtitle</label>
                        <input name="branding[identity][subtitle]" value="{{ $previewSubtitle }}" data-preview-field="subtitle">
                        @error('branding.identity.subtitle') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label>Location short</label>
                        <input name="branding[identity][location_short]" value="{{ old('branding.identity.location_short', $identity['location_short'] ?? '') }}" data-preview-field="location_short">
                        @error('branding.identity.location_short') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field full">
                        <label>Location full</label>
                        <input name="branding[identity][location_full]" value="{{ old('branding.identity.location_full', $identity['location_full'] ?? '') }}" data-preview-field="location_full">
                        @error('branding.identity.location_full') <div class="error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <details class="advanced-details">
                    <summary>Configuracion avanzada</summary>
                    <div class="advanced-details__body">
                        <p class="section-note" style="margin-bottom: 16px;">Valores derivados, campos legados y ajustes internos conservados por compatibilidad.</p>

                        <div class="form-grid">
                            @foreach ($advancedColorFields as $key => $label)
                                <div class="field">
                                    <label>{{ $label }}</label>
                                    <input name="branding[colors][{{ $key }}]" value="{{ old('branding.colors.' . $key, $colors[$key] ?? '') }}" placeholder="#RRGGBB">
                                    @error('branding.colors.' . $key) <div class="error">{{ $message }}</div> @enderror
                                </div>
                            @endforeach

                            @foreach ($advancedAppearanceFields as $key => $spec)
                                <div class="field">
                                    <label>{{ $spec['label'] }}</label>
                                    @if ($spec['type'] === 'checkbox')
                                        <label style="display:flex;align-items:center;gap:8px;margin:0;">
                                            <input type="checkbox" name="branding[appearance][{{ $key }}]" value="1" {{ old('branding.appearance.' . $key, $appearance[$key] ?? false) ? 'checked' : '' }} style="width:auto;">
                                            <span>{{ $spec['label'] }}</span>
                                        </label>
                                    @else
                                        <input
                                            type="{{ $spec['type'] }}"
                                            min="{{ $spec['min'] }}"
                                            max="{{ $spec['max'] }}"
                                            name="branding[appearance][{{ $key }}]"
                                            value="{{ old('branding.appearance.' . $key, $appearance[$key] ?? ($key === 'card_radius' ? 24 : 18)) }}"
                                        >
                                    @endif
                                    @error('branding.appearance.' . $key) <div class="error">{{ $message }}</div> @enderror
                                </div>
                            @endforeach

                            @foreach ($assetFields as $key => $label)
                                <div class="field">
                                    <label>{{ $label }} reference</label>
                                    <input name="branding[assets][{{ $key }}]" value="{{ old('branding.assets.' . $key, $assets[$key] ?? '') }}" placeholder="assets/... or https://...">
                                    <div class="help">Accepts a safe local asset path or a valid URL.</div>
                                    @error('branding.assets.' . $key) <div class="error">{{ $message }}</div> @enderror
                                </div>
                            @endforeach

                            @foreach ($termFields as $key => $label)
                                <div class="field">
                                    <label>{{ $label }}</label>
                                    <input name="branding[terminology][{{ $key }}]" value="{{ old('branding.terminology.' . $key, $terminology[$key] ?? '') }}">
                                    @error('branding.terminology.' . $key) <div class="error">{{ $message }}</div> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>
            </div>

            <aside class="branding-preview" data-branding-preview-root>
                <div class="branding-preview__inner">
                    <div class="branding-preview__eyebrow">Vista previa en vivo — asi se veria la app</div>

                    <div class="brand-preview-tabs">
                        <button type="button" class="brand-preview-tab active" data-preview-tab="login">Login</button>
                        <button type="button" class="brand-preview-tab" data-preview-tab="home">Home</button>
                    </div>

                    <div class="brand-preview-phone">
                        <div class="brand-preview-screen" data-preview-screen>
                            <div class="brand-preview-hero-overlay" data-preview-hero-overlay></div>

                            <div class="brand-preview-panel active" data-preview-panel="login">
                                <div class="brand-preview-topspace"></div>
                                <div class="brand-preview-logo-wrap">
                                    <div class="brand-preview-logo" data-preview-logo>
                                        <img data-preview-logo-img hidden alt="">
                                        <span data-preview-mark>{{ $previewShortName }}</span>
                                    </div>
                                </div>
                                <div class="brand-preview-card">
                                    <div class="brand-preview-name" data-preview-display-name>{{ $previewDisplayName }}</div>
                                    <div class="brand-preview-tagline" data-preview-tagline>{{ $previewTagline ?: $previewSubtitle }}</div>
                                    <div class="brand-preview-input">Correo electronico</div>
                                    <div class="brand-preview-input">Contrasena</div>
                                    <div class="brand-preview-button">Iniciar sesion</div>
                                </div>
                            </div>

                            <div class="brand-preview-panel" data-preview-panel="home">
                                <div class="brand-preview-home-header">
                                    <div class="brand-preview-home-greeting">Hola, Cliente</div>
                                    <div class="brand-preview-home-title">Buenos dias</div>
                                </div>
                                <div class="brand-preview-home-search">Buscar servicios...</div>
                                <div class="brand-preview-home-section-title">Servicios populares</div>
                                <div class="brand-preview-home-cards">
                                    <div class="brand-preview-home-card">
                                        <div class="brand-preview-home-card-thumb"></div>
                                        <div class="brand-preview-home-card-name">Servicio</div>
                                        <div class="brand-preview-home-card-price">&mdash;</div>
                                    </div>
                                    <div class="brand-preview-home-card">
                                        <div class="brand-preview-home-card-thumb"></div>
                                        <div class="brand-preview-home-card-name">Servicio</div>
                                        <div class="brand-preview-home-card-price">&mdash;</div>
                                    </div>
                                </div>
                            </div>

                            <div class="brand-preview-navbar">
                                <div class="brand-preview-nav-item active">
                                    <span class="brand-preview-nav-dot"></span>Inicio
                                </div>
                                <div class="brand-preview-nav-item">
                                    <span class="brand-preview-nav-dot"></span>Servicios
                                </div>
                                <div class="brand-preview-nav-item">
                                    <span class="brand-preview-nav-dot"></span>Reservas
                                </div>
                                <div class="brand-preview-nav-item">
                                    <span class="brand-preview-nav-dot"></span>Perfil
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="card section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Contacto</h2>
                <p class="section-note">Canales publicos del negocio</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label>Telefono</label>
                <input name="contact[phone]" value="{{ old('contact.phone', $form['contact']['phone'] ?? '') }}">
                @error('contact.phone') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>WhatsApp</label>
                <input name="contact[whatsapp]" value="{{ old('contact.whatsapp', $form['contact']['whatsapp'] ?? '') }}">
                @error('contact.whatsapp') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Email</label>
                <input name="contact[email]" type="email" value="{{ old('contact.email', $form['contact']['email'] ?? '') }}">
                @error('contact.email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Instagram</label>
                <input name="contact[instagram]" value="{{ old('contact.instagram', $form['contact']['instagram'] ?? '') }}">
                @error('contact.instagram') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Website</label>
                <input name="contact[website]" value="{{ old('contact.website', $form['contact']['website'] ?? '') }}">
                @error('contact.website') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field full">
                <label>Direccion</label>
                <input name="contact[address]" value="{{ old('contact.address', $form['contact']['address'] ?? '') }}">
                @error('contact.address') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>
    </section>

    <section class="card section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Politicas</h2>
                <p class="section-note">Reglas de cancelacion y reprogramacion</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label>Ventana de cancelacion (horas)</label>
                <input name="policies[cancellation_window_hours]" type="number" min="0" max="168" value="{{ old('policies.cancellation_window_hours', $form['policies']['cancellation_window_hours'] ?? '') }}">
                @error('policies.cancellation_window_hours') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field full">
                <label>Politica de cancelacion</label>
                <textarea name="policies[cancellation_policy_text]">{{ old('policies.cancellation_policy_text', $form['policies']['cancellation_policy_text'] ?? '') }}</textarea>
                @error('policies.cancellation_policy_text') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>
    </section>

    <section class="card section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Features</h2>
                <p class="section-note">Flags de experiencia y administracion</p>
            </div>
        </div>

        <div class="form-grid">
            @foreach ([
                'show_staff' => 'Mostrar staff',
                'reservation_staff_selection' => 'Seleccion de staff en reservas',
                'admin_staff_management' => 'Gestion de staff admin',
                'show_gallery' => 'Mostrar galeria',
                'show_reviews' => 'Mostrar reseñas',
                'show_business_profile' => 'Mostrar perfil del negocio',
                'show_admin_dashboard' => 'Mostrar dashboard admin',
            ] as $key => $label)
                <div class="field">
                    <label>
                        <input type="checkbox" name="features[{{ $key }}]" value="1" {{ old('features.' . $key, $form['features'][$key] ?? false) ? 'checked' : '' }} style="width: auto; margin-right: 8px;">
                        {{ $label }}
                    </label>
                </div>
            @endforeach
        </div>
    </section>

    <div class="actions" style="margin-top: 18px;">
        <button class="button primary" type="submit">{{ $mode === 'create' ? 'Crear negocio' : 'Guardar cambios' }}</button>
        <a class="button ghost" href="{{ route('super-admin.businesses.index') }}">Cancelar</a>
    </div>
</form>

<script>
(function () {
    const root = document.querySelector('[data-branding-preview-root]');
    if (!root) {
        return;
    }

    const presetSelect = document.querySelector('[data-branding-preset]');
    const primaryPicker = document.querySelector('[data-branding-primary-picker]');
    const primaryHex = document.querySelector('[data-branding-primary-hex]');
    const accentPicker = document.querySelector('[data-branding-accent-picker]');
    const accentHex = document.querySelector('[data-branding-accent-hex]');
    const previewFields = {
        appName: document.querySelector('[data-preview-field="app_name"]'),
        displayName: document.querySelector('[data-preview-display-name]'),
        shortName: document.querySelector('[data-preview-mark]'),
        tagline: document.querySelector('[data-preview-tagline]'),
    };
    const previewScreen = document.querySelector('[data-preview-screen]');
    const previewHeroOverlay = document.querySelector('[data-preview-hero-overlay]');
    const previewLogoImg = document.querySelector('[data-preview-logo-img]');
    const logoUrlInput = document.querySelector('[name="branding[assets][logo_transparent]"]');
    const heroUrlInput = document.querySelector('[name="branding[assets][hero_background]"]');

    const presetPalettes = {
        elegant_light: {
            background: '#FBF7F3',
            surface: '#F4EDE8',
            card: '#FFFFFF',
            border: '#DCC9C2',
            textPrimary: '#1F1614',
            textSecondary: '#6D5751',
            textOnPrimary: '#1F1614',
            accent: '#A86E63',
        },
        elegant_dark: {
            background: '#090909',
            surface: '#1A1512',
            card: '#120E0B',
            border: 'rgba(255,255,255,0.13)',
            textPrimary: '#FFFFFF',
            textSecondary: '#B9AFA5',
            textOnPrimary: '#090909',
            accent: '#B77A3E',
        },
        salon_rose: {
            background: '#120F14',
            surface: '#201A21',
            card: '#171218',
            border: '#26FFFFFF',
            textPrimary: '#FFFFFF',
            textSecondary: '#E1CFC9',
            textOnPrimary: '#120F14',
            accent: '#C87D6E',
        },
        natural: {
            background: '#071719',
            surface: '#102528',
            card: '#0D1F22',
            border: 'rgba(255,255,255,0.13)',
            textPrimary: '#FFFFFF',
            textSecondary: '#C1DED9',
            textOnPrimary: '#071719',
            accent: '#4EA295',
        },
        corporate: {
            background: '#081425',
            surface: '#111C31',
            card: '#0F1829',
            border: 'rgba(255,255,255,0.13)',
            textPrimary: '#FFFFFF',
            textSecondary: '#C0D1E8',
            textOnPrimary: '#081425',
            accent: '#4C84D0',
        },
        barber_luxury: {
            background: '#090909',
            surface: '#1A1512',
            card: '#120E0B',
            border: 'rgba(255,255,255,0.13)',
            textPrimary: '#FFFFFF',
            textSecondary: '#B9AFA5',
            textOnPrimary: '#090909',
            accent: '#B77A3E',
        },
        clinic_clean: {
            background: '#081425',
            surface: '#111C31',
            card: '#0F1829',
            border: 'rgba(255,255,255,0.13)',
            textPrimary: '#FFFFFF',
            textSecondary: '#C0D1E8',
            textOnPrimary: '#081425',
            accent: '#4C84D0',
        },
    };

    function expandHex(hex) {
        if (!hex) {
            return null;
        }
        var normalized = String(hex).trim().replace(/^#/, '');
        if (/^[0-9a-fA-F]{3}$/.test(normalized)) {
            normalized = normalized.split('').map(function (part) {
                return part + part;
            }).join('');
        }
        if (/^[0-9a-fA-F]{6}$/.test(normalized)) {
            return '#' + normalized.toUpperCase();
        }
        if (/^[0-9a-fA-F]{8}$/.test(normalized)) {
            return '#' + normalized.toUpperCase();
        }
        return null;
    }

    function hexToRgb(hex) {
        var normalized = expandHex(hex);
        if (!normalized) {
            return null;
        }
        var raw = normalized.slice(1);
        if (raw.length === 8) {
            raw = raw.slice(2);
        }
        return {
            r: parseInt(raw.slice(0, 2), 16),
            g: parseInt(raw.slice(2, 4), 16),
            b: parseInt(raw.slice(4, 6), 16),
        };
    }

    function rgbToHex(r, g, b) {
        return '#' + [r, g, b].map(function (value) {
            return Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0');
        }).join('').toUpperCase();
    }

    function mixColor(colorA, colorB, ratio) {
        const a = hexToRgb(colorA);
        const b = hexToRgb(colorB);
        if (!a || !b) {
            return colorA || colorB || '#000000';
        }
        const weight = Math.max(0, Math.min(1, ratio));
        return rgbToHex(
            a.r * (1 - weight) + b.r * weight,
            a.g * (1 - weight) + b.g * weight,
            a.b * (1 - weight) + b.b * weight
        );
    }

    function luminance(hex) {
        const rgb = hexToRgb(hex);
        if (!rgb) {
            return 0;
        }
        function transform(channel) {
            const value = channel / 255;
            return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * transform(rgb.r) + 0.7152 * transform(rgb.g) + 0.0722 * transform(rgb.b);
    }

    function bestTextFor(background) {
        return luminance(background) > 0.5 ? '#000000' : '#FFFFFF';
    }

    function currentHex(input, fallback) {
        const value = expandHex(input?.value);
        return value || fallback;
    }

    function syncHexPair(picker, hexInput, fallback) {
        const next = currentHex(hexInput, fallback);
        if (picker && picker.value.toUpperCase() !== next) {
            picker.value = next.slice(0, 7);
        }
        if (hexInput && hexInput.value.toUpperCase() !== next) {
            hexInput.value = next;
        }
        return next;
    }

    function derivePalette(presetKey, primary, accent) {
        const base = presetPalettes[presetKey] || presetPalettes.elegant_light;
        const resolvedPrimary = primary || base.accent;
        const resolvedAccent = accent || base.accent;
        const background = base.background;
        const surface = mixColor(background, bestTextFor(background), 0.08);
        const card = mixColor(surface, bestTextFor(surface), 0.06);
        const border = base.border || mixColor(surface, background, 0.22);
        const textPrimary = base.textPrimary || bestTextFor(background);
        const textSecondary = base.textSecondary || mixColor(textPrimary, background, 0.38);
        const textOnPrimary = bestTextFor(resolvedPrimary);
        const inputBackground = mixColor(surface, background, 0.38);
        const placeholder = mixColor(textSecondary, background, 0.34);

        return {
            primary: resolvedPrimary,
            accent: resolvedAccent,
            background,
            surface,
            card,
            border,
            textPrimary,
            textSecondary,
            textOnPrimary,
            inputBackground,
            placeholder,
        };
    }

    function applyPalette(palette) {
        root.style.setProperty('--preview-primary', palette.primary);
        root.style.setProperty('--preview-primary-text', palette.textOnPrimary);
        root.style.setProperty('--preview-border', palette.border);
        root.style.setProperty('--preview-text', palette.textPrimary);
        root.style.setProperty('--preview-secondary-text', palette.textSecondary);
        root.style.setProperty('--preview-input-bg', palette.inputBackground);
        root.style.setProperty('--preview-placeholder', palette.placeholder);
        root.style.setProperty('--preview-surface', palette.surface);
        root.style.setProperty('--preview-card', palette.card);
        root.style.setProperty('--preview-background', palette.background);
        root.style.setProperty('--preview-accent', palette.accent);
    }

    function renderPreview() {
        const preset = presetSelect ? presetSelect.value : 'elegant_light';
        const primary = syncHexPair(primaryPicker, primaryHex, '#D4A84F');
        const accent = syncHexPair(accentPicker, accentHex, presetPalettes[preset]?.accent || '#A86E63');
        const palette = derivePalette(preset, primary, accent);
        applyPalette(palette);

        if (previewFields.displayName) {
            previewFields.displayName.textContent = (document.querySelector('[data-preview-field="display_name"]')?.value || @json($previewDisplayName)).trim() || 'Marca';
        }
        if (previewFields.shortName) {
            previewFields.shortName.textContent = (document.querySelector('[data-preview-field="short_name"]')?.value || @json($previewShortName)).trim() || 'BM';
        }
        if (previewFields.tagline) {
            const tagline = (document.querySelector('[data-preview-field="tagline"]')?.value || '').trim();
            const subtitle = (document.querySelector('[data-preview-field="subtitle"]')?.value || '').trim();
            previewFields.tagline.textContent = tagline || subtitle || '';
        }
    }

    function syncLogoPreview() {
        const url = (logoUrlInput?.value || '').trim();
        if (!previewLogoImg) {
            return;
        }
        if (url) {
            previewLogoImg.onerror = function () {
                previewLogoImg.hidden = true;
                if (previewFields.shortName) previewFields.shortName.hidden = false;
            };
            previewLogoImg.src = url;
            previewLogoImg.hidden = false;
            if (previewFields.shortName) previewFields.shortName.hidden = true;
        } else {
            previewLogoImg.hidden = true;
            previewLogoImg.removeAttribute('src');
            if (previewFields.shortName) previewFields.shortName.hidden = false;
        }
    }

    function syncHeroPreview() {
        const url = (heroUrlInput?.value || '').trim();
        if (!previewScreen) {
            return;
        }
        if (url) {
            previewScreen.style.backgroundImage = `url("${url.replace(/"/g, '%22')}")`;
            if (previewHeroOverlay) previewHeroOverlay.classList.add('visible');
        } else {
            previewScreen.style.backgroundImage = '';
            if (previewHeroOverlay) previewHeroOverlay.classList.remove('visible');
        }
    }

    [presetSelect, primaryPicker, primaryHex, accentPicker, accentHex].forEach(function (input) {
        if (!input) {
            return;
        }
        input.addEventListener('input', renderPreview);
        input.addEventListener('change', renderPreview);
    });

    document.querySelectorAll('[data-preview-field]').forEach(function (input) {
        input.addEventListener('input', renderPreview);
        input.addEventListener('change', renderPreview);
    });

    if (logoUrlInput) {
        logoUrlInput.addEventListener('input', syncLogoPreview);
        logoUrlInput.addEventListener('change', syncLogoPreview);
    }

    if (heroUrlInput) {
        heroUrlInput.addEventListener('input', syncHeroPreview);
        heroUrlInput.addEventListener('change', syncHeroPreview);
    }

    document.querySelectorAll('[data-preview-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.dataset.previewTab;
            document.querySelectorAll('[data-preview-tab]').forEach(function (t) {
                t.classList.toggle('active', t === tab);
            });
            document.querySelectorAll('[data-preview-panel]').forEach(function (panel) {
                panel.classList.toggle('active', panel.dataset.previewPanel === target);
            });
        });
    });

    renderPreview();
    syncLogoPreview();
    syncHeroPreview();
})();
</script>
