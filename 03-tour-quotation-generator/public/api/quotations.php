<?php
// GET    api/quotations.php[?q=search]   list
// GET    api/quotations.php?id=1         detail (items + calculated summary)
// POST   api/quotations.php              create
// PUT    api/quotations.php?id=1         update
// DELETE api/quotations.php?id=1         delete
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    require_api_user();
    $repo = new QuotationRepository(db());
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    if ($method !== 'GET') {
        require_csrf();
    }

    if ($method === 'GET') {
        if ($id === null) {
            json_response(['success' => true, 'data' => $repo->list(trim((string) ($_GET['q'] ?? '')))]);
        }
        $q = $repo->find($id);
        $q ? json_response(['success' => true, 'data' => $q]) : json_error('Quotation not found', 404);
    }

    if ($method === 'POST' || $method === 'PUT') {
        if ($method === 'PUT' && ($id === null || $repo->find($id) === null)) {
            json_error('Quotation not found', 404);
        }
        [$tour, $items, $errors] = $repo->validate(json_input());
        if ($errors) {
            json_response(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
        }
        $saved = $repo->save($method === 'PUT' ? $id : null, $tour, $items);
        json_response(['success' => true, 'data' => $repo->find($saved)], $method === 'POST' ? 201 : 200);
    }

    if ($method === 'DELETE') {
        $id !== null && $repo->delete($id) ? json_response(['success' => true]) : json_error('Quotation not found', 404);
    }

    json_error('Method not allowed', 405);
});
