<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Device / satellite API keys
    |--------------------------------------------------------------------------
    |
    | Comma-separated shared secrets for biometric clocks and offline DTR
    | sync clients. Send via header:
    |   X-API-Key: <key>
    | or
    |   Authorization: Bearer <key>
    |
    | Prefer rotating keys per environment; never commit real keys.
    |
    */

    'device_keys' => array_values(array_filter(array_map(
        static fn (string $key): string => trim($key),
        explode(',', (string) env('API_DEVICE_KEYS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Lock down /api/*
    |--------------------------------------------------------------------------
    |
    | When true, non-public API routes require either a valid device key or an
    | authenticated user (web session / Sanctum token). Auth login stays open.
    |
    */

    'require_auth' => (bool) env('API_REQUIRE_AUTH', true),

    /*
    |--------------------------------------------------------------------------
    | Public API path prefixes (no auth when lockdown is on)
    |--------------------------------------------------------------------------
    */

    'public_prefixes' => [
        'auth/login',
    ],

];
