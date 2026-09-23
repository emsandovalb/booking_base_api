<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessClosure;
use App\Models\Staff;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

class BusinessClosureController extends Controller
{
    public function index(Request $request)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $query = BusinessClosure::query()
            ->with('staff:id,name')
            ->where('business_id', $context->businessId())
            ->orderBy('date');

        if ($request->filled('from')) {
            $query->where('date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->query('to'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (!empty($data['staff_id'])) {
            $staffQuery = Staff::query()->whereKey($data['staff_id']);
            $context->applyTo($staffQuery);
            if (!$staffQuery->exists()) {
                return response()->json(['message' => 'Selected staff does not belong to this business'], 422);
            }
        }

        $closure = BusinessClosure::query()->firstOrCreate(
            [
                'business_id' => $context->businessId(),
                'staff_id' => $data['staff_id'] ?? null,
                'date' => $data['date'],
            ],
            [
                'reason' => $data['reason'] ?? null,
                'created_by' => $request->user()->id,
            ],
        );

        return response()->json($closure->load('staff:id,name'), 201);
    }

    public function destroy(Request $request, BusinessClosure $closure)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }

        $closure->delete();

        return response()->json(['message' => 'Closure removed']);
    }

    private function authorizeBusinessAdmin(Request $request, BusinessContext $context): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $business = $context->currentBusiness();
        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if (!$context->userCanManageBusiness($user, $business)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }
}
