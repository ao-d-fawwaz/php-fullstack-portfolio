-- Karate Tournament Bracket schema (MySQL / MariaDB)
CREATE DATABASE IF NOT EXISTS karate_tournament CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE karate_tournament;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE participants (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sex        ENUM('M','F') NOT NULL,
    birth_date DATE         NOT NULL,
    weight_kg  DECIMAL(5,1) NOT NULL,
    belt       VARCHAR(20)  NOT NULL,
    dojo       VARCHAR(100) NOT NULL,
    event      ENUM('kumite','kata') NOT NULL,
    created_at DATETIME     NOT NULL
);

CREATE TABLE brackets (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_key VARCHAR(50) NOT NULL UNIQUE,
    created_at   DATETIME    NOT NULL
);

CREATE TABLE matches (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    bracket_id INT NOT NULL,
    round      INT NOT NULL,
    position   INT NOT NULL,
    red_id     INT NULL,
    white_id   INT NULL,
    winner_id  INT NULL,
    UNIQUE KEY uq_slot (bracket_id, round, position),
    FOREIGN KEY (bracket_id) REFERENCES brackets(id) ON DELETE CASCADE,
    FOREIGN KEY (red_id)    REFERENCES participants(id),
    FOREIGN KEY (white_id)  REFERENCES participants(id),
    FOREIGN KEY (winner_id) REFERENCES participants(id)
);
