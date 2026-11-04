<?php
// Tasks CRUD for the logged-in user.
// GET    api/task.php                 -> list
// POST   api/task.php                 -> create   (JSON body)
// PUT    api/task.php?task_uuid=...   -> update   (JSON body)
// DELETE api/task.php?task_uuid=...   -> delete
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

const PRIORITIES = ['low', 'medium', 'high'];

function column_id(string $name): ?int
{
    $stmt = db()->prepare('SELECT id FROM columns WHERE name = ?');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function add_history(int $taskId, int $userId, string $action, ?string $old, ?string $new): void
{
    db()->prepare('INSERT INTO task_history (task_id, user_id, action, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$taskId, $userId, $action, $old, $new, now()]);
}

function validated_fields(array $data, array $current = []): array
{
    $title = trim((string) ($data['title'] ?? $current['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 255) {
        json_error('Title is required (max 255 characters)', 422);
    }
    $columnName = (string) ($data['column'] ?? $current['column_name'] ?? '');
    $columnId = column_id($columnName);
    if ($columnId === null) {
        json_error('Unknown column', 422);
    }
    $priority = (string) ($data['priority'] ?? $current['priority'] ?? 'medium');
    if (!in_array($priority, PRIORITIES, true)) {
        json_error('Priority must be low, medium or high', 422);
    }
    $due = $data['due_date'] ?? $current['due_date'] ?? null;
    if ($due !== null && $due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $due)) {
        json_error('due_date must be YYYY-MM-DD', 422);
    }
    return [
        'title' => $title,
        'description' => (string) ($data['description'] ?? $current['description'] ?? ''),
        'column_id' => $columnId,
        'completed' => !empty($data['completed'] ?? $current['completed'] ?? false) ? 1 : 0,
        'priority' => $priority,
        'due_date' => ($due === '' ? null : $due),
    ];
}

run_api(function (): void {
    $userId = require_api_user();
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'GET') {
        require_csrf();
    }

    if ($method === 'GET') {
        $stmt = db()->prepare(
            'SELECT t.task_uuid, t.title, t.description, t.completed, t.priority, t.due_date, t.created_at,
                    c.name AS column_name, c.display_name AS column_display
             FROM tasks t JOIN columns c ON c.id = t.column_id
             WHERE t.user_id = ? ORDER BY t.created_at DESC, t.id DESC'
        );
        $stmt->execute([$userId]);
        json_response(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($method === 'POST') {
        $data = json_input();
        $f = validated_fields($data);
        $uuid = (string) ($data['task_uuid'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $uuid)) {
            $uuid = 'task_' . bin2hex(random_bytes(12));
        }
        db()->prepare(
            'INSERT INTO tasks (task_uuid, user_id, column_id, title, description, completed, priority, due_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$uuid, $userId, $f['column_id'], $f['title'], $f['description'], $f['completed'], $f['priority'], $f['due_date'], now()]);
        $taskId = (int) db()->lastInsertId();
        add_history($taskId, $userId, 'created', null, $f['title']);
        json_response(['success' => true, 'message' => 'Task created', 'task_uuid' => $uuid], 201);
    }

    $uuid = (string) ($_GET['task_uuid'] ?? '');
    $task = find_task($uuid, $userId);
    if ($task === null) {
        json_error('Task not found', 404);
    }

    if ($method === 'PUT') {
        $stmt = db()->prepare('SELECT name FROM columns WHERE id = ?');
        $stmt->execute([$task['column_id']]);
        $task['column_name'] = $stmt->fetchColumn();
        $f = validated_fields(json_input(), $task);
        db()->prepare(
            'UPDATE tasks SET title = ?, description = ?, column_id = ?, completed = ?, priority = ?, due_date = ?, updated_at = ?
             WHERE id = ?'
        )->execute([$f['title'], $f['description'], $f['column_id'], $f['completed'], $f['priority'], $f['due_date'], now(), $task['id']]);
        if ((int) $task['column_id'] !== $f['column_id']) {
            add_history((int) $task['id'], $userId, 'moved', (string) $task['column_name'], (string) (json_input()['column'] ?? ''));
        }
        if ($task['title'] !== $f['title']) {
            add_history((int) $task['id'], $userId, 'renamed', $task['title'], $f['title']);
        }
        json_response(['success' => true, 'message' => 'Task updated']);
    }

    if ($method === 'DELETE') {
        // Remove attachment files from disk before the rows are deleted by the cascade
        $stmt = db()->prepare('SELECT stored_name FROM task_attachments WHERE task_id = ?');
        $stmt->execute([$task['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $stored) {
            @unlink(__DIR__ . '/../uploads/' . basename($stored));
        }
        db()->prepare('DELETE FROM tasks WHERE id = ?')->execute([$task['id']]);
        json_response(['success' => true, 'message' => 'Task deleted']);
    }

    json_error('Method not allowed', 405);
});
