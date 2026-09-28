<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\StaffRole;
use App\Services\BookingAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BusinessAppController extends Controller
{
    public function __construct(private readonly BookingAvailabilityService $availability) {}

    public function home(Request $request, string $slug): View
    {
        $business = $this->business($request, $slug);
        $today = $business->bookings()->whereDate('date', today())->with(['court', 'staff', 'user'])->orderBy('date')->get();
        $next = $business->bookings()->blocking()->where('date', '>=', now())->with(['court', 'staff', 'user'])->orderBy('date')->first();
        $pending = $business->bookings()->where('status', 'pending')->where('date', '>=', now())->count();
        $serviceCount = $business->courts()->where('status', 'active')->count();
        $staffCount = $business->staff()->where('is_active', true)->count();

        return view('business.home', compact('business', 'today', 'next', 'pending', 'serviceCount', 'staffCount'));
    }

    public function agenda(Request $request, string $slug): View
    {
        $business = $this->business($request, $slug);
        $date = $request->query('date', today()->toDateString());
        $selectedDate = CarbonImmutable::createFromFormat('Y-m-d', $date);
        $bookings = $business->bookings()->whereDate('date', $selectedDate)
            ->with(['court', 'staff', 'user'])->orderBy('date')->get();

        return view('business.agenda', compact('business', 'bookings', 'selectedDate'));
    }

    public function transition(Request $request, string $slug, int $bookingId, string $action): RedirectResponse
    {
        $business = $this->business($request, $slug);
        $item = $business->bookings()->whereKey($bookingId)->firstOrFail();
        $target = match ($action) {
            'confirm' => 'confirmed',
            'cancel' => 'cancelled',
            default => abort(404),
        };
        $allowed = $target === 'cancelled' ? ['pending', 'confirmed'] : ['pending'];
        abort_unless(in_array($item->status, $allowed, true), 422, 'Invalid booking transition');
        $item->update(['status' => $target]);

        return back()->with('status', $target === 'confirmed' ? 'Cita confirmada.' : 'Cita cancelada.');
    }

    public function booking(Request $request, string $slug, int $bookingId): View
    {
        $business = $this->business($request, $slug);
        $booking = $business->bookings()->whereKey($bookingId)->with(['court', 'staff', 'user'])->firstOrFail();

        return view('business.booking', compact('business', 'booking'));
    }

    public function reschedule(Request $request, string $slug, int $bookingId): RedirectResponse
    {
        $business = $this->business($request, $slug);
        $item = $business->bookings()->whereKey($bookingId)->with(['court', 'staff'])->firstOrFail();
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
        ]);
        abort_unless($item->status === 'pending' && $item->staff, 422, 'Solo se pueden reprogramar citas pendientes con profesional asignado');
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['time']}");
        $lock = Cache::lock("booking-lock:business:{$business->id}:staff:{$item->staff_id}", 10);
        try {
            $replacement = $lock->block(3, function () use ($business, $item, $start) {
                return DB::transaction(function () use ($business, $item, $start) {
                    if (! $this->availability->hasSlot($item->court, $item->staff, $start->toDateString(), $start->format('H:i'), $item->id)) {
                        return null;
                    }
                    $minutes = $this->availability->durationMinutes($item->court);
                    // This is atomic with the insert; a failed replacement rolls the old
                    // booking back to pending instead of losing the appointment.
                    $item->update(['status' => 'cancelled']);
                    $replacement = $business->bookings()->create([
                        'user_id' => $item->user_id,
                        'customer_name' => $item->customer_name,
                        'customer_phone' => $item->customer_phone,
                        'customer_email' => $item->customer_email,
                        'court_id' => $item->court_id,
                        'staff_id' => $item->staff_id,
                        'rebooked_from_booking_id' => $item->id,
                        'date' => $start,
                        'time_slot' => $this->availability->slotLabel($start, $item->court),
                        'duration_hours' => max(1, (int) ceil($minutes / 60)),
                        'duration_minutes' => $minutes,
                        'status' => 'pending',
                        'payment_status' => 'unpaid',
                        'booking_code' => Str::upper(Str::random(8)),
                        'total_price' => round((float) $item->court->price_per_hour, 2),
                    ]);
                    return $replacement;
                });
            });
        } catch (LockTimeoutException) {
            $replacement = null;
        }
        abort_unless($replacement, 422, 'Horario no disponible');

        return back()->with('status', 'Cita reprogramada y marcada como pendiente.');
    }

    public function services(Request $request, string $slug): View
    {
        $business = $this->business($request, $slug);
        $services = $business->courts()->withCount('staff')->orderBy('name')->get();

        return view('business.services', compact('business', 'services'));
    }

    public function updateService(Request $request, string $slug, int $service): RedirectResponse
    {
        $business = $this->business($request, $slug);
        $item = $business->courts()->whereKey($service)->firstOrFail();
        $item->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price_per_hour' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:480'],
            'open_hour' => ['required', 'date_format:H:i'],
            'close_hour' => ['required', 'date_format:H:i', 'after:open_hour'],
            'status' => ['required', 'in:active,inactive'],
        ]));

        return back()->with('status', 'Servicio actualizado.');
    }

    public function storeService(Request $request, string $slug): RedirectResponse
    {
        $business = $this->business($request, $slug);
        $business->courts()->create($this->serviceData($request) + [
            'owner_id' => $request->user()->id,
            'address' => data_get($business->contact_config, 'contact.address', 'Por definir'),
            'rating' => 0,
        ]);

        return back()->with('status', 'Servicio creado. Ahora asígnalo al personal.');
    }

    public function staff(Request $request, string $slug): View
    {
        $business = $this->business($request, $slug);
        $staff = $business->staff()->with(['role', 'courts'])->orderBy('name')->get();

        return view('business.staff', compact('business', 'staff'));
    }

    public function toggleStaff(Request $request, string $slug, int $staff): RedirectResponse
    {
        $business = $this->business($request, $slug);
        $person = $business->staff()->whereKey($staff)->firstOrFail();
        $person->update(['is_active' => ! $person->is_active]);

        return back()->with('status', 'Disponibilidad del profesional actualizada.');
    }

    public function storeStaff(Request $request, string $slug): RedirectResponse
    {
        $business = $this->business($request, $slug);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer'],
        ]);
        $serviceIds = $business->courts()->whereIn('id', $data['service_ids'])->pluck('id');
        abort_unless($serviceIds->count() === count(array_unique($data['service_ids'])), 422, 'Servicio inválido');
        $role = StaffRole::firstOrCreate(['slug' => 'barber'], ['name' => 'Barbero', 'description' => 'Profesional de barbería']);
        $person = $business->staff()->create([
            'staff_role_id' => $role->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
        ]);
        $person->courts()->sync($serviceIds->mapWithKeys(fn ($id) => [$id => ['is_primary' => false]])->all());

        return back()->with('status', 'Profesional agregado.');
    }

    public function share(Request $request, string $slug): View
    {
        $business = $this->business($request, $slug);
        $publicUrl = route('booking.public', $business->slug);

        return view('business.more', compact('business', 'publicUrl'));
    }

    private function business(Request $request, string $slug): Business
    {
        $business = Business::resolveBySlug($slug) ?? abort(404);
        abort_unless($request->user()?->canManageBusiness($business), 403);

        return $business;
    }

    private function serviceData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price_per_hour' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:480'],
            'open_hour' => ['required', 'date_format:H:i'],
            'close_hour' => ['required', 'date_format:H:i', 'after:open_hour'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
