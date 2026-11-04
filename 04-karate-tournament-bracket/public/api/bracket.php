<?php
// GET  api/bracket.php                        categories with participant counts (public)
// GET  api/bracket.php?category=<key>         bracket of a category (public)
// POST api/bracket.php  {"action":"generate","category":"<key>"[,"force":true]}   organiser
// POST api/bracket.php  {"action":"winner","match_id":1,"winner_id":2}           organiser
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    global $config;
    $repo = new TournamentRepository(db(), $config);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!isset($_GET['category'])) {
            json_response(['success' => true, 'data' => $repo->categories()]);
        }
        $bracket = $repo->bracket((string) $_GET['category']);
        $bracket ? json_response(['success' => true, 'data' => $bracket]) : json_error('No bracket for this category yet', 404);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }
    require_api_user();
    require_csrf();
    $in = json_input();

    if (($in['action'] ?? '') === 'generate') {
        $key = (string) ($in['category'] ?? '');
        if (!in_array($key, array_column($repo->categories(), 'key'), true)) {
            json_error('Unknown category', 404);
        }
        if ($repo->hasResults($key) && empty($in['force'])) {
            json_error('This bracket already has results. Send "force": true to rebuild it.', 409);
        }
        try {
            $repo->generate($key);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage(), 422);
        }
        json_response(['success' => true, 'data' => $repo->bracket($key)], 201);
    }

    if (($in['action'] ?? '') === 'winner') {
        $result = $repo->setWinner((int) ($in['match_id'] ?? 0), (int) ($in['winner_id'] ?? 0));
        match ($result) {
            'ok' => json_response(['success' => true]),
            'not_found' => json_error('Match not found', 404),
            'not_ready' => json_error('Both fighters must be known first', 409),
            'not_in_match' => json_error('Winner is not in this match', 422),
            'locked' => json_error('The next round is already decided; change that result first', 409),
        };
    }
    json_error('Unknown action', 400);
});
