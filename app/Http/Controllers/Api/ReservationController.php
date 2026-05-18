<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    private BookingController $bookings;

    public function __construct(BookingController $bookings)
    {
        $this->bookings = $bookings;
    }

    public function index(Request $request)
    {
        // Temporary compatibility alias: reservations are backed by the existing bookings table.
        return $this->bookings->index($request);
    }

    public function store(Request $request)
    {
        return $this->bookings->store($request);
    }

    public function show(Request $request, Booking $reservation)
    {
        return $this->bookings->show($request, $reservation);
    }

    public function cancel(Request $request, Booking $reservation)
    {
        return $this->bookings->cancel($request, $reservation);
    }

    public function rebook(Request $request, Booking $reservation)
    {
        return $this->bookings->rebook($request, $reservation);
    }
}
