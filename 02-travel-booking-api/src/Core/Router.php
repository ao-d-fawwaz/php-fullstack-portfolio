<?php
declare(strict_types=1);

namespace App\Core;

/** Tiny router: register "METHOD /path/{param}" => handler, then dispatch. */
final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'middleware', 'pattern');
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $context = ['route' => $route['pattern']];
            foreach ($route['middleware'] as $mw) {
                $context = $mw($context) ?? $context;
            }
            ($route['handler'])($params, $context);
            return;
        }
        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new HttpException(405, 'Method not allowed');
        }
        throw new HttpException(404, 'Endpoint not found');
    }
}
