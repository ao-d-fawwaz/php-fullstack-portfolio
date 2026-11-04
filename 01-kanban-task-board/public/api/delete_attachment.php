<?php
// POST api/delete_attachment.php  (JSON: {"attachment_id": 1})
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    $userId = require_api_user();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }
    require_csrf();
    $id = (int) (json_input()['attachment_id'] ?? 0);
    $stmt = db()->prepare(
        'SELECT a.id, a.stored_name FROM task_attachments a JOIN tasks t ON t.id = a.task_id
         WHERE a.id = ? AND t.user_id = ?'
    );
    $stmt->execute([$id, $userId]);
    $att = $stmt->fetch();
    if (!$att) {
        json_error('Attachment not found', 404);
    }
    @unlink(__DIR__ . '/../uploads/' . basename($att['stored_name']));
    db()->prepare('DELETE FROM task_attachments WHERE id = ?')->execute([$att['id']]);
    json_response(['success' => true, 'message' => 'Attachment deleted']);
});
