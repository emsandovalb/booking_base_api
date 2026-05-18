<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $this->perPageFromRequest($request);
        $result = Staff::query()
            ->with(['role', 'user', 'services.resource'])
            ->withCount('services')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse($result);
    }

    public function show(Request $request, Staff $staff)
    {
        return $staff->load(['role', 'user', 'services.resource'])->loadCount('services');
    }

    private function perPageFromRequest(Request $request): int
    {
        $per = (int) $request->query('per_page', 20);
        if ($per < 1) {
            $per = 1;
        }
        if ($per > 50) {
            $per = 50;
        }

        return $per;
    }

    private function paginatedResponse($paginator)
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }
}
