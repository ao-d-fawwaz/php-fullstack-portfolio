-- Kanban Task Board schema (MySQL / MariaDB)
CREATE DATABASE IF NOT EXISTS kanban_board CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kanban_board;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE columns (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(50)  NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    position     INT          NOT NULL DEFAULT 0
);

CREATE TABLE tasks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    task_uuid   VARCHAR(64)  NOT NULL UNIQUE,
    user_id     INT          NOT NULL,
    column_id   INT          NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    completed   TINYINT(1)   NOT NULL DEFAULT 0,
    priority    ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    due_date    DATE NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (column_id) REFERENCES columns(id)
);

CREATE TABLE task_history (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    task_id    INT          NOT NULL,
    user_id    INT          NOT NULL,
    action     VARCHAR(50)  NOT NULL,
    old_value  TEXT,
    new_value  TEXT,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

CREATE TABLE task_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    task_id    INT      NOT NULL,
    user_id    INT      NOT NULL,
    message    TEXT     NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE task_attachments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    task_id     INT          NOT NULL,
    uploaded_by INT          NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_size   INT          NOT NULL,
    file_type   VARCHAR(100) NOT NULL,
    uploaded_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id)     REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

INSERT INTO columns (name, display_name, position) VALUES
    ('sales', 'Sales', 1), ('finance', 'Finance', 2), ('operation', 'Operation', 3);
