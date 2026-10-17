<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Support\BrandingConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class AppConfigController extends Controller
{
    /**
     * Branding is read on every app start but changes rarely, so successful
     * responses carry an ETag and a short public max-age. Clients that send
     * the ETag back get an empty 304, and edits show up within a minute.
     */
    private const CACHE_MAX_AGE_SECONDS = 60;

    public function showDefault(Request $request): JsonResponse
    {
        $config = BrandingConfig::basePreset();

        return $this->cacheable($request, response()->json($config + [
            'business_type' => 'generic',
            'api_base_url' => url('/api/v1'),
        ]));
    }

    public function showForBusinessSlug(Request $request, string $slug): JsonResponse
    {
        $business = Business::resolveBySlug($slug);

        if (!$business) {
            return response()->json([
                'message' => 'Business not found.',
            ], 404);
        }

        return $this->cacheable($request, response()->json($this->resolveConfigForBusiness($business)));
    }

    public function showPublicBusinessConfig(Request $request, string $slug): JsonResponse
    {
        $business = Business::resolveBySlug($slug);

        if (!$business) {
            return response()->json([
                'message' => 'Business not found.',
            ], 404);
        }

        return $this->cacheable($request, response()->json($this->resolvePublicConfigForBusiness($business, $request)));
    }

    public function show(Request $request): JsonResponse
    {
        return $this->showDefault($request);
    }

    private function cacheable(Request $request, JsonResponse $response): JsonResponse
    {
        $response->setPublic();
        $response->setMaxAge(self::CACHE_MAX_AGE_SECONDS);
        $response->setEtag(sha1($response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    private function resolveConfigForBusiness(Business $business): array
    {
        $config = array_replace_recursive(
            BrandingConfig::resolveForBusiness($business),
            [
                'contact' => Arr::get($business->contact_config, 'contact', []),
                'hours' => Arr::get($business->app_config, 'hours', []),
                'policies' => Arr::get($business->app_config, 'policies', []),
                'features' => Arr::get($business->feature_config, 'features', []),
            ]
        );

        return $this->sanitizeBrandingPayload($business, $config);
    }

    private function resolvePublicConfigForBusiness(Business $business, Request $request): array
    {
        $config = $this->resolveConfigForBusiness($business);
        $identity = Arr::get($config, 'identity', []);
        $assets = Arr::get($config, 'assets', []);
        $colors = Arr::get($config, 'colors', []);
        $appearance = Arr::get($config, 'appearance', []);
        $contact = Arr::only(Arr::get($config, 'contact', []), [
            'phone',
            'whatsapp',
            'website',
            'instagram',
            'facebook',
            'address',
        ]);

        return [
            'business_id' => $business->id,
            'slug' => $business->slug,
            'display_name' => $identity['display_name'] ?? $business->name,
            'short_name' => $identity['short_name'] ?? $business->name,
            'logo_url' => $assets['logo_transparent'] ?? $assets['app_icon'] ?? '',
            'primary_color' => $colors['primary'] ?? $colors['primary_gold'] ?? '',
            'secondary_color' => $colors['primary_light'] ?? $colors['primary_gold_light'] ?? '',
            'background_color' => $colors['background'] ?? '',
            'contact_phone' => $contact['phone'] ?? '',
            'api_base_url' => rtrim($request->getSchemeAndHttpHost(), '/') . '/api/v1',
            'business_type' => $business->business_type ?? 'generic',
            'identity' => $identity,
            'assets' => $assets,
            'colors' => $colors,
            'appearance' => $appearance,
            'contact' => $contact,
            'hours' => Arr::get($config, 'hours', []),
            'policies' => Arr::get($config, 'policies', []),
            'terminology' => Arr::get($config, 'terminology', []),
            'features' => Arr::get($config, 'features', []),
        ];
    }

    private function sanitizeBrandingPayload(Business $business, array $config): array
    {
        return $config;
    }
}
