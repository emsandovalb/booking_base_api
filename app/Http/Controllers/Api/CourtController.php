<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Booking;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class CourtController extends Controller
{
    public function index(Request $request)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        $q = Court::query();
        $context->applyTo($q);
        $q->with(['staff.role']);
        $q->where('status', 'active');
        if ($search = $request->query('q')) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%$search%")
                   ->orWhere('address', 'like', "%$search%");
            });
        }
        if ($cat = $request->query('category')) {
            $q->where('category', $cat);
        }
        if ($dur = $request->query('duration')) {
            $q->where('duration_hours', (int) $dur);
        }
        if ($min = $request->query('min_price')) {
            $q->where('price_per_hour', '>=', (float) $min);
        }
        if ($max = $request->query('max_price')) {
            $q->where('price_per_hour', '<=', (float) $max);
        }
        if ($sort = $request->query('sort')) {
            if ($sort === 'rating') $q->orderByDesc('rating');
            if ($sort === 'price_asc') $q->orderBy('price_per_hour');
            if ($sort === 'price_desc') $q->orderByDesc('price_per_hour');
        } else {
            $q->latest();
        }
        $perPage = $this->perPageFromRequest($request);
        $result = $q->paginate($perPage)->withQueryString();
        return $this->paginatedResponse($result);
    }

    public function reservationsForDay(Request $request)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }
        $day = $request->query('day');
        try {
            $date = CarbonImmutable::parse($day ?: CarbonImmutable::now()->toDateString());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid date'], 422);
        }
        $start = $date->startOfDay();
        $end = $date->endOfDay();
        $items = Booking::with(['court', 'user', 'staff'])
            ->whereBetween('date', [$start, $end]);
        if ($context->hasSlug()) {
            $items->where('business_id', $context->businessId());
        }
        $items = $items->orderBy('time_slot')->get();
        return ['data' => $items];
    }

    public function show(Request $request, Court $court)
    {
        if (!$this->canViewCourt($request, $court)) {
            return response()->json(['message' => 'Not found'], 404);
        }
        return $court->load(['staff.role']);
    }

    public function availability(Request $request, Court $court)
    {
        if (!$this->canViewCourt($request, $court)) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $date = $request->query('date'); // YYYY-MM-DD
        if (!$date) {
            return response()->json(['message' => 'date is required (YYYY-MM-DD)'], 422);
        }
        try {
            $dayStart = Carbon::parse($date . ' 00:00:00');
        } catch (\Throwable $e) {
            return response()->json(['message' => 'invalid date'], 422);
        }
        $dayEnd = (clone $dayStart)->endOfDay();
        $items = Booking::where('court_id', $court->id)
            ->whereBetween('date', [$dayStart, $dayEnd])
            ->get();

        $booked = [];
        foreach ($items as $b) {
            /** @var Carbon $start */
            $start = Carbon::parse($b->date);
            $end = $this->parseSlotEnd($start, $b->time_slot, $court->duration_hours ?? 1);
            $booked[] = [
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
            ];
        }

        return ['booked' => $booked];
    }

    private function parseSlotEnd(Carbon $start, string $slot, int $fallbackHours): Carbon
    {
        // Expect formats like: "10:00 AM to 12:00 PM"
        if (preg_match('/to\s*(\d{1,2}):(\d{2})\s*([AP]M)/i', $slot, $m)) {
            $h = ((int)$m[1]) % 12;
            $min = (int)$m[2];
            $ampm = strtoupper($m[3]);
            if ($ampm === 'PM') $h += 12;
            return (clone $start)->setTime($h, $min, 0);
        }
        return (clone $start)->addHours($fallbackHours ?: 1);
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
            'name' => 'required',
            'address' => 'required',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'duration_hours' => 'nullable|integer|min:1',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'open_hour' => 'nullable|string',
            'close_hour' => 'nullable|string',
            'business_hours_note' => 'nullable|string',
            'price_per_hour' => 'nullable|numeric',
            'rating' => 'nullable|numeric',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'images' => 'nullable|array|max:10',
            'images.*' => 'string|max:5000',
        ]);
        if (!empty($data['images']) && is_array($data['images'])) {
            $data['images'] = $this->processAndValidateImages($data['images']);
        }
        $court = Court::create(array_merge($data, [
            'owner_id' => $request->user()->id,
            'business_id' => $context->currentBusinessId(),
            'status' => 'active',
        ]));
        return response()->json($court, 201);
    }

    public function mine(Request $request)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if (($request->user()->role ?? null) !== 'admin') {
            return response()->json(['data' => []]);
        }
        $perPage = $this->perPageFromRequest($request);
        $query = Court::with(['staff.role'])
            ->where('owner_id', $request->user()->id)
            ->latest();
        $context->applyTo($query);
        $result = $query->paginate($perPage)->withQueryString();
        return $this->paginatedResponse($result);
    }

    public function update(Request $request, Court $court)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }
        $data = $request->validate([
            'name' => 'sometimes|required|string',
            'address' => 'sometimes|required|string',
            'description' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string',
            'duration_hours' => 'sometimes|nullable|integer|min:1',
            'duration_minutes' => 'sometimes|nullable|integer|min:1|max:1440',
            'open_hour' => 'sometimes|nullable|string',
            'close_hour' => 'sometimes|nullable|string',
            'business_hours_note' => 'sometimes|nullable|string',
            'price_per_hour' => 'sometimes|nullable|numeric',
            'rating' => 'sometimes|nullable|numeric',
            'contact_email' => 'sometimes|nullable|email',
            'contact_phone' => 'sometimes|nullable|string',
            'images' => 'sometimes|array|max:10',
            'images.*' => 'string|max:5000',
            'status' => 'sometimes|in:active,inactive',
        ]);
        if (array_key_exists('images', $data) && is_array($data['images'])) {
            $data['images'] = $this->processAndValidateImages($data['images']);
        }
        $court->fill($data);
        $court->save();
        return $court->fresh();
    }

    public function destroy(Request $request, Court $court)
    {
        $context = BusinessContext::fromRequest($request);
        if (!$context->isValid()) {
            return response()->json(['message' => 'Business not found'], 404);
        }
        if ($response = $this->authorizeBusinessAdmin($request, $context)) {
            return $response;
        }
        $court->status = 'inactive';
        $court->save();
        return response()->json(['message' => 'Court deactivated', 'court' => $court]);
    }

    private function perPageFromRequest(Request $request): int
    {
        $per = (int) $request->query('per_page', 20);
        if ($per < 1) $per = 1;
        if ($per > 50) $per = 50;
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

    private function processAndValidateImages(array $images): array
    {
        $stored = [];
        foreach ($images as $index => $img) {
            $stored[] = $this->storeImageString($img, $index);
        }
        return $stored;
    }

    private function storeImageString(mixed $img, ?int $index = null): string
    {
        if (!is_string($img)) {
            $this->failImageValidation($index, 'Image entry must be a string.');
        }
        $trim = trim($img);
        if ($trim === '') {
            $this->failImageValidation($index, 'Image entry cannot be empty.');
        }

        // Allow reusing existing court storage URLs only
        if (Str::startsWith($trim, ['/storage/courts/'])) {
            return $trim;
        }

        $maxBytes = 2 * 1024 * 1024; // 2MB per image
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        if (Str::startsWith($trim, 'data:image')) {
            $parts = explode(',', $trim, 2);
            if (count($parts) !== 2) {
                $this->failImageValidation($index, 'Invalid data URL format for image.');
            }
            $meta = $parts[0];
            $data = $parts[1];
            $mime = 'image/jpeg';
            if (preg_match('/data:(.*?);base64/', $meta, $matches)) {
                $candidate = $matches[1] ?: $mime;
                if (!in_array($candidate, $allowedMimes, true)) {
                    $this->failImageValidation($index, 'Unsupported image mime type.');
                }
                $mime = $candidate;
            }
            if (!in_array($mime, $allowedMimes, true)) {
                $this->failImageValidation($index, 'Unsupported image mime type.');
            }
            $binary = base64_decode($data, true);
            if ($binary === false) {
                $this->failImageValidation($index, 'Malformed base64 image data.');
            }
            if (strlen($binary) > $maxBytes) {
                $this->failImageValidation($index, 'Image is too large.');
            }
            return $this->storeBinaryImage($binary, $mime);
        }

        // Raw base64 without header
        if (strlen($trim) > 100 && preg_match('/^[A-Za-z0-9+\/]+=*$/', $trim)) {
            $binary = base64_decode($trim, true);
            if ($binary === false) {
                $this->failImageValidation($index, 'Malformed base64 image data.');
            }
            if (strlen($binary) > $maxBytes) {
                $this->failImageValidation($index, 'Image is too large.');
            }
            // Default to jpeg for bare base64 images
            return $this->storeBinaryImage($binary, 'image/jpeg');
        }

        $this->failImageValidation($index, 'Unsupported image format or source.');
    }

    private function storeBinaryImage(string $binary, string $mime): string
    {
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $name = 'courts/' . Str::uuid() . '.' . $ext;
        Storage::disk('public')->put($name, $binary);
        return Storage::url($name);
    }

    private function failImageValidation(?int $index, string $message): void
    {
        $key = is_null($index) ? 'images' : "images.$index";
        throw ValidationException::withMessages([
            $key => [$message],
        ]);
    }

    private function canViewCourt(Request $request, Court $court): bool
    {
        if ($court->status === 'active') {
            return true;
        }
        $user = $request->user();
        return $user && $user->role === 'admin';
    }

    private function authorizeBusinessAdmin(Request $request, BusinessContext $context): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user || ($user->role ?? null) !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$context->hasSlug()) {
            return null;
        }

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
