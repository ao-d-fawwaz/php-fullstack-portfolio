<?php
// GET api/get_attachments.php?task_id=<uuid>[&recent=5]
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    $userId = require_api_user();
    $task = find_task((string) ($_GET['task_id'] ?? ''), $userId);
    if ($task === null) {
        json_error('Task not found', 404);
    }
    $limit = max(0, min(100, (int) ($_GET['recent'] ?? 0)));
    $sql = 'SELECT a.id, a.file_name, a.file_size, a.file_type, a.uploaded_at, u.username AS uploaded_by_name
            FROM task_attachments a JOIN users u ON u.id = a.uploaded_by
            WHERE a.task_id = ? ORDER BY a.uploaded_at DESC, a.id DESC' . ($limit > 0 ? ' LIMIT ' . $limit : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([$task['id']]);
    $rows = array_map(function (array $a): array {
        // Files are served through download.php, which re-checks ownership
        $a['file_url'] = 'api/download.php?id=' . $a['id'];
        return $a;
    }, $stmt->fetchAll());
    json_response(['success' => true, 'attachments' => $rows, 'count' => count($rows)]);
});
