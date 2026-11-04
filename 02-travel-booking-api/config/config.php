<?php
// All settings can be overridden with environment variables.
return [
    'db_dsn'  => getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=travel_booking;charset=utf8mb4',
    'db_user' => getenv('DB_USER') !== false ? getenv('DB_USER') : 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    // Secret for signing JWTs. MUST be set to a long random value outside local development.
    'jwt_secret' => getenv('JWT_SECRET') ?: 'dev-only-secret-change-me-0123456789abcdef',
    'jwt_ttl'    => 60 * 60 * 8, // 8 hours
    // Comma-separated list of origins allowed to call the API from a browser
    'cors_origins' => array_filter(explode(',', getenv('CORS_ORIGINS') ?: 'http://localhost')),
    // Rate limit per IP and route
    'rate_limit_max'    => (int) (getenv('RATE_LIMIT_MAX') ?: 60),
    'rate_limit_window' => 60,
];
