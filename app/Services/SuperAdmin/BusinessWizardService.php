<?php

namespace App\Services\SuperAdmin;

use App\Models\Business;
use App\Models\Court;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Models\User;
use App\Support\BrandingConfig;
use Illuminate\Support\Facades\DB;

class BusinessWizardService
{
    public function defaults(): array
    {
        $defaults = BrandingConfig::barbershopPreset();

        return [
            'business' => [
                'name' => '',
                'slug' => '',
                'legal_name' => '',
                'business_type' => 'barbershop',
                'status' => 'active',
            ],
            'brand' => [
                'app_name' => data_get($defaults, 'identity.app_name', 'Bemuss'),
                'display_name' => data_get($defaults, 'identity.display_name', 'BEMUSS'),
                'short_name' => data_get($defaults, 'identity.short_name', 'Bemuss'),
                'tagline' => data_get($defaults, 'identity.tagline', ''),
                'subtitle' => data_get($defaults, 'identity.subtitle', ''),
                'primary_color' => data_get($defaults, 'colors.primary_gold', '#D4A84F'),
                'secondary_color' => data_get($defaults, 'colors.primary_gold_light', '#E8C36A'),
                'background_color' => data_get($defaults, 'colors.background', '#07111f'),
                'logo_path' => '',
                'hero_path' => '',
            ],
            'contact' => [
                'phone' => data_get($defaults, 'contact.phone', ''),
                'whatsapp' => data_get($defaults, 'contact.whatsapp', ''),
                'email' => data_get($defaults, 'contact.email', ''),
                'website' => data_get($defaults, 'contact.website', ''),
                'instagram' => data_get($defaults, 'contact.instagram', ''),
                'facebook' => data_get($defaults, 'contact.facebook', ''),
                'address' => data_get($defaults, 'contact.address', ''),
                'country' => '',
                'city' => '',
            ],
            'hours' => [
                'cancellation_window_hours' => data_get($defaults, 'policies.cancellation_window_hours', 4),
                'cancellation_policy_text' => data_get($defaults, 'policies.cancellation_policy_text', ''),
                'schedule' => $this->defaultSchedule(),
            ],
            'owner' => [
                'full_name' => '',
                'email' => '',
            ],
            'setup' => [
                'services' => [
                    ['name' => '', 'price' => '', 'duration_minutes' => 30],
                    ['name' => '', 'price' => '', 'duration_minutes' => 30],
                    ['name' => '', 'price' => '', 'duration_minutes' => 30],
                    ['name' => '', 'price' => '', 'duration_minutes' => 30],
                ],
                'staff' => [
                    ['name' => '', 'phone' => ''],
                    ['name' => '', 'phone' => ''],
                    ['name' => '', 'phone' => ''],
                    ['name' => '', 'phone' => ''],
                ],
            ],
            'features' => array_map(
                fn ($enabled) => (bool) $enabled,
                array_replace(array_fill_keys(array_keys($this->featureLabels()), true), data_get($defaults, 'features', []))
            ),
        ];
    }

    public function createBusiness(array $data, ?User $actor, AuditLogService $auditLogService): Business
    {
        return DB::transaction(function () use ($data, $actor, $auditLogService) {
            $business = Business::create($this->mapPayloadForCreate($data, $actor));

            $owner = User::create([
                'name' => $data['owner']['full_name'],
                'email' => $data['owner']['email'],
                'password' => $data['owner']['password'],
            ]);

            $business->users()->attach($owner->id, [
                'role' => 'owner',
                'status' => 'active',
                'accepted_at' => now(),
                'metadata' => [
                    'source' => 'super-admin-wizard',
                    'created_by' => $actor?->id,
                ],
            ]);

            $this->createInitialCatalog($business, $owner, $data);

            $auditLogService->record(
                $business,
                $actor,
                'business.wizard.created',
                $business,
                [],
                $this->auditSnapshot($business),
                [
                    'source' => 'wizard',
                    'owner_user_id' => $owner->id,
                ]
            );

            return $business->fresh();
        });
    }

    public function existingSlugs(): array
    {
        return Business::query()->pluck('slug')->all();
    }

    public function businessTypes(): array
    {
        return [
            'barbershop' => 'Barbershop',
            'salon' => 'Salon',
            'spa' => 'Spa',
            'clinic' => 'Clinic',
            'other' => 'Other',
        ];
    }

    public function featureLabels(): array
    {
        return [
            'show_gallery' => 'Gallery',
            'show_reviews' => 'Reviews',
            'show_business_profile' => 'Business Profile',
            'show_staff' => 'Staff',
            'admin_staff_management' => 'Staff Management',
            'reservation_staff_selection' => 'Reservations',
            'show_admin_dashboard' => 'Admin Dashboard',
        ];
    }

