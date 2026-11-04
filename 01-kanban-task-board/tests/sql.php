<?php
// Tiny SQLite helper for the test script (no sqlite3 CLI needed).
//   php tests/sql.php <db-file> --schema <file.sql>   load a schema
//   php tests/sql.php <db-file> "<SELECT ...>"        print first column of first row
$pdo = new PDO('sqlite:' . $argv[1], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (($argv[2] ?? '') === '--schema') {
    $pdo->exec(file_get_contents($argv[3]));
    exit;
}
echo $pdo->query($argv[2])->fetchColumn(), "\n";
