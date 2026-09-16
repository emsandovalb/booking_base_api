<?php

namespace App\Services\SuperAdmin;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\SuperAdmin\AuditLogService;

class BusinessWizardService
{
    public function defaults(): array
    {
        $defaults = config('white_label');

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
        $defaults = config('white_label');
        $schedule = $data['hours']['schedule'] ?? $this->defaultSchedule();

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
                'assets' => $defaults['assets'] ?? [],
                'colors' => [
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
                ],
            ],
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
            if (!($day['is_open'] ?? false)) {
                $lines[] = $dayLabel . ' closed';
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
            if (!($day['is_open'] ?? false)) {
                $details[] = $dayLabel . ' closed';
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
