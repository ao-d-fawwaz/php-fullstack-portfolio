<?php
// GET api/download.php?id=<attachment id> -> streams the file if the task belongs to the user
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    $userId = require_api_user();
    $stmt = db()->prepare(
        'SELECT a.file_name, a.stored_name, a.file_type FROM task_attachments a JOIN tasks t ON t.id = a.task_id
         WHERE a.id = ? AND t.user_id = ?'
    );
    $stmt->execute([(int) ($_GET['id'] ?? 0), $userId]);
    $att = $stmt->fetch();
    $path = $att ? __DIR__ . '/../uploads/' . basename($att['stored_name']) : '';
    if (!$att || !is_file($path)) {
        json_error('File not found', 404);
    }
    header('Content-Type: ' . $att['file_type']);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    $disposition = str_starts_with($att['file_type'], 'image/') || $att['file_type'] === 'application/pdf' ? 'inline' : 'attachment';
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($att['file_name']) . '"');
    readfile($path);
    exit;
});
