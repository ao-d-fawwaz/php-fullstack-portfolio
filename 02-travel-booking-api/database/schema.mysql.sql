-- Travel Booking API schema (MySQL / MariaDB)
CREATE DATABASE IF NOT EXISTS travel_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE travel_booking;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(30)  NOT NULL UNIQUE,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('staff','admin') NOT NULL DEFAULT 'staff',
    created_at    DATETIME NOT NULL
);

CREATE TABLE products (
    code          VARCHAR(20)  PRIMARY KEY,
    title         VARCHAR(255) NOT NULL,
    price         BIGINT       NOT NULL,          -- per person, in IDR
    duration_days INT          NOT NULL,
    vendor        VARCHAR(100) NOT NULL,
    airline       VARCHAR(100) NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE bookings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_name   VARCHAR(150) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    email           VARCHAR(255) NOT NULL,
    departure_date  DATE         NOT NULL,
    pax             INT          NOT NULL,
    passport_number VARCHAR(12)  NOT NULL,
    passport_expiry DATE         NOT NULL,
    product_code    VARCHAR(20)  NOT NULL,
    status          ENUM('pending','confirmed','paid','cancelled') NOT NULL DEFAULT 'pending',
    created_by      INT          NOT NULL,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NULL,
    FOREIGN KEY (product_code) REFERENCES products(code),
    FOREIGN KEY (created_by)   REFERENCES users(id),
    INDEX idx_bookings_status (status)
);

-- Snapshot of the product at booking time (price changes never alter history)
CREATE TABLE ledger_entries (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    booking_id    INT          NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    product_code  VARCHAR(20)  NOT NULL,
    product_title VARCHAR(255) NOT NULL,
    unit_price    BIGINT       NOT NULL,
    pax           INT          NOT NULL,
    total_amount  BIGINT       NOT NULL,
    duration_days INT          NOT NULL,
    vendor        VARCHAR(100) NOT NULL,
    airline       VARCHAR(100) NOT NULL,
    created_at    DATETIME     NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

CREATE TABLE rate_limits (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    route        VARCHAR(100) NOT NULL,
    request_time INT          NOT NULL,
    INDEX idx_rate (ip_address, route, request_time)
);
