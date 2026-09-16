<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $businesses = Business::query()
            ->latest()
            ->limit(5)
            ->get();

        $recentBookings = Booking::query()
            ->with(['business', 'user'])
            ->latest()
            ->limit(5)
            ->get();

        return view('super-admin.dashboard', [
            'summary' => [
                'total_businesses' => Business::count(),
                'active_businesses' => Business::query()->where('status', 'active')->count(),
                'inactive_businesses' => Business::query()->whereNotIn('status', ['active', 'suspended'])->count(),
                'suspended_businesses' => Business::query()->where('status', 'suspended')->count(),
                'total_users' => User::count(),
                'total_bookings' => Booking::count(),
            ],
            'recentBusinesses' => $businesses,
            'recentBookings' => $recentBookings,
        ]);
    }
}
