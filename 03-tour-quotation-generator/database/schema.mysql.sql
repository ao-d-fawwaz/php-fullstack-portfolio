-- Tour Quotation Generator schema (MySQL / MariaDB)
CREATE DATABASE IF NOT EXISTS tour_quotation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tour_quotation;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE quotations (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    tour_code          VARCHAR(20)    NOT NULL,
    tour_name          VARCHAR(150)   NOT NULL,
    period_start       DATE           NOT NULL,
    period_end         DATE           NOT NULL,
    min_pax            INT            NOT NULL,
    foc                INT            NOT NULL DEFAULT 0,
    rate_usd           DECIMAL(12,2)  NOT NULL,
    rate_rmb           DECIMAL(12,2)  NOT NULL,
    pic                VARCHAR(100)   NOT NULL DEFAULT '',
    final_selling      DECIMAL(15,2)  NULL,
    selling_per_pax    DECIMAL(15,2)  NOT NULL,   -- cached from the calculator for the list view
    net_margin_per_pax DECIMAL(15,2)  NOT NULL,
    updated_at         DATETIME       NOT NULL
);

CREATE TABLE quotation_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    quotation_id   INT            NOT NULL,
    position       INT            NOT NULL,
    type           ENUM('main','variable','optional') NOT NULL,
    description    VARCHAR(255)   NOT NULL,
    currency       ENUM('IDR','USD','RMB') NOT NULL DEFAULT 'IDR',
    nett_vendor    DECIMAL(15,2)  NOT NULL,
    divide_by      INT            NULL,          -- NULL = tour minimum pax
    profit_percent DECIMAL(6,2)   NOT NULL DEFAULT 0,
    qty_days       DECIMAL(8,2)   NOT NULL DEFAULT 1,
    remark         VARCHAR(255)   NOT NULL DEFAULT '',
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
);
