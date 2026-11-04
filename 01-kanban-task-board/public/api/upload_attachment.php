<?php
// POST api/upload_attachment.php  (multipart: task_id=<uuid>, files[])
// Only whitelisted file types are accepted; the real MIME type is checked from the file content.
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

run_api(function (): void {
    global $config;
    $userId = require_api_user();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }
    require_csrf();
    $task = find_task((string) ($_POST['task_id'] ?? ''), $userId);
    if ($task === null) {
        json_error('Task not found', 404);
    }
    if (!isset($_FILES['files'])) {
        json_error('No files uploaded', 422);
    }

    $files = $_FILES['files'];
    if (!is_array($files['name'])) {
        $files = array_map(fn($v) => [$v], $files);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $dir = __DIR__ . '/../uploads/';
    $uploaded = [];
    $errors = [];

    foreach ($files['name'] as $i => $originalName) {
        $name = basename((string) $originalName);
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "$name: upload error ({$files['error'][$i]})";
            continue;
        }
        if ($files['size'][$i] > $config['upload_max_bytes']) {
            $errors[] = "$name: file too large (max 10 MB)";
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $finfo->file($files['tmp_name'][$i]);
        if (!isset($config['upload_allowed'][$ext]) || $config['upload_allowed'][$ext] !== $mime) {
            $errors[] = "$name: file type not allowed";
            continue;
        }
        // Random stored name: the original name never touches the file system
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($files['tmp_name'][$i], $dir . $stored)) {
            $errors[] = "$name: could not save file";
            continue;
        }
        db()->prepare(
            'INSERT INTO task_attachments (task_id, uploaded_by, file_name, stored_name, file_size, file_type, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$task['id'], $userId, $name, $stored, (int) $files['size'][$i], $mime, now()]);
        $uploaded[] = ['id' => (int) db()->lastInsertId(), 'name' => $name];
    }

    json_response([
        'success' => count($uploaded) > 0,
        'uploaded' => $uploaded,
        'errors' => $errors,
        'message' => count($uploaded) . ' file(s) uploaded, ' . count($errors) . ' error(s)',
    ], count($uploaded) > 0 ? 201 : 422);
});
