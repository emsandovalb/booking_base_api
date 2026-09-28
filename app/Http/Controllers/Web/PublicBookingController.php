<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Staff;
use App\Services\BookingAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function __construct(private readonly BookingAvailabilityService $availability) {}

    public function show(string $slug): View
    {
        $business = $this->business($slug);
        $services = $business->courts()
            ->where('status', 'active')
            ->with(['staff' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('booking.public', compact('business', 'services'));
    }

    public function availability(Request $request, string $slug): JsonResponse
    {
        $business = $this->business($slug);
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);
        [$service, $staff] = $this->selection($business, $data['service_id'], $data['staff_id']);

        return response()->json(['slots' => $this->availability->slots($service, $staff, $data['date'])]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $business = $this->business($slug);
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:190'],
        ]);
        [$service, $staff] = $this->selection($business, $data['service_id'], $data['staff_id']);

        // Shared key with API and owner-side rescheduling: every entry point
        // competes for the same professional's calendar lock.
        $lock = Cache::lock("booking-lock:business:{$business->id}:staff:{$staff->id}", 10);
        try {
            $booking = $lock->block(3, function () use ($business, $service, $staff, $data) {
                return DB::transaction(function () use ($business, $service, $staff, $data) {
                    Staff::query()->whereKey($staff->id)->lockForUpdate()->first();
                    if (! $this->availability->hasSlot($service, $staff, $data['date'], $data['time'])) {
                        return null;
                    }

                    $start = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['time']}");
                    $minutes = max(10, (int) ($service->duration_minutes ?: (($service->duration_hours ?: 1) * 60)));
                    $end = $start->addMinutes($minutes);

                    return Booking::create([
                        'user_id' => null,
                        'customer_name' => trim($data['customer_name']),
                        'customer_phone' => trim($data['customer_phone']),
                        'customer_email' => $data['customer_email'] ?? null,
                        'court_id' => $service->id,
                        'business_id' => $business->id,
                        'staff_id' => $staff->id,
                        'date' => $start,
                        'time_slot' => $start->format('g:i A').' to '.$end->format('g:i A'),
                        'duration_hours' => max(1, (int) ceil($minutes / 60)),
                        'duration_minutes' => $minutes,
                        'status' => 'pending',
                        'booking_code' => Str::upper(Str::random(8)),
                        'public_token' => (string) Str::uuid(),
                        'total_price' => $service->price_per_hour,
                    ]);
                });
            });
        } catch (LockTimeoutException) {
            $booking = null;
        }

        if (! $booking) {
            return back()->withInput()->withErrors(['time' => 'Ese horario acaba de ocuparse. Elige otro.']);
        }

        return redirect(URL::temporarySignedRoute('booking.confirmed', now()->addDays(30), [
            'slug' => $business->slug,
            'token' => $booking->public_token,
        ]));
    }

    public function confirmed(string $slug, string $token): View
    {
        $business = $this->business($slug);
        $booking = $business->bookings()
            ->where('public_token', $token)
            ->with(['court', 'staff'])
            ->firstOrFail();

        return view('booking.confirmed', compact('business', 'booking'));
    }

    private function business(string $slug): Business
    {
        return Business::resolveBySlug($slug) ?? abort(404);
    }

    private function selection(Business $business, int $serviceId, int $staffId): array
    {
        $service = $business->courts()->whereKey($serviceId)->where('status', 'active')->firstOrFail();
        $staff = $business->staff()
            ->whereKey($staffId)
            ->where('is_active', true)
            ->whereHas('courts', fn ($query) => $query->whereKey($service->id))
            ->firstOrFail();

        return [$service, $staff];
    }
}
