<?php

// Production origins come exclusively from FRONTEND_URL / FRONTEND_URLS.
// No hardcoded dev/LAN fallback: if neither is set, the allow-list is
// empty and every cross-origin browser request is rejected — that's the
// safe failure mode, not silently trusting a developer's machine.
//
// For local development, set FRONTEND_URL (and/or FRONTEND_URLS for
// multiple origins) in your own .env — never here.
$allowedOrigins = array_values(array_unique(array_filter(array_merge(
    array_filter([trim((string) env('FRONTEND_URL', ''))]),
    array_filter(array_map(
        static fn (string $origin) => trim($origin),
        explode(',', (string) env('FRONTEND_URLS', ''))
    ))
))));

return [
    'paths' => ['api/*', 'storage/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