    public function weekDays(): array
    {
        return [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];
    }

    private function mapPayloadForCreate(array $data, ?User $actor): array
    {
        $defaults = BrandingConfig::presetForBusiness(
            null,
            $data['business']['business_type'] ?? 'barbershop',
            $data['business']['slug'] ?? null,
        );
        $schedule = $data['hours']['schedule'] ?? $this->defaultSchedule();
        $assets = $defaults['assets'] ?? [];
        $logoPath = $data['brand']['logo_path'] ?? null;
        $heroPath = $data['brand']['hero_path'] ?? null;
        $initialServicesCount = count(array_filter($data['setup']['services'] ?? [], fn (array $service) => filled($service['name'] ?? null)));
        $initialStaffCount = count(array_filter($data['setup']['staff'] ?? [], fn (array $staff) => filled($staff['name'] ?? null)));
        if ($logoPath) {
            $assets['logo_transparent'] = $logoPath;
            $assets['logo_dark'] = $logoPath;
            $assets['logo_light'] = $logoPath;
            $assets['app_icon'] = $logoPath;
        }
        if ($heroPath) {
            $assets['hero_background'] = $heroPath;
            $assets['login_background'] = $heroPath;
            $assets['onboarding_background'] = $heroPath;
        }

        return [
            'name' => $data['business']['name'],
            'slug' => $data['business']['slug'],
            'legal_name' => $data['business']['legal_name'] ?? null,
            'business_type' => $data['business']['business_type'],
            'status' => $data['business']['status'],
            'app_config' => [
                'identity' => [
                    'app_name' => $data['brand']['app_name'],
                    'display_name' => $data['brand']['display_name'],
                    'short_name' => $data['brand']['short_name'],
                    'tagline' => $data['brand']['tagline'] ?? null,
                    'subtitle' => $data['brand']['subtitle'] ?? null,
                    'location_short' => $data['contact']['city'] ?: ($defaults['identity']['location_short'] ?? null),
                    'location_full' => $this->joinLocation($data['contact']['city'] ?? null, $data['contact']['country'] ?? null),
                    'rating' => $defaults['identity']['rating'] ?? null,
                    'review_count' => $defaults['identity']['review_count'] ?? null,
                ],
                'hours' => [
                    'label' => $defaults['hours']['label'] ?? 'Horario de atención',
                    'weekly_summary' => $this->buildWeeklySummary($schedule),
                    'detailed_hours' => $this->buildDetailedHours($schedule),
                ],
                'policies' => [
                    'cancellation_window_hours' => $data['hours']['cancellation_window_hours'] ?? null,
                    'cancellation_policy_text' => $data['hours']['cancellation_policy_text'] ?? null,
                ],
                'terminology' => $defaults['terminology'] ?? [],
            ],
            'contact_config' => [
                'contact' => [
                    'phone' => $data['contact']['phone'] ?? null,
                    'whatsapp' => $data['contact']['whatsapp'] ?? null,
                    'email' => $data['contact']['email'] ?? null,
                    'website' => $data['contact']['website'] ?? null,
                    'instagram' => $data['contact']['instagram'] ?? null,
                    'facebook' => $data['contact']['facebook'] ?? null,
                    'address' => $data['contact']['address'] ?? null,
                    'country' => $data['contact']['country'] ?? null,
                    'city' => $data['contact']['city'] ?? null,
                ],
            ],
            'branding_config' => [
                'assets' => $assets,
                'colors' => [
                    'primary' => $data['brand']['primary_color'] ?? null,
                    'primary_light' => $data['brand']['secondary_color'] ?? null,
                    'primary_dark' => $defaults['colors']['primary_dark'] ?? null,
                    'secondary' => $defaults['colors']['secondary'] ?? null,
                    'accent' => $defaults['colors']['accent'] ?? null,
                    'primary_gold' => $data['brand']['primary_color'] ?? null,
                    'primary_gold_light' => $data['brand']['secondary_color'] ?? null,
                    'primary_gold_dark' => $defaults['colors']['primary_gold_dark'] ?? null,
                    'background' => $data['brand']['background_color'] ?? null,
                    'surface' => $defaults['colors']['surface'] ?? null,
                    'card' => $defaults['colors']['card'] ?? null,
                    'border' => $defaults['colors']['border'] ?? null,
                    'text_primary' => $defaults['colors']['text_primary'] ?? null,
                    'text_secondary' => $defaults['colors']['text_secondary'] ?? null,
                ],
                'appearance' => $defaults['appearance'] ?? [],
            ],
            'feature_config' => [
                'features' => array_map(fn ($enabled) => (bool) $enabled, $data['features'] ?? []),
            ],
            'metadata' => [
                'source' => 'super-admin-wizard',
                'managed_by' => $actor?->id,
                'onboarding' => [
                    'owner' => [
                        'full_name' => $data['owner']['full_name'] ?? null,
                        'email' => $data['owner']['email'] ?? null,
                    ],
                    'weekly_schedule' => $schedule,
                    'initial_services_count' => $initialServicesCount,
                    'initial_staff_count' => $initialStaffCount,
                    'ready_for_handoff' => $initialServicesCount > 0 && $initialStaffCount > 0,
                ],
            ],
        ];
    }

