<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
if (current_user_id() === null) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kanban Board</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(145deg, #e7eef5 0%, #dbe4ed 100%);
            min-height: 100vh;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .board-container {
            max-width: 1400px;
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem;
            padding: 1rem;
        }

        .column {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(0,0,0,0.05);
            width: 360px;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
            border: 1px solid rgba(255,255,255,0.6);
            height: 700px;
        }

        .column:hover {
            box-shadow: 0 30px 60px -12px rgba(0,20,40,0.35), 0 0 0 1px rgba(0,0,0,0.1);
        }

        .column-header {
            padding: 1.5rem 1.5rem 1rem 1.5rem;
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: -0.02em;
            color: #1e293b;
            background: transparent;
            border-bottom: 2px solid rgba(0,0,0,0.08);
            border-radius: 32px 32px 0 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .column-header::before {
            content: '●';
            font-size: 1.6rem;
            color: #3b82f6;
            opacity: 0.8;
        }

        .column[data-column="sales"] .column-header::before { color: #f97316; }
        .column[data-column="finance"] .column-header::before { color: #10b981; }
        .column[data-column="operation"] .column-header::before { color: #8b5cf6; }

        .task-list {
            padding: 1rem 1.2rem 1.5rem 1.2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            overflow-y: auto;
            min-height: 0;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

        .task-list::-webkit-scrollbar {
            width: 6px;
        }

        .task-list::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .task-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .task-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .task {
            background: white;
            padding: 1rem 1rem 1rem 1.2rem;
            border-radius: 20px;
            box-shadow: 0 4px 10px -4px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            cursor: grab;
            transition: all 0.15s ease;
            border: 1px solid rgba(0,0,0,0.04);
            font-size: 1rem;
            font-weight: 500;
            color: #1e293b;
            backdrop-filter: blur(2px);
            user-select: none;
        }

        .task:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -10px rgba(0,0,0,0.2), 0 2px 4px rgba(0,0,0,0.1);
            border-color: rgba(59,130,246,0.3);
        }

        .task:active {
            cursor: grabbing;
            transform: scale(0.99);
        }

        .task-check {
            width: 24px;
            height: 24px;
            border-radius: 30px;
            border: 2px solid #cbd5e1;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .task-check.completed {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }

        .task-check.completed::after {
            content: '✓';
            font-size: 1rem;
            font-weight: bold;
        }

        .task-content {
            flex: 1;
            word-break: break-word;
            cursor: pointer;
        }

        .task-content:hover {
            color: #3b82f6;
            text-decoration: underline;
        }

        .task-actions {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            flex-shrink: 0;
        }

        .detail-link {
            background: #f1f5f9;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            color: #475569;
            border: 1px solid rgba(0,0,0,0.04);
            text-decoration: none;
        }

        .detail-link:hover {
            background: #e2e8f0;
            transform: scale(1.1);
            color: #0f172a;
        }

        .delete-btn {
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 4px 10px -4px rgba(239,68,68,0.4);
            backdrop-filter: blur(2px);
            line-height: 1;
        }

        .delete-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
            box-shadow: 0 6px 14px -4px #dc2626;
        }

        .add-task {
            padding: 1.2rem 1.5rem 1.5rem 1.5rem;
            border-top: 2px dashed rgba(0,0,0,0.1);
            display: flex;
            gap: 0.6rem;
            background: transparent;
            flex-shrink: 0;
        }

        .add-task input {
            flex: 1;
            padding: 0.85rem 1.2rem;
            border: none;
            border-radius: 60px;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(4px);
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.02), 0 0 0 1px rgba(0,0,0,0.04);
            font-size: 0.95rem;
            font-weight: 500;
            color: #0f172a;
            transition: all 0.2s;
        }

        .add-task input:focus {
            outline: none;
            background: white;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.02), 0 0 0 3px rgba(59,130,246,0.3);
        }

        .add-task input::placeholder {
            color: #64748b;
            font-weight: 400;
            opacity: 0.6;
        }

        .add-task button {
            background: #0f172a;
            border: none;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
            padding: 0 1.4rem;
            border-radius: 60px;
            cursor: pointer;
            box-shadow: 0 8px 18px -8px #0f172a80;
            letter-spacing: 0.3px;
            transition: all 0.15s ease;
            backdrop-filter: blur(2px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .add-task button:hover {
            background: #1e293b;
            transform: scale(1.04);
            box-shadow: 0 12px 22px -10px #0f172a;
        }

        .task-count {
            background: rgba(0,0,0,0.08);
            padding: 0.2rem 0.8rem;
            border-radius: 60px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .task-list:empty::after {
            content: '✨ no tasks — drag or add';
            display: block;
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
            padding: 1.8rem 0.5rem;
            opacity: 0.45;
            font-style: italic;
            letter-spacing: 0.2px;
            border: 2px dashed rgba(0,0,0,0.06);
            border-radius: 24px;
            background: rgba(255,255,255,0.2);
        }

        .task.dragging {
            opacity: 0.5;
            transform: rotate(1deg) scale(0.98);
        }

        .task-list.drag-over {
            background: rgba(59,130,246,0.08);
            border-radius: 28px;
            transition: background 0.1s;
        }

        /* Styles untuk halaman detail */
        .detail-page {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 32px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .detail-title {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 1.5rem;
            background: #f1f5f9;
            border-radius: 60px;
            color: #475569;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.15s;
        }

        .back-link:hover {
            background: #e2e8f0;
            color: #0f172a;
            transform: translateX(-2px);
        }

        .detail-meta {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 24px;
            margin-bottom: 2rem;
        }

        .detail-meta-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 0;
        }

        .detail-meta-label {
            font-weight: 600;
            color: #64748b;
            min-width: 100px;
        }

        .detail-meta-value {
            color: #0f172a;
            font-weight: 500;
        }

        .detail-description {
            margin-bottom: 2rem;
        }

        .detail-description h3 {
            font-size: 1.2rem;
            color: #0f172a;
            margin-bottom: 1rem;
        }

        .detail-description textarea {
            width: 100%;
            padding: 1.2rem;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            font-size: 1rem;
            resize: vertical;
            min-height: 200px;
            transition: border-color 0.15s;
        }

        .detail-description textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .detail-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 60px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #0f172a;
            color: white;
        }

        .btn-primary:hover {
            background: #1e293b;
            transform: scale(1.02);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            border-radius: 60px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-badge.completed {
            background: #10b98120;
            color: #10b981;
        }

        .status-badge.pending {
            background: #f9731620;
            color: #f97316;
        }

        .column-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 1rem;
            border-radius: 60px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .column-badge.sales {
            background: #f9731620;
            color: #f97316;
        }

        .column-badge.finance {
            background: #10b98120;
            color: #10b981;
        }

        .column-badge.operation {
            background: #8b5cf620;
            color: #8b5cf6;
        }
        /* Chat Container */
.chat-container {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin: 1.5rem 0;
    overflow: hidden;
}

.chat-messages {
    height: 300px;
    overflow-y: auto;
    padding: 1.5rem;
    background: #f8fafc;
}

.chat-message {
    margin-bottom: 1rem;
    padding: 1rem;
    border-radius: 12px;
    max-width: 80%;
}

.chat-message.own-message {
    background: #4299e1;
    color: white;
    margin-left: auto;
}

.chat-message.other-message {
    background: white;
    border: 1px solid #e2e8f0;
    margin-right: auto;
}

.message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.8rem;
    opacity: 0.8;
}

.message-content {
    line-height: 1.5;
}

.chat-input-area {
    padding: 1rem;
    background: white;
    border-top: 1px solid #e2e8f0;
}

.chat-input-area textarea {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    resize: none;
    font-family: inherit;
    margin-bottom: 0.5rem;
}

.chat-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.btn-attachment, .btn-send {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
}

.btn-attachment {
    background: #718096;
    color: white;
}

.btn-attachment:hover {
    background: #4a5568;
}

.btn-send {
    background: #4299e1;
    color: white;
}

.btn-send:hover {
    background: #3182ce;
}

/* Attachments Preview */
.attachments-preview {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.attachments-preview h4 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    color: #2d3748;
}

.see-all-link {
    color: #4299e1;
    cursor: pointer;
    font-size: 0.9rem;
}

.see-all-link:hover {
    text-decoration: underline;
}

.attachment-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 8px;
}

.attachment-item i {
    font-size: 1.2rem;
    color: #718096;
}

.attachment-info {
    flex: 1;
}

.attachment-name {
    color: #2d3748;
    text-decoration: none;
    font-weight: 500;
}

.attachment-name:hover {
    text-decoration: underline;
}

.attachment-meta {
    font-size: 0.8rem;
    color: #718096;
    margin-left: 0.5rem;
}

/* Attachment Page */
.attachment-page {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: white;
    z-index: 2000;
    overflow-y: auto;
}

.attachment-header {
    background: #2d3748;
    color: white;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    cursor: pointer;
}

.attachment-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.upload-section {
    background: #f8fafc;
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.file-drop-area {
    border: 2px dashed #cbd5e0;
    border-radius: 12px;
    padding: 3rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.file-drop-area:hover,
.file-drop-area.dragover {
    border-color: #4299e1;
    background: #ebf8ff;
}

.file-drop-area i {
    font-size: 3rem;
    color: #4299e1;
    margin-bottom: 1rem;
}

.selected-files {
    margin: 1rem 0;
}

.selected-file-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.btn-upload {
    background: #48bb78;
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 12px;
    cursor: pointer;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
}

.btn-upload:hover {
    background: #38a169;
}

.attachments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.attachment-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s;
}

.attachment-card:hover {
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.attachment-icon i {
    font-size: 2rem;
    color: #4299e1;
}

.attachment-details {
    flex: 1;
}

.attachment-title {
    font-weight: 500;
    color: #2d3748;
    text-decoration: none;
    display: block;
    margin-bottom: 0.25rem;
}

.attachment-title:hover {
    text-decoration: underline;
}

.attachment-metadata {
    font-size: 0.8rem;
    color: #718096;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-delete {
    background: none;
    border: none;
    color: #e53e3e;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.3s;
}

.btn-delete:hover {
    background: #fed7d7;
}

.loading-messages,
.no-messages,
.loading-attachments,
.no-attachments,
.loading {
    text-align: center;
    padding: 2rem;
    color: #718096;
    font-style: italic;
}

/* Tambahkan di bagian style */
.debug-panel {
    background: #1e1e2e;
    color: #fff;
    padding: 15px;
    margin: 20px 0;
    border-radius: 8px;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 12px;
    max-height: 400px;
    overflow: auto;
}

.debug-panel pre {
    color: #a6e22e;
    margin: 5px 0;
}

.file-missing {
    color: #f43f5e;
    font-size: 12px;
    font-weight: bold;
}

.upload-progress {
    width: 100%;
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    margin: 10px 0;
    overflow: hidden;
}

.upload-progress-bar {
    height: 100%;
    background: #48bb78;
    transition: width 0.3s;
}

.error-detail {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 10px;
    border-radius: 6px;
    margin: 10px 0;
    font-size: 14px;
}
.user-bar {
    position: fixed;
    top: 12px;
    right: 20px;
    z-index: 10;
    background: rgba(255,255,255,.85);
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 13px;
    color: #4a5568;
}
.user-bar a { color: #667eea; }
    </style>
</head>
<body>

<!-- Halaman Utama Board -->
<div class="user-bar">Signed in as <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong> · <a href="logout.php">Log out</a></div>
<div id="board-page">
    <div class="board-container">
        <!-- Sales column -->
        <div class="column" data-column="sales">
            <div class="column-header">
                Sales
                <span class="task-count" id="count-sales">0</span>
            </div>
            <div class="task-list" id="sales"></div>
            <div class="add-task">
                <input type="text" id="input-sales" placeholder="e.g., Call client...">
                <button onclick="addTask('sales')">Add</button>
            </div>
        </div>

        <!-- Finance column -->
        <div class="column" data-column="finance">
            <div class="column-header">
                Finance
                <span class="task-count" id="count-finance">0</span>
            </div>
            <div class="task-list" id="finance"></div>
            <div class="add-task">
                <input type="text" id="input-finance" placeholder="e.g., Invoice...">
                <button onclick="addTask('finance')">Add</button>
            </div>
        </div>

        <!-- Operation column -->
        <div class="column" data-column="operation">
            <div class="column-header">
                Operation
                <span class="task-count" id="count-operation">0</span>
            </div>
            <div class="task-list" id="operation"></div>
            <div class="add-task">
                <input type="text" id="input-operation" placeholder="e.g., Deploy...">
                <button onclick="addTask('operation')">Add</button>
            </div>
        </div>
    </div>
</div>

<script>
    let draggedTask = null;
    let tasks = {}; // Store all tasks data

    // API Configuration
    const API_BASE_URL = 'api';
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

    // Send the CSRF token and session cookie with every API call
    const nativeFetch = window.fetch.bind(window);
    window.fetch = (url, options = {}) => {
        const headers = new Headers(options.headers || {});
        headers.set('X-CSRF-Token', CSRF_TOKEN);
        return nativeFetch(url, { ...options, headers, credentials: 'same-origin' }).then(response => {
            if (response.status === 401) {
                window.location.href = 'login.php';
            }
            return response;
        });
    };

    function generateTaskId() {
        return 'task_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    function updateTaskCount(columnId) {
        const count = document.getElementById(columnId).children.length;
        document.getElementById(`count-${columnId}`).textContent = count;
    }

    // Load tasks from server
    async function loadTasks() {
        try {
            const response = await fetch(`${API_BASE_URL}/task.php`);
            const result = await response.json();

            if (result.success) {
                // Convert server tasks format to local format
                tasks = {};
                result.data.forEach(task => {
                    tasks[task.task_uuid] = {
                        id: task.task_uuid,
                        title: task.title,
                        description: task.description || '',
                        column: task.column_name,
                        completed: task.completed == 1,
                        createdAt: task.created_at,
                        priority: task.priority || 'medium'
                    };
                    
                    // Create task element
                    createTaskElement(task.column_name, task.title, task.task_uuid);
                });
                
                // Update counts
                updateTaskCount('sales');
                updateTaskCount('finance');
                updateTaskCount('operation');
            } else {
                console.error('Failed to load tasks:', result.message);
            }
        } catch (error) {
            console.error('Error loading tasks:', error);
        }
    }

    // Add new task
    async function addTask(columnId) {
        const input = document.getElementById('input-' + columnId);
        if (!input.value.trim()) return;

        const taskId = generateTaskId();
        const taskTitle = input.value.trim();

        // Create task on server
        try {
            const response = await fetch(`${API_BASE_URL}/task.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_uuid: taskId,
                    column: columnId,
                    title: taskTitle,
                    description: '',
                    completed: false,
                    priority: 'medium'
                })
            });

            const result = await response.json();

            if (result.success) {
                // Create task in UI
                createTaskElement(columnId, taskTitle, taskId);
                input.value = '';
                updateTaskCount(columnId);
            } else {
                alert('Gagal menambahkan task: ' + result.message);
            }
        } catch (error) {
            console.error('Error adding task:', error);
            alert('Koneksi error');
        }
    }

    // Create task element
    function createTaskElement(columnId, text, taskId) {
        const task = document.createElement('div');
        task.className = 'task';
        task.draggable = true;
        task.dataset.taskId = taskId;
        task.dataset.column = columnId;

        // Initialize task data if not exists
        if (!tasks[taskId]) {
            tasks[taskId] = {
                id: taskId,
                title: text,
                description: '',
                column: columnId,
                completed: false,
                createdAt: new Date().toISOString()
            };
        }

        task.innerHTML = `
            <div class="task-check ${tasks[taskId].completed ? 'completed' : ''}" onclick="toggleTaskComplete(this)"></div>
            <span class="task-content" onclick="viewTaskDetail('${taskId}')">${escapeHtml(text)}</span>
            <div class="task-actions">
                <a href="#" class="detail-link" onclick="viewTaskDetail('${taskId}'); return false;">📝</a>
                <button class="delete-btn" onclick="deleteTask(this)" aria-label="delete task">✕</button>
            </div>
        `;

        // Drag events
        task.addEventListener('dragstart', function (e) {
            draggedTask = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        task.addEventListener('dragend', function () {
            this.classList.remove('dragging');
            document.querySelectorAll('.task-list').forEach(list => {
                list.classList.remove('drag-over');
            });
            
            const newColumn = this.parentNode.id;
            const taskId = this.dataset.taskId;
            if (taskId && tasks[taskId]) {
                tasks[taskId].column = newColumn;
                // Update task on server
                updateTaskOnServer(taskId, { column: newColumn });
            }
        });

        document.getElementById(columnId).appendChild(task);
    }

    // Update task on server
    async function updateTaskOnServer(taskId, updates) {
        const task = tasks[taskId];
        if (!task) return;

        try {
            const response = await fetch(`${API_BASE_URL}/task.php?task_uuid=${taskId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    title: task.title,
                    description: task.description || '',
                    column: updates.column || task.column,
                    completed: task.completed,
                    priority: task.priority || 'medium'
                })
            });

            const result = await response.json();
            if (!result.success) {
                console.error('Failed to update task:', result.message);
            }
        } catch (error) {
            console.error('Error updating task:', error);
        }
    }

    // Toggle task complete
    function toggleTaskComplete(element) {
        element.classList.toggle('completed');
        const task = element.closest('.task');
        const taskId = task.dataset.taskId;
        
        if (taskId && tasks[taskId]) {
            tasks[taskId].completed = element.classList.contains('completed');
            // Update on server
            updateTaskOnServer(taskId, { completed: tasks[taskId].completed });
        }
    }

    // View task detail
    function viewTaskDetail(taskId) {
        // Save current tasks to localStorage temporarily
        localStorage.setItem('tempTasks', JSON.stringify(tasks));
        
        // Redirect to detail page with task ID
        window.location.href = `?task=${taskId}`;
    }

    // Delete task
    async function deleteTask(btn) {
        if (!confirm('Yakin ingin menghapus task ini?')) return;

        const task = btn.closest('.task');
        const columnId = task.parentNode.id;
        const taskId = task.dataset.taskId;
        
        try {
            const response = await fetch(`${API_BASE_URL}/task.php?task_uuid=${taskId}`, {
                method: 'DELETE'
            });

            const result = await response.json();

            if (result.success) {
                // Remove from local data
                if (taskId && tasks[taskId]) {
                    delete tasks[taskId];
                }
                
                task.remove();
                updateTaskCount(columnId);
            } else {
                alert('Gagal menghapus task: ' + result.message);
            }
        } catch (error) {
            console.error('Error deleting task:', error);
            alert('Koneksi error');
        }
    }

    // Save task details
    async function saveTaskDetails(taskId) {
        const title = document.getElementById('taskTitle').value;
        const description = document.getElementById('taskDescription').value;
        
        if (tasks[taskId]) {
            tasks[taskId].title = title;
            tasks[taskId].description = description;
            
            // Update on server
            await updateTaskOnServer(taskId, { 
                title: title,
                description: description 
            });
        }
        
        // Redirect back to board
        window.location.href = 'index.php';
    }

    // Show detail page
    // Show detail page with chat and attachments
function showDetailPage(taskId) {
    // Load tasks from temp storage
    const savedTasks = localStorage.getItem('tempTasks');
    if (savedTasks) {
        tasks = JSON.parse(savedTasks);
        localStorage.removeItem('tempTasks');
    }

    const task = tasks[taskId];
    if (!task) {
        window.location.href = 'index.php';
        return;
    }
    
    // Sembunyikan board page
    document.getElementById('board-page').style.display = 'none';
    
    // Buat element detail page
    const detailPage = document.createElement('div');
    detailPage.id = 'detail-page';
    detailPage.className = 'detail-page';
    detailPage.innerHTML = `
        <div class="detail-header">
            <h1 class="detail-title">Task Details</h1>
            <a href="index.php" class="back-link">← Back to Board</a>
        </div>
        
        <div class="detail-meta">
            <div class="detail-meta-item">
                <span class="detail-meta-label">Task ID:</span>
                <span class="detail-meta-value">${taskId}</span>
            </div>
            <div class="detail-meta-item">
                <span class="detail-meta-label">Status:</span>
                <span class="status-badge ${task.completed ? 'completed' : 'pending'}">
                    ${task.completed ? '✓ Completed' : '○ Pending'}
                </span>
            </div>
            <div class="detail-meta-item">
                <span class="detail-meta-label">Column:</span>
                <span class="column-badge ${task.column}">
                    ${task.column.charAt(0).toUpperCase() + task.column.slice(1)}
                </span>
            </div>
            <div class="detail-meta-item">
                <span class="detail-meta-label">Created:</span>
                <span class="detail-meta-value">${new Date(task.createdAt).toLocaleString()}</span>
            </div>
        </div>

        <div class="detail-description">
            <h3>Title</h3>
            <input type="text" id="taskTitle" value="${escapeHtml(task.title).replace(/"/g, '&quot;')}" style="width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 20px; margin-bottom: 1.5rem; font-size: 1rem;">
            <h3>Description</h3>
            <textarea id="taskDescription" rows="4" placeholder="Add more details..." style="width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 20px; margin-bottom: 1.5rem; font-size: 1rem; font-family: inherit; resize: vertical;">${escapeHtml(task.description || '')}</textarea>
            
            <h3>Task Chat & Discussion</h3>
            <div class="chat-container">
                <div class="chat-messages" id="chatMessages">
                    <div class="loading-messages">Loading messages...</div>
                </div>
                
                <div class="chat-input-area">
                    <textarea id="chatMessageInput" placeholder="Type your message..." rows="2"></textarea>
                    <div class="chat-actions">
                        <button class="btn-attachment" onclick="showAttachmentPage('${taskId}')">
                            <i class="fas fa-paperclip"></i> See Attachments
                        </button>
                        <button class="btn-send" onclick="sendMessage('${taskId}')">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>
            </div>

            <div class="attachments-preview">
                <h4>Recent Attachments <span class="see-all-link" onclick="showAttachmentPage('${taskId}')">See all →</span></h4>
                <div class="attachment-list" id="recentAttachments">
                    <div class="loading-attachments">Loading attachments...</div>
                </div>
            </div>
        </div>

        <div class="detail-actions">
            <button class="btn btn-secondary" onclick="window.location.href='index.php'">Cancel</button>
            <button class="btn btn-primary" onclick="saveTaskDetails('${taskId}')">Save Changes</button>
        </div>
    `;
    
    document.body.appendChild(detailPage);
    
    // Load chat messages
    loadChatMessages(taskId);
    
    // Load recent attachments
    loadRecentAttachments(taskId);
    
    // Set interval untuk refresh chat setiap 3 detik
    setInterval(() => loadChatMessages(taskId), 3000);
}

// Load chat messages from database
// Load chat messages dengan error handling
function loadChatMessages(taskId) {
    fetch(`${API_BASE_URL}/get_messages.php?task_id=${taskId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            const chatMessages = document.getElementById('chatMessages');
            if (data.success) {
                if (data.messages.length === 0) {
                    chatMessages.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
                } else {
                    chatMessages.innerHTML = data.messages.map(msg => `
                        <div class="chat-message ${msg.is_current_user ? 'own-message' : 'other-message'}">
                            <div class="message-header">
                                <span class="message-user">${escapeHtml(msg.user_name)}</span>
                                <span class="message-time">${new Date(msg.created_at).toLocaleString()}</span>
                            </div>
                            <div class="message-content">${escapeHtml(msg.message)}</div>
                        </div>
                    `).join('');
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            } else {
                chatMessages.innerHTML = `<div class="error-message">Error: ${data.message || 'Unknown error'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error loading messages:', error);
            document.getElementById('chatMessages').innerHTML = 
                `<div class="error-message">Failed to load messages: ${error.message}</div>`;
        });
}
// Send message
function sendMessage(taskId) {
    const messageInput = document.getElementById('chatMessageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('message', message);
    
   fetch(`${API_BASE_URL}/send_message.php`, {
    method: 'POST',
    body: formData
})
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            loadChatMessages(taskId);
        } else {
            alert('Error sending message: ' + data.error);
        }
    })
    .catch(error => console.error('Error sending message:', error));
}

// Show attachment page
function showAttachmentPage(taskId) {
    // Create attachment page overlay
    const attachmentPage = document.createElement('div');
    attachmentPage.id = 'attachment-page';
    attachmentPage.className = 'attachment-page';
    attachmentPage.innerHTML = `
        <div class="attachment-header">
            <h2>Task Attachments</h2>
            <button class="close-btn" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
        
        <div class="attachment-content">
            <div class="upload-section">
                <h3>Upload New Attachment</h3>
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="task_id" value="${taskId}">
                    
                    <div class="file-drop-area" id="fileDropArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop files here or click to browse</p>
                        <input type="file" id="fileInput" name="file" style="display: none;" multiple>
                    </div>
                    
                    <div class="selected-files" id="selectedFiles"></div>
                    
                    <button type="button" class="btn-upload" onclick="uploadFiles('${taskId}')">
                        <i class="fas fa-upload"></i> Upload Files
                    </button>
                </form>
            </div>
            
            <div class="attachments-list-section">
                <h3>All Attachments</h3>
                <div class="attachments-grid" id="allAttachments">
                    <div class="loading">Loading attachments...</div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(attachmentPage);
    
    // Setup file drop area
    setupFileDrop();
    
    // Load all attachments
    loadAllAttachments(taskId);
}

// Setup file drop area
function setupFileDrop() {
    const dropArea = document.getElementById('fileDropArea');
    const fileInput = document.getElementById('fileInput');
    
    dropArea.addEventListener('click', () => fileInput.click());
    
    dropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropArea.classList.add('dragover');
    });
    
    dropArea.addEventListener('dragleave', () => {
        dropArea.classList.remove('dragover');
    });
    
    dropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dropArea.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });
    
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });
}

// Handle selected files
function handleFiles(files) {
    const selectedFiles = document.getElementById('selectedFiles');
    selectedFiles.innerHTML = '';
    
    Array.from(files).forEach(file => {
        const fileItem = document.createElement('div');
        fileItem.className = 'selected-file-item';
        fileItem.innerHTML = `
            <i class="fas ${getFileIcon(file.type)}"></i>
            <span class="file-name">${file.name}</span>
            <span class="file-size">(${formatFileSize(file.size)})</span>
        `;
        selectedFiles.appendChild(fileItem);
    });
}

// Upload files
// Upload files
function uploadFiles(taskId) {
    const fileInput = document.getElementById('fileInput');
    const files = fileInput.files;
    
    
    if (files.length === 0) {
        alert('Please select files to upload');
        return;
    }
    
    // Validasi file sebelum upload
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // Cek ukuran (max 10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert(`File ${file.name} terlalu besar (max 10MB)`);
            return;
        }
        
        // Cek tipe file
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            if (!confirm(`File ${file.name} memiliki tipe ${file.type} yang mungkin tidak didukung. Lanjutkan?`)) {
                return;
            }
        }
    }
    
    const formData = new FormData();
    formData.append('task_id', taskId);
    
    // Append multiple files dengan nama field yang benar
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }
    
    // Tampilkan loading
    const uploadBtn = document.querySelector('.btn-upload');
    const originalText = uploadBtn.innerHTML;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    uploadBtn.disabled = true;
    
    fetch(`${API_BASE_URL}/upload_attachment.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        
        if (data.success) {
            alert(`✅ ${data.uploaded.length} files uploaded successfully!`);
            if (data.errors.length > 0) {
                console.warn('Upload errors:', data.errors);
                alert('Some files failed:\n' + data.errors.join('\n'));
            }
            
            // Refresh both attachment lists
            loadAllAttachments(taskId);
            loadRecentAttachments(taskId);
            
            // Clear selected files
            document.getElementById('selectedFiles').innerHTML = '';
            fileInput.value = '';
        } else {
            alert('❌ Upload failed: ' + (data.message || 'Unknown error'));
            if (data.errors && data.errors.length > 0) {
                console.error('Upload errors:', data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Error uploading files:', error);
        alert('Error uploading files: ' + error.message);
    })
    .finally(() => {
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
    });
}

// Load all attachments
// Load all attachments
function loadAllAttachments(taskId) {
    
    const attachmentsGrid = document.getElementById('allAttachments');
    if (!attachmentsGrid) return;
    
    attachmentsGrid.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading attachments...</div>';
    
    // Gunakan taskId (UUID) sebagai parameter
    fetch(`${API_BASE_URL}/get_attachments.php?task_id=${taskId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            
            if (data.success && data.attachments && data.attachments.length > 0) {
                attachmentsGrid.innerHTML = data.attachments.map(att => {
                    return `
                    <div class="attachment-card" id="attachment-${att.id}">
                        <div class="attachment-icon">
                            <i class="fas ${getFileIcon(att.file_type)}"></i>
                        </div>
                        <div class="attachment-details">
                            <a href="${att.file_url}" target="_blank" class="attachment-title" title="${escapeHtml(att.file_name)}">
                                ${escapeHtml(att.file_name.length > 30 ? att.file_name.substring(0, 30) + '...' : att.file_name)}
                            </a>
                            <div class="attachment-metadata">
                                <span class="attachment-size">${formatFileSize(att.file_size)}</span>
                                <span class="attachment-date">${new Date(att.uploaded_at.replace(' ', 'T')).toLocaleString()}</span>
                                <span class="attachment-uploader">by ${escapeHtml(att.uploaded_by_name || 'System')}</span>
                            </div>
                        </div>
                        <button class="btn-delete" onclick="deleteAttachment(${att.id}, '${taskId}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `}).join('');
            } else {
                attachmentsGrid.innerHTML = '<div class="no-attachments">📁 No attachments yet</div>';
            }
        })
        .catch(error => {
            console.error('Error loading attachments:', error);
            attachmentsGrid.innerHTML = `<div class="error-message">❌ Failed to load attachments: ${error.message}</div>`;
        });
}
// Load recent attachments
function loadRecentAttachments(taskId) {
    
    const recentAttachments = document.getElementById('recentAttachments');
    if (!recentAttachments) return;
    
    recentAttachments.innerHTML = '<div class="loading-attachments"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch(`${API_BASE_URL}/get_attachments.php?task_id=${taskId}&recent=5`)
        .then(response => response.json())
        .then(data => {
            
            if (data.success && data.attachments && data.attachments.length > 0) {
                recentAttachments.innerHTML = data.attachments.map(att => {
                    const fileUrl = att.file_url;
                    
                    return `
                    <div class="attachment-item">
                        <i class="fas ${getFileIcon(att.file_type)}"></i>
                        <div class="attachment-info">
                            <a href="${fileUrl}" target="_blank" class="attachment-name">${escapeHtml(att.file_name)}</a>
                            <span class="attachment-meta">
                                ${formatFileSize(att.file_size)} • ${new Date(att.uploaded_at.replace(' ', 'T')).toLocaleDateString()}
                            </span>
                        </div>
                    </div>
                `}).join('');
            } else {
                recentAttachments.innerHTML = '<div class="no-attachments">📁 No attachments yet</div>';
            }
        })
        .catch(error => {
            console.error('Error loading recent attachments:', error);
            recentAttachments.innerHTML = '<div class="error-message">Failed to load attachments</div>';
        });
}

// Delete attachment
function deleteAttachment(attachmentId, taskId) {
    if (!confirm('Are you sure you want to delete this attachment?')) return;
    
   fetch(`${API_BASE_URL}/delete_attachment.php`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        attachment_id: attachmentId
    })
})
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAllAttachments(taskId);
            loadRecentAttachments(taskId);
        } else {
            alert('Error deleting attachment: ' + data.message);
        }
    })
    .catch(error => console.error('Error deleting attachment:', error));
}

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getFileIcon(fileType) {
    if (fileType.startsWith('image/')) return 'fa-file-image';
    if (fileType.startsWith('video/')) return 'fa-file-video';
    if (fileType.startsWith('audio/')) return 'fa-file-audio';
    if (fileType.includes('pdf')) return 'fa-file-pdf';
    if (fileType.includes('word')) return 'fa-file-word';
    if (fileType.includes('excel')) return 'fa-file-excel';
    if (fileType.includes('zip') || fileType.includes('rar')) return 'fa-file-archive';
    return 'fa-file';
}

    // Drag & drop setup
    const taskLists = document.querySelectorAll('.task-list');

    taskLists.forEach(list => {
        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });

        list.addEventListener('dragleave', function () {
            this.classList.remove('drag-over');
        });

        list.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            if (draggedTask && this !== draggedTask.parentNode) {
                const oldColumn = draggedTask.parentNode.id;
                const newColumn = this.id;
                const taskId = draggedTask.dataset.taskId;
                
                this.appendChild(draggedTask);
                
                // Update counts
                updateTaskCount(oldColumn);
                updateTaskCount(newColumn);
                
                // Update task data
                if (taskId && tasks[taskId]) {
                    tasks[taskId].column = newColumn;
                    // Update on server
                    updateTaskOnServer(taskId, { column: newColumn });
                }
            }
        });
    });

    document.addEventListener('dragover', e => e.preventDefault());
    document.addEventListener('drop', e => e.preventDefault());

    // Check if we're on detail page
    function initPage() {
        const urlParams = new URLSearchParams(window.location.search);
        const taskId = urlParams.get('task');
        
        if (taskId) {
            // Load tasks from localStorage if available
            const savedTasks = localStorage.getItem('tempTasks');
            if (savedTasks) {
                tasks = JSON.parse(savedTasks);
                showDetailPage(taskId);
            } else {
                // If no saved tasks, load from server first
                loadTasks().then(() => {
                    showDetailPage(taskId);
                });
            }
        } else {
            // Show board page and load tasks
            loadTasks();
        }
    }

    // Initialize the page
    initPage();

</script>

</body>
</html>