<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Staff;
use Illuminate\Http\Request;

class ResourceStaffController extends Controller
{
    public function index(Request $request, Court $resource)
    {
        $staff = Staff::query()
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
