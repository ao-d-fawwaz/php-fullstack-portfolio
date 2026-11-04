<?php
// GET api/get_messages.php?task_id=<uuid> -> chat messages of a task
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    $userId = require_api_user();
    $task = find_task((string) ($_GET['task_id'] ?? ''), $userId);
    if ($task === null) {
        json_error('Task not found', 404);
    }
    $stmt = db()->prepare(
        'SELECT m.id, m.user_id, m.message, m.created_at, u.username AS user_name
         FROM task_messages m JOIN users u ON u.id = m.user_id
         WHERE m.task_id = ? ORDER BY m.created_at ASC, m.id ASC'
    );
    $stmt->execute([$task['id']]);
    $messages = array_map(function (array $m) use ($userId): array {
        $m['is_current_user'] = ((int) $m['user_id'] === $userId);
        return $m;
    }, $stmt->fetchAll());
    json_response(['success' => true, 'messages' => $messages]);
});