    private function createInitialCatalog(Business $business, User $owner, array $data): void
    {
        $schedule = $data['hours']['schedule'] ?? $this->defaultSchedule();
        [$openHour, $closeHour] = $this->catalogHours($schedule);
        $hoursNote = $this->buildWeeklySummary($schedule);
        $address = filled($data['contact']['address'] ?? null)
            ? trim($data['contact']['address'])
            : ($this->joinLocation($data['contact']['city'] ?? null, $data['contact']['country'] ?? null) ?: 'Por definir');
        $serviceImage = data_get($business->branding_config, 'assets.service_placeholder');
        $services = [];

        foreach ($data['setup']['services'] ?? [] as $serviceData) {
            if (! filled($serviceData['name'] ?? null)) {
                continue;
            }

            $services[] = Court::create([
                'business_id' => $business->id,
                'owner_id' => $owner->id,
                'name' => trim($serviceData['name']),
                'address' => $address,
                'price_per_hour' => $serviceData['price'],
                'duration_hours' => 1,
                'duration_minutes' => $serviceData['duration_minutes'],
                'open_hour' => $openHour,
                'close_hour' => $closeHour,
                'business_hours_note' => $hoursNote,
                'rating' => 0,
                'images' => $serviceImage ? [$serviceImage] : [],
                'status' => 'active',
            ]);
        }

        $role = StaffRole::firstOrCreate(
            ['slug' => 'barber'],
            ['name' => 'Barbero', 'description' => 'Profesional de barbería'],
        );

        foreach ($data['setup']['staff'] ?? [] as $staffData) {
            if (! filled($staffData['name'] ?? null)) {
                continue;
            }

            $staff = Staff::create([
                'business_id' => $business->id,
                'staff_role_id' => $role->id,
                'name' => trim($staffData['name']),
                'phone' => $staffData['phone'] ?? null,
                'is_active' => true,
            ]);

            $staff->courts()->sync(collect($services)->mapWithKeys(
                fn (Court $service, int $index) => [$service->id => ['is_primary' => $index === 0]],
            )->all());
        }
    }

    private function catalogHours(array $schedule): array
    {
        $openDays = collect($schedule)->filter(fn (array $day) => (bool) ($day['is_open'] ?? false));

        if ($openDays->isEmpty()) {
            return ['09:00', '18:00'];
        }

        return [
            $openDays->min(fn (array $day) => $day['opens_at'] ?? '09:00'),
            $openDays->max(fn (array $day) => $day['closes_at'] ?? '18:00'),
        ];
    }

    private function defaultSchedule(): array
    {
        return [
            'monday' => ['is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00'],
            'tuesday' => ['is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00'],
            'wednesday' => ['is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00'],
            'thursday' => ['is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00'],
            'friday' => ['is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00'],
            'saturday' => ['is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00'],
            'sunday' => ['is_open' => false, 'opens_at' => '09:00', 'closes_at' => '18:00'],
        ];
    }

    private function buildWeeklySummary(array $schedule): string
    {
        $lines = [];

        foreach ($this->weekDays() as $dayKey => $dayLabel) {
            $day = $schedule[$dayKey] ?? [];
            if (! ($day['is_open'] ?? false)) {
                $lines[] = $dayLabel.' closed';

                continue;
            }

            $lines[] = sprintf(
                '%s %s - %s',
                $dayLabel,
                $day['opens_at'] ?? '09:00',
                $day['closes_at'] ?? '18:00'
            );
        }

        return implode("\n", $lines);
    }

    private function buildDetailedHours(array $schedule): array
    {
        $details = [];

        foreach ($this->weekDays() as $dayKey => $dayLabel) {
            $day = $schedule[$dayKey] ?? [];
            if (! ($day['is_open'] ?? false)) {
                $details[] = $dayLabel.' closed';

                continue;
            }

            $details[] = sprintf(
                '%s %s - %s',
                $dayLabel,
                $day['opens_at'] ?? '09:00',
                $day['closes_at'] ?? '18:00'
            );
        }

        return $details;
    }

    private function joinLocation(?string $city, ?string $country): ?string
    {
        $parts = array_values(array_filter([$city, $country]));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function auditSnapshot(Business $business): array
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
