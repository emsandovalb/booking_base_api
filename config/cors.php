<?php

// Production origins come exclusively from FRONTEND_URL / FRONTEND_URLS.
// Flutter Web development uses a different ephemeral port on each run, so
// repository-local QA can explicitly opt into tightly scoped loopback-host
// patterns with CORS_ALLOW_LOCALHOST=true. The opt-in defaults to false and
// must never be enabled in a deployed environment.
$allowedOrigins = array_values(array_unique(array_filter(array_merge(
    array_filter([trim((string) env('FRONTEND_URL', ''))]),
    array_filter(array_map(
        static fn (string $origin) => trim($origin),
        explode(',', (string) env('FRONTEND_URLS', ''))
    ))
))));

$allowLocalhost = filter_var(env('CORS_ALLOW_LOCALHOST', false), FILTER_VALIDATE_BOOL);
$allowedOriginPatterns = $allowLocalhost ? [
    '#^https?://localhost(?::[0-9]{1,5})?$#',
    '#^https?://127\.0\.0\.1(?::[0-9]{1,5})?$#',
] : [];

return [
    'paths' => ['api/*', 'storage/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => $allowedOriginPatterns,
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
