<?php
// POST api/calculate.php -> live preview of the calculation without saving
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    require_api_user();
    require_csrf();
    $repo = new QuotationRepository(db());
    [$tour, $items, $errors] = $repo->validate(json_input());
    if ($errors) {
        json_response(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
    }
    json_response(['success' => true, 'data' => QuotationCalculator::calculate($tour, $items)]);
});
