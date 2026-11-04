<?php
/**
 * Front controller: every /api/... request is routed here by .htaccess (or by PHP's built-in server).
 */
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\LedgerController;
use App\Controllers\ProductController;
use App\Core\Database;
use App\Core\Http;
use App\Core\HttpException;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Models\Booking;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\User;

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        require __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

$config = require __DIR__ . '/../config/config.php';
ini_set('display_errors', '0');

// Path relative to this file's folder, so the API works in any sub-directory (e.g. XAMPP htdocs)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim($path, '/');

// Built-in dev server: let it serve the demo page and other static files directly
if (PHP_SAPI === 'cli-server' && !str_starts_with($path, '/api')) {
    if ($path === '/') {
        readfile(__DIR__ . '/index.html');
        return true;
    }
    return false;
}

// CORS: only listed origins
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $config['cors_origins'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $db = Database::connection($config);
    $auth = new AuthMiddleware($config['jwt_secret']);
    $limit = (new RateLimitMiddleware($db, $config['rate_limit_max'], $config['rate_limit_window']))->handle();

    $authController = new AuthController(new User($db), $config);
    $productController = new ProductController(new Product($db));
    $bookingController = new BookingController($db, new Booking($db), new Product($db), new Ledger($db));
    $ledgerController = new LedgerController(new Ledger($db));

    $r = new Router();
    $r->add('POST', '/api/auth/login', fn() => $authController->login(), [$limit]);
    $r->add('POST', '/api/auth/register', fn() => $authController->register(), [$limit, $auth->require('admin')]);
    $r->add('GET', '/api/products', fn() => $productController->index(), [$limit, $auth->require()]);
    $r->add('GET', '/api/products/{code}', fn($p) => $productController->show($p), [$limit, $auth->require()]);
    $r->add('GET', '/api/bookings', fn() => $bookingController->index(), [$limit, $auth->require()]);
    $r->add('POST', '/api/bookings', fn($p, $c) => $bookingController->store($p, $c), [$limit, $auth->require()]);
    $r->add('GET', '/api/bookings/{id}', fn($p) => $bookingController->show($p), [$limit, $auth->require()]);
    $r->add('PATCH', '/api/bookings/{id}/status', fn($p) => $bookingController->updateStatus($p), [$limit, $auth->require()]);
    $r->add('GET', '/api/ledger', fn() => $ledgerController->index(), [$limit, $auth->require('admin')]);
    $r->add('GET', '/api/ledger/summary', fn() => $ledgerController->summary(), [$limit, $auth->require('admin')]);

    $r->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (HttpException $e) {
    $body = ['status' => 'error', 'message' => $e->getMessage()];
    if ($e->errors) {
        $body['errors'] = $e->errors;
    }
    Http::json($body, $e->status);
} catch (Throwable $e) {
    error_log('[travel-api] ' . $e->getMessage());
    Http::json(['status' => 'error', 'message' => 'Server error'], 500);
}
