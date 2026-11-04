<?php
// Defaults match a local XAMPP install; override with environment variables elsewhere.
return [
    'db_dsn'  => getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=tour_quotation;charset=utf8mb4',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'company_name' => getenv('COMPANY_NAME') ?: 'Nusantara Journeys (demo)',
];
