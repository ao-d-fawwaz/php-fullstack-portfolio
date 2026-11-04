<?php
// Database settings. Defaults match a local XAMPP install (MySQL user "root", no password).
// Override with environment variables in other environments.
return [
    'db_dsn'  => getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=kanban_board;charset=utf8mb4',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    // Uploads
    'upload_max_bytes' => 10 * 1024 * 1024,
    'upload_allowed'   => [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
    ],
];
