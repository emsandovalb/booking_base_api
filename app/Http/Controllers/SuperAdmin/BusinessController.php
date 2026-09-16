<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Support\BrandingConfig;
use App\Services\SuperAdmin\AuditLogService;
use App\Services\SuperAdmin\BusinessWizardService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function index()
    {
        $businesses = Business::query()
            ->withCount([
                'courts as resources_count',
                'staff as staff_count',
                'bookings as bookings_count',
                'users as members_count',
            ])
            ->latest()
            ->paginate(20);

        return view('super-admin.businesses.index', [
            'businesses' => $businesses,
        ]);
    }

    public function show(Business $business, Request $request, AuditLogService $auditLogService)
    {
        $business->loadCount([
            'courts as resources_count',
            'staff as staff_count',
            'bookings as bookings_count',
            'users as members_count',
        ])->load([
            'users' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email')
                    ->orderBy('name');
            },
        ]);

        $filters = $this->activityFilters($request);
        $logs = $auditLogService->recentForBusiness($business, $filters, 20);

        return view('super-admin.businesses.show', [
            'business' => $business,
            'configPreview' => $this->configPreview($business),
            'activityLogs' => $logs,
            'activityRows' => $logs->map(fn (AuditLog $log) => $auditLogService->present($log)),
            'activityFilters' => $filters,
            'activityActionOptions' => AuditLog::actionOptions(),
            'activityUsers' => $business->users,
        ]);
    }

    public function create(BusinessWizardService $wizardService)
    {
        return view('super-admin.businesses.create', [
            'form' => $wizardService->defaults(),
            'existingSlugs' => $wizardService->existingSlugs(),
            'businessTypes' => $wizardService->businessTypes(),
            'featureLabels' => $wizardService->featureLabels(),
            'weekDays' => $wizardService->weekDays(),
        ]);
    }

    public function store(Request $request, BusinessWizardService $wizardService, AuditLogService $auditLogService)
    {
        $data = $request->validate($this->wizardRules($wizardService));
        $business = $wizardService->createBusiness($data, $request->user(), $auditLogService);

        return redirect()
            ->route('super-admin.businesses.workspace', $business)
            ->with('status', 'Business creado correctamente.');
    }

    public function edit(Business $business)
    {
        return view('super-admin.businesses.edit', [
            'business' => $business,
            'form' => $this->formDefaults($business),
        ]);
    }

    public function update(Request $request, Business $business, AuditLogService $auditLogService)
    {
        $before = $this->businessSnapshot($business);
        $request->merge($this->normalizeBusinessUpdateInput($request->all(), $business));
        $data = $this->validateBusiness($request, $business);
        $business->update($this->mapPayload($request, $data, $business));
        $after = $this->businessSnapshot($business);

        $auditLogService->record(
            $business,
            $request->user(),
            'business.updated',
            $business,
            $before,
            $after,
            ['source' => 'business-edit']
        );

        $auditLogService->record(
            $business,
            $request->user(),
            'workspace.configuration_updated',
            $business,
            $before,
            $after,
            ['source' => 'workspace']
        );

        return redirect()
            ->route('super-admin.businesses.show', $business)
            ->with('status', 'Configuración actualizada.');
    }

    public function activate(Business $business, Request $request, AuditLogService $auditLogService)
    {
        $before = $this->businessSnapshot($business);
        $business->update(['status' => 'active']);

        $auditLogService->record(
            $business,
            $request->user(),
            'business.activated',
            $business,
            $before,
            $this->businessSnapshot($business),
            ['source' => 'status-toggle']
        );

        return back()->with('status', 'Business activado.');
    }

    public function suspend(Business $business, Request $request, AuditLogService $auditLogService)
    {
        $before = $this->businessSnapshot($business);
        $business->update(['status' => 'suspended']);

        $auditLogService->record(
            $business,
            $request->user(),
            'business.suspended',
            $business,
            $before,
            $this->businessSnapshot($business),
            ['source' => 'status-toggle']
        );

        return back()->with('status', 'Business suspendido.');
    }

    private function wizardRules(BusinessWizardService $wizardService): array
    {
        $rules = [
            'business.name' => ['required', 'string', 'max:255'],
            'business.slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('businesses', 'slug'),
            ],
            'business.legal_name' => ['nullable', 'string', 'max:255'],
            'business.business_type' => ['required', 'string', 'max:100'],
            'business.status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'brand.app_name' => ['required', 'string', 'max:255'],
            'brand.display_name' => ['required', 'string', 'max:255'],
            'brand.short_name' => ['required', 'string', 'max:100'],
            'brand.tagline' => ['nullable', 'string', 'max:255'],
            'brand.subtitle' => ['nullable', 'string', 'max:255'],
            'brand.primary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand.secondary_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'brand.background_color' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.whatsapp' => ['nullable', 'string', 'max:50'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.website' => ['nullable', 'url', 'max:255'],
            'contact.instagram' => ['nullable', 'string', 'max:255'],
            'contact.facebook' => ['nullable', 'string', 'max:255'],
            'contact.address' => ['nullable', 'string', 'max:255'],
            'contact.country' => ['nullable', 'string', 'max:255'],
            'contact.city' => ['nullable', 'string', 'max:255'],
            'hours.cancellation_window_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'hours.cancellation_policy_text' => ['nullable', 'string', 'max:1000'],
            'owner.full_name' => ['required', 'string', 'max:255'],
            'owner.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'owner.password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        foreach (array_keys($wizardService->weekDays()) as $day) {
            $rules["hours.schedule.{$day}.is_open"] = ['nullable', 'boolean'];
            $rules["hours.schedule.{$day}.opens_at"] = ['required_if:hours.schedule.' . $day . '.is_open,1', 'date_format:H:i'];
            $rules["hours.schedule.{$day}.closes_at"] = [
                'required_if:hours.schedule.' . $day . '.is_open,1',
                'date_format:H:i',
                'after:hours.schedule.' . $day . '.opens_at',
            ];
        }

        return $rules;
    }

    private function validateBusiness(Request $request, ?Business $business = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('businesses', 'slug')->ignore($business?->id),
            ],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'branding.identity.app_name' => ['required', 'string', 'max:255'],
            'branding.identity.display_name' => ['required', 'string', 'max:255'],
            'branding.identity.short_name' => ['required', 'string', 'max:100'],
            'branding.identity.tagline' => ['nullable', 'string', 'max:255'],
            'branding.identity.subtitle' => ['nullable', 'string', 'max:255'],
            'branding.identity.location_short' => ['nullable', 'string', 'max:255'],
            'branding.identity.location_full' => ['nullable', 'string', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.whatsapp' => ['nullable', 'string', 'max:50'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.instagram' => ['nullable', 'string', 'max:255'],
            'contact.website' => ['nullable', 'url', 'max:255'],
            'contact.address' => ['nullable', 'string', 'max:255'],
            'branding.assets.logo_transparent' => ['nullable', 'string', 'max:255'],
            'branding.assets.logo_dark' => ['nullable', 'string', 'max:255'],
            'branding.assets.logo_light' => ['nullable', 'string', 'max:255'],
            'branding.assets.hero_background' => ['nullable', 'string', 'max:255'],
            'branding.assets.login_background' => ['nullable', 'string', 'max:255'],
            'branding.assets.onboarding_background' => ['nullable', 'string', 'max:255'],
            'branding.assets.service_placeholder' => ['nullable', 'string', 'max:255'],
            'branding.assets.premium_service_placeholder' => ['nullable', 'string', 'max:255'],
            'branding.assets.staff_placeholder' => ['nullable', 'string', 'max:255'],
            'branding.assets.profile_placeholder' => ['nullable', 'string', 'max:255'],
            'branding.colors.primary' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.primary_light' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.primary_dark' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.secondary' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.accent' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.background' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.surface' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.card' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.border' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.text_primary' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.text_secondary' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.text_on_primary' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.input_background' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.placeholder' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.disabled_text' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.disabled_background' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.success' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.warning' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.danger' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.primary_gold' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.primary_gold_light' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.colors.primary_gold_dark' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'branding.appearance.theme_mode' => ['nullable', Rule::in(['dark', 'light'])],
            'branding.appearance.theme_preset' => ['required', Rule::in(BrandingConfig::supportedThemePresets())],
            'branding.appearance.card_radius' => ['nullable', 'integer', 'min:0', 'max:64'],
            'branding.appearance.input_radius' => ['nullable', 'integer', 'min:0', 'max:64'],
            'branding.appearance.button_radius' => ['nullable', 'integer', 'min:0', 'max:64'],
            'branding.appearance.use_cinematic_backgrounds' => ['nullable', 'boolean'],
            'branding.appearance.use_logo_glow' => ['nullable', 'boolean'],
            'branding.appearance.use_heavy_blur' => ['nullable', 'boolean'],
            'branding.terminology.business' => ['nullable', 'string', 'max:100'],
            'branding.terminology.business_profile' => ['nullable', 'string', 'max:100'],
            'branding.terminology.service' => ['nullable', 'string', 'max:100'],
            'branding.terminology.services' => ['nullable', 'string', 'max:100'],
            'branding.terminology.appointment' => ['nullable', 'string', 'max:100'],
            'branding.terminology.appointments' => ['nullable', 'string', 'max:100'],
            'branding.terminology.staff' => ['nullable', 'string', 'max:100'],
            'branding.terminology.staff_plural' => ['nullable', 'string', 'max:100'],
            'branding.terminology.staff_display_name' => ['nullable', 'string', 'max:100'],
            'branding.terminology.manager' => ['nullable', 'string', 'max:100'],
            'branding.terminology.gallery' => ['nullable', 'string', 'max:100'],
            'branding.terminology.reviews' => ['nullable', 'string', 'max:100'],
            'policies.cancellation_window_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'policies.cancellation_policy_text' => ['nullable', 'string', 'max:1000'],
            'features.show_staff' => ['nullable', 'boolean'],
            'features.reservation_staff_selection' => ['nullable', 'boolean'],
            'features.admin_staff_management' => ['nullable', 'boolean'],
            'features.show_gallery' => ['nullable', 'boolean'],
            'features.show_reviews' => ['nullable', 'boolean'],
            'features.show_business_profile' => ['nullable', 'boolean'],
            'features.show_admin_dashboard' => ['nullable', 'boolean'],
        ]);
    }

    private function mapPayload(Request $request, array $data, ?Business $business = null): array
    {
        $defaults = config('white_label');
        $currentAppConfig = $business?->app_config ?? [];
        $currentContactConfig = $business?->contact_config ?? [];
        $currentBrandingConfig = $business?->branding_config ?? [];
        $currentFeatureConfig = $business?->feature_config ?? [];
        $branding = BrandingConfig::normalizeBrandingInput(
            $data['branding'] ?? [],
            $data['business_type'] ?? $business?->business_type,
            $business?->slug
        );
        $identity = array_replace_recursive($defaults['identity'] ?? [], $branding['identity'] ?? []);
        $contact = array_replace_recursive(
            $defaults['contact'] ?? [],
            data_get($currentContactConfig, 'contact', []),
            $data['contact'] ?? []
        );
        $policies = array_replace_recursive(
            $defaults['policies'] ?? [],
            data_get($currentAppConfig, 'policies', []),
            $data['policies'] ?? []
        );
        $hours = array_replace_recursive(
            $defaults['hours'] ?? [],
            data_get($currentAppConfig, 'hours', [])
        );
        $features = array_merge($defaults['features'] ?? [], data_get($currentFeatureConfig, 'features', []), [
            'show_staff' => $request->boolean('features.show_staff'),
            'reservation_staff_selection' => $request->boolean('features.reservation_staff_selection'),
            'admin_staff_management' => $request->boolean('features.admin_staff_management'),
            'show_gallery' => $request->boolean('features.show_gallery'),
            'show_reviews' => $request->boolean('features.show_reviews'),
            'show_business_profile' => $request->boolean('features.show_business_profile'),
            'show_admin_dashboard' => $request->boolean('features.show_admin_dashboard'),
        ]);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'legal_name' => $data['legal_name'] ?? null,
            'business_type' => $data['business_type'],
            'status' => $data['status'],
            'app_config' => [
                'identity' => [
                    'app_name' => $identity['app_name'],
                    'display_name' => $identity['display_name'],
                    'short_name' => $identity['short_name'],
                    'tagline' => $identity['tagline'] ?? null,
                    'subtitle' => $identity['subtitle'] ?? null,
                    'location_short' => $identity['location_short'] ?? null,
                    'location_full' => $identity['location_full'] ?? null,
                    'rating' => $identity['rating'] ?? null,
                    'review_count' => $identity['review_count'] ?? null,
                ],
                'hours' => $hours,
                'policies' => [
                    'cancellation_window_hours' => $policies['cancellation_window_hours'] ?? null,
                    'cancellation_policy_text' => $policies['cancellation_policy_text'] ?? null,
                ],
                'terminology' => $branding['terminology'] ?? $defaults['terminology'] ?? [],
            ],
            'contact_config' => [
                'contact' => [
                    'phone' => $contact['phone'] ?? null,
                    'whatsapp' => $contact['whatsapp'] ?? null,
                    'email' => $contact['email'] ?? null,
                    'instagram' => $contact['instagram'] ?? null,
                    'website' => $contact['website'] ?? null,
                    'facebook' => $contact['facebook'] ?? null,
                    'address' => $contact['address'] ?? null,
                    'country' => $contact['country'] ?? null,
                    'city' => $contact['city'] ?? null,
                ],
            ],
            'branding_config' => [
                'identity' => $branding['identity'] ?? [],
                'assets' => $branding['assets'] ?? [],
                'colors' => $branding['colors'] ?? [],
                'appearance' => $branding['appearance'] ?? [],
                'terminology' => $branding['terminology'] ?? [],
            ],
            'feature_config' => [
                'features' => $features,
            ],
            'metadata' => [
                'source' => 'super-admin',
                'managed_by' => $request->user()?->id,
            ],
        ];
    }

    private function normalizeBusinessUpdateInput(array $data, ?Business $business = null): array
    {
        $branding = is_array($data['branding'] ?? null) ? $data['branding'] : [];
        if (!isset($branding['identity']) && isset($data['identity'])) {
            $branding['identity'] = $data['identity'];
        }
        if (!isset($branding['appearance']) && isset($data['appearance'])) {
            $branding['appearance'] = $data['appearance'];
        }

        $legacyColorKeys = [
            'primary_gold',
            'primary_gold_light',
            'primary_gold_dark',
        ];
        $legacyColors = Arr::only($branding, $legacyColorKeys);
        if ($legacyColors !== []) {
            $branding['colors'] = array_replace_recursive($branding['colors'] ?? [], $legacyColors);
        }

        $preset = BrandingConfig::presetForBusiness($business);
        $branding['appearance'] = array_replace_recursive(
            $preset['appearance'] ?? [],
            $branding['appearance'] ?? []
        );

        $data['branding'] = $branding;

        return $data;
    }

    private function formDefaults(?Business $business = null): array
    {
        $defaults = config('white_label');
        $businessConfig = $business ? $this->configPreview($business) : [];
        $brandingDefaults = BrandingConfig::presetForBusiness($business);

        return [
            'name' => $business->name ?? ($defaults['identity']['app_name'] ?? ''),
            'slug' => $business->slug ?? '',
            'legal_name' => $business->legal_name ?? '',
            'business_type' => $business->business_type ?? 'barbershop',
            'status' => $business->status ?? 'active',
            'branding' => [
                'identity' => [
                    'app_name' => data_get($businessConfig, 'identity.app_name', $brandingDefaults['identity']['app_name'] ?? ''),
                    'display_name' => data_get($businessConfig, 'identity.display_name', $brandingDefaults['identity']['display_name'] ?? ''),
                    'short_name' => data_get($businessConfig, 'identity.short_name', $brandingDefaults['identity']['short_name'] ?? ''),
                    'tagline' => data_get($businessConfig, 'identity.tagline', $brandingDefaults['identity']['tagline'] ?? ''),
                    'subtitle' => data_get($businessConfig, 'identity.subtitle', $brandingDefaults['identity']['subtitle'] ?? ''),
                    'location_short' => data_get($businessConfig, 'identity.location_short', $brandingDefaults['identity']['location_short'] ?? ''),
                    'location_full' => data_get($businessConfig, 'identity.location_full', $brandingDefaults['identity']['location_full'] ?? ''),
                ],
                'assets' => [
                    'logo_transparent' => data_get($businessConfig, 'assets.logo_transparent', $brandingDefaults['assets']['logo_transparent'] ?? ''),
                    'logo_dark' => data_get($businessConfig, 'assets.logo_dark', $brandingDefaults['assets']['logo_dark'] ?? ''),
                    'logo_light' => data_get($businessConfig, 'assets.logo_light', $brandingDefaults['assets']['logo_light'] ?? ''),
                    'hero_background' => data_get($businessConfig, 'assets.hero_background', $brandingDefaults['assets']['hero_background'] ?? ''),
                    'login_background' => data_get($businessConfig, 'assets.login_background', $brandingDefaults['assets']['login_background'] ?? ''),
                    'onboarding_background' => data_get($businessConfig, 'assets.onboarding_background', $brandingDefaults['assets']['onboarding_background'] ?? ''),
                    'service_placeholder' => data_get($businessConfig, 'assets.service_placeholder', $brandingDefaults['assets']['service_placeholder'] ?? ''),
                    'premium_service_placeholder' => data_get($businessConfig, 'assets.premium_service_placeholder', $brandingDefaults['assets']['premium_service_placeholder'] ?? ''),
                    'staff_placeholder' => data_get($businessConfig, 'assets.staff_placeholder', $brandingDefaults['assets']['staff_placeholder'] ?? ''),
                    'profile_placeholder' => data_get($businessConfig, 'assets.profile_placeholder', $brandingDefaults['assets']['profile_placeholder'] ?? ''),
                ],
                'colors' => [
                    'primary' => data_get($businessConfig, 'colors.primary', $brandingDefaults['colors']['primary'] ?? ''),
                    'primary_light' => data_get($businessConfig, 'colors.primary_light', $brandingDefaults['colors']['primary_light'] ?? ''),
                    'primary_dark' => data_get($businessConfig, 'colors.primary_dark', $brandingDefaults['colors']['primary_dark'] ?? ''),
                    'secondary' => data_get($businessConfig, 'colors.secondary', $brandingDefaults['colors']['secondary'] ?? ''),
                    'accent' => data_get($businessConfig, 'colors.accent', $brandingDefaults['colors']['accent'] ?? ''),
                    'background' => data_get($businessConfig, 'colors.background', $brandingDefaults['colors']['background'] ?? ''),
                    'surface' => data_get($businessConfig, 'colors.surface', $brandingDefaults['colors']['surface'] ?? ''),
                    'card' => data_get($businessConfig, 'colors.card', $brandingDefaults['colors']['card'] ?? ''),
                    'border' => data_get($businessConfig, 'colors.border', $brandingDefaults['colors']['border'] ?? ''),
                    'text_primary' => data_get($businessConfig, 'colors.text_primary', $brandingDefaults['colors']['text_primary'] ?? ''),
                    'text_secondary' => data_get($businessConfig, 'colors.text_secondary', $brandingDefaults['colors']['text_secondary'] ?? ''),
                    'text_on_primary' => data_get($businessConfig, 'colors.text_on_primary', $brandingDefaults['colors']['text_on_primary'] ?? ''),
                    'success' => data_get($businessConfig, 'colors.success', $brandingDefaults['colors']['success'] ?? ''),
                    'warning' => data_get($businessConfig, 'colors.warning', $brandingDefaults['colors']['warning'] ?? ''),
                    'danger' => data_get($businessConfig, 'colors.danger', $brandingDefaults['colors']['danger'] ?? ''),
                    'primary_gold' => data_get($businessConfig, 'colors.primary_gold', $brandingDefaults['colors']['primary_gold'] ?? ''),
                    'primary_gold_light' => data_get($businessConfig, 'colors.primary_gold_light', $brandingDefaults['colors']['primary_gold_light'] ?? ''),
                    'primary_gold_dark' => data_get($businessConfig, 'colors.primary_gold_dark', $brandingDefaults['colors']['primary_gold_dark'] ?? ''),
                ],
                'appearance' => [
                    'theme_mode' => data_get($businessConfig, 'appearance.theme_mode', $brandingDefaults['appearance']['theme_mode'] ?? 'dark'),
                    'theme_preset' => data_get($businessConfig, 'appearance.theme_preset', $brandingDefaults['appearance']['theme_preset'] ?? 'generic_dark'),
                    'card_radius' => data_get($businessConfig, 'appearance.card_radius', $brandingDefaults['appearance']['card_radius'] ?? 24),
                    'input_radius' => data_get($businessConfig, 'appearance.input_radius', $brandingDefaults['appearance']['input_radius'] ?? 18),
                    'button_radius' => data_get($businessConfig, 'appearance.button_radius', $brandingDefaults['appearance']['button_radius'] ?? 18),
                    'use_cinematic_backgrounds' => data_get($businessConfig, 'appearance.use_cinematic_backgrounds', $brandingDefaults['appearance']['use_cinematic_backgrounds'] ?? true),
                    'use_logo_glow' => data_get($businessConfig, 'appearance.use_logo_glow', $brandingDefaults['appearance']['use_logo_glow'] ?? true),
                    'use_heavy_blur' => data_get($businessConfig, 'appearance.use_heavy_blur', $brandingDefaults['appearance']['use_heavy_blur'] ?? false),
                ],
                'terminology' => [
                    'business' => data_get($businessConfig, 'terminology.business', $brandingDefaults['terminology']['business'] ?? ''),
                    'business_profile' => data_get($businessConfig, 'terminology.business_profile', $brandingDefaults['terminology']['business_profile'] ?? ''),
                    'service' => data_get($businessConfig, 'terminology.service', $brandingDefaults['terminology']['service'] ?? ''),
                    'services' => data_get($businessConfig, 'terminology.services', $brandingDefaults['terminology']['services'] ?? ''),
                    'appointment' => data_get($businessConfig, 'terminology.appointment', $brandingDefaults['terminology']['appointment'] ?? ''),
                    'appointments' => data_get($businessConfig, 'terminology.appointments', $brandingDefaults['terminology']['appointments'] ?? ''),
                    'staff' => data_get($businessConfig, 'terminology.staff', $brandingDefaults['terminology']['staff'] ?? ''),
                    'staff_plural' => data_get($businessConfig, 'terminology.staff_plural', $brandingDefaults['terminology']['staff_plural'] ?? ''),
                    'staff_display_name' => data_get($businessConfig, 'terminology.staff_display_name', $brandingDefaults['terminology']['staff_display_name'] ?? ''),
                    'manager' => data_get($businessConfig, 'terminology.manager', $brandingDefaults['terminology']['manager'] ?? ''),
                    'gallery' => data_get($businessConfig, 'terminology.gallery', $brandingDefaults['terminology']['gallery'] ?? ''),
                    'reviews' => data_get($businessConfig, 'terminology.reviews', $brandingDefaults['terminology']['reviews'] ?? ''),
                ],
            ],
            'contact' => [
                'phone' => data_get($businessConfig, 'contact.phone', $defaults['contact']['phone'] ?? ''),
                'whatsapp' => data_get($businessConfig, 'contact.whatsapp', $defaults['contact']['whatsapp'] ?? ''),
                'email' => data_get($businessConfig, 'contact.email', $defaults['contact']['email'] ?? ''),
                'instagram' => data_get($businessConfig, 'contact.instagram', $defaults['contact']['instagram'] ?? ''),
                'website' => data_get($businessConfig, 'contact.website', $defaults['contact']['website'] ?? ''),
                'address' => data_get($businessConfig, 'contact.address', $defaults['contact']['address'] ?? ''),
            ],
            'policies' => [
                'cancellation_window_hours' => data_get($businessConfig, 'policies.cancellation_window_hours', $defaults['policies']['cancellation_window_hours'] ?? ''),
                'cancellation_policy_text' => data_get($businessConfig, 'policies.cancellation_policy_text', $defaults['policies']['cancellation_policy_text'] ?? ''),
            ],
            'features' => [
                'show_staff' => (bool) data_get($businessConfig, 'features.show_staff', $defaults['features']['show_staff'] ?? false),
                'reservation_staff_selection' => (bool) data_get($businessConfig, 'features.reservation_staff_selection', $defaults['features']['reservation_staff_selection'] ?? false),
                'admin_staff_management' => (bool) data_get($businessConfig, 'features.admin_staff_management', $defaults['features']['admin_staff_management'] ?? false),
                'show_gallery' => (bool) data_get($businessConfig, 'features.show_gallery', $defaults['features']['show_gallery'] ?? false),
                'show_reviews' => (bool) data_get($businessConfig, 'features.show_reviews', $defaults['features']['show_reviews'] ?? false),
                'show_business_profile' => (bool) data_get($businessConfig, 'features.show_business_profile', $defaults['features']['show_business_profile'] ?? false),
                'show_admin_dashboard' => (bool) data_get($businessConfig, 'features.show_admin_dashboard', $defaults['features']['show_admin_dashboard'] ?? false),
            ],
        ];
    }

    private function configPreview(Business $business): array
    {
        return BrandingConfig::resolveForBusiness($business);
    }

    private function activityFilters(Request $request): array
    {
        return [
            'date' => $request->string('date')->toString(),
            'action' => $request->string('action')->toString(),
            'user' => $request->string('user')->toString(),
            'search' => $request->string('search')->toString(),
            'business' => $request->string('business')->toString(),
        ];
    }

    private function businessSnapshot(Business $business): array
    {
        return [
            'name' => $business->name,
            'slug' => $business->slug,
            'legal_name' => $business->legal_name,
            'business_type' => $business->business_type,
            'status' => $business->status,
            'app_config' => $business->app_config,
            'contact_config' => $business->contact_config,
            'branding_config' => $business->branding_config,
            'feature_config' => $business->feature_config,
            'metadata' => $business->metadata,
        ];
    }
}
