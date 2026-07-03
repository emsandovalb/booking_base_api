<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function showDefault(): JsonResponse
    {
        return response()->json(config('white_label'));
    }

    public function showForBusinessSlug(string $slug): JsonResponse
    {
        $business = Business::query()
            ->active()
            ->where('slug', $slug)
            ->first();

        if (!$business) {
            return response()->json([
                'message' => 'Business not found.',
            ], 404);
        }

        return response()->json($this->resolveConfigForBusiness($business));
    }

    public function show(): JsonResponse
    {
        return $this->showDefault();
    }

    private function resolveConfigForBusiness(Business $business): array
    {
        $config = config('white_label');

        return array_replace_recursive(
            $config,
            $business->app_config ?? [],
            $business->contact_config ?? [],
            $business->branding_config ?? [],
            $business->feature_config ?? [],
        );
    }
}
