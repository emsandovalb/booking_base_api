<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Staff;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

class ResourceStaffController extends Controller
{
    public function index(Request $request, Court $resource)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $staff = Staff::query()
            ->when($context->hasSlug(), function ($query) use ($context) {
                $query->where('business_id', $context->businessId());
            })
            ->whereHas('services', function ($query) use ($resource) {
                $query->where('court_id', $resource->id);
            })
            ->with([
                'role',
                'user',
                'services' => function ($query) use ($resource) {
                    $query->where('court_id', $resource->id)->with('resource');
                },
            ])
            ->withCount([
                'services as services_count' => function ($query) use ($resource) {
                    $query->where('court_id', $resource->id);
                },
            ])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $staff]);
    }
}
