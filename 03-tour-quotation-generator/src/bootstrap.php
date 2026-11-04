<?php
/**
 * Shared bootstrap: config, PDO connection, JSON helpers, session auth.
 */
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

ini_set('display_errors', '0');
session_name('quotation_session');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    global $config;
    if ($pdo === null) {
        $pdo = new PDO($config['db_dsn'], $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }
    return $pdo;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['success' => false, 'message' => $message], $status);
}

function json_input(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/** API guard: every endpoint requires a logged-in user. */
function require_api_user(): int
{
    $id = current_user_id();
    if ($id === null) {
        json_error('Not authenticated', 401);
    }
    return $id;
}

/** CSRF check for state-changing API calls (token sent in X-CSRF-Token header or form field). */
function require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        json_error('Invalid CSRF token', 403);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Run an API handler and turn unexpected errors into a generic 500 (details go to the log only). */
function run_api(callable $handler): void
{
    try {
        $handler();
    } catch (Throwable $e) {
        error_log('[quotation] ' . $e->getMessage());
        json_error('Server error', 500);
    }
}

require_once __DIR__ . '/QuotationCalculator.php';
require_once __DIR__ . '/QuotationRepository.php';
