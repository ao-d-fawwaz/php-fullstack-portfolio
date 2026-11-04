<?php
return [
    'db_dsn'  => getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=karate_tournament;charset=utf8mb4',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'tournament_name' => getenv('TOURNAMENT_NAME') ?: 'Kyokushin Open Championship (demo)',
    // Age groups are calculated on this date
    'tournament_date' => getenv('TOURNAMENT_DATE') ?: '2026-12-12',
    'belts' => ['White', 'Orange', 'Blue', 'Yellow', 'Green', 'Brown', 'Black'],
];
