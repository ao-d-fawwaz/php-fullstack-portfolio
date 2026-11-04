<?php
// GET    api/participants.php          public list (name, dojo, belt, category only)
// POST   api/participants.php          register (organiser login required)
// DELETE api/participants.php?id=1     remove (organiser, only if not in a bracket yet)
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    global $config;
    $repo = new TournamentRepository(db(), $config);
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        json_response(['success' => true, 'data' => $repo->participants(current_user_id() === null)]);
    }
    require_api_user();
    require_csrf();
    if ($method === 'POST') {
        [$data, $errors] = $repo->validateParticipant(json_input());
        if ($errors) {
            json_response(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
        }
        $id = $repo->addParticipant($data);
        json_response(['success' => true, 'id' => $id, 'category' => BracketEngine::categoryLabel(BracketEngine::categoryKey($data, $config['tournament_date']))], 201);
    }
    if ($method === 'DELETE') {
        $result = $repo->deleteParticipant((int) ($_GET['id'] ?? 0));
        match ($result) {
            'deleted' => json_response(['success' => true]),
            'in_bracket' => json_error('Participant is already in a bracket', 409),
            default => json_error('Participant not found', 404),
        };
    }
    json_error('Method not allowed', 405);
});
