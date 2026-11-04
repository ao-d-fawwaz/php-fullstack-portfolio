<?php
// POST api/send_message.php  (form fields: task_id=<uuid>, message)
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    $userId = require_api_user();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }
    require_csrf();
    $task = find_task((string) ($_POST['task_id'] ?? ''), $userId);
    if ($task === null) {
        json_error('Task not found', 404);
    }
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($message === '' || mb_strlen($message) > 2000) {
        json_error('Message must be 1-2000 characters', 422);
    }
    db()->prepare('INSERT INTO task_messages (task_id, user_id, message, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$task['id'], $userId, $message, now()]);
    json_response(['success' => true, 'message' => 'Message sent', 'message_id' => (int) db()->lastInsertId()], 201);
});
