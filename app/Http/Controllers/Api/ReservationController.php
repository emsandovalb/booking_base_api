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

    public function confirm(Request $request, Booking $reservation)
    {
        return $this->bookings->confirm($request, $reservation);
    }

    public function reject(Request $request, Booking $reservation)
    {
        return $this->bookings->reject($request, $reservation);
    }

    public function complete(Request $request, Booking $reservation)
    {
        return $this->bookings->complete($request, $reservation);
    }

    public function cancel(Request $request, Booking $reservation)
    {
        return $this->bookings->cancel($request, $reservation);
    }

    public function rebook(Request $request, Booking $reservation)
    {
        return $this->bookings->rebook($request, $reservation);
    }

    public function markPaid(Request $request, Booking $reservation)
    {
        return $this->bookings->markPaid($request, $reservation);
    }
}
