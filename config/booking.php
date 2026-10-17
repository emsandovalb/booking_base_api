<?php

return [
    /*
    | Fallback IANA timezone for businesses that have not set their own.
    | Appointment times are stored as the business's local wall-clock time,
    | so "now" must be evaluated in this zone, not in the app's UTC clock.
    */
    'default_timezone' => env('BOOKING_DEFAULT_TIMEZONE', 'America/Costa_Rica'),
];
