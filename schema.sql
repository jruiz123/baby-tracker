-- Run this in phpMyAdmin or MySQL console
-- Creates the baby_tracker database and all tables

CREATE DATABASE IF NOT EXISTS baby_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE baby_tracker;

-- ── Users ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Babies ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS babies (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    name       VARCHAR(100) NOT NULL,
    birthdate  DATE,
    gender     ENUM('male','female','other') DEFAULT 'other',
    photo      VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── Logs (main) ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    baby_id         INT NOT NULL,
    user_id         INT NOT NULL,
    category        ENUM('temperature','diaper','feeding','sleep','play','medicine','note') NOT NULL,
    logged_at       DATETIME NOT NULL,
    notes           TEXT,
    alarm_enabled   TINYINT(1) DEFAULT 0,
    alarm_minutes   INT DEFAULT 240,
    alarm_triggered TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (baby_id) REFERENCES babies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── Temperature detail ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS log_temperature (
    log_id      INT PRIMARY KEY,
    temperature DECIMAL(4,1) NOT NULL,
    unit        CHAR(1) DEFAULT 'C',
    symptoms    VARCHAR(500),
    FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE CASCADE
);

-- ── Diaper detail ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS log_diaper (
    log_id INT PRIMARY KEY,
    type   ENUM('wet','solid','mixed','dry') DEFAULT 'wet',
    color  VARCHAR(50),
    FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE CASCADE
);

-- ── Feeding detail ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS log_feeding (
    log_id INT PRIMARY KEY,
    type   ENUM('breast','formula','solid','water','snack') DEFAULT 'formula',
    amount DECIMAL(6,1),
    unit   ENUM('ml','oz','g','tbsp') DEFAULT 'ml',
    food   VARCHAR(200),
    FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE CASCADE
);

-- ── Sleep detail ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS log_sleep (
    log_id           INT PRIMARY KEY,
    duration_minutes INT,
    quality          ENUM('good','restless','poor') DEFAULT 'good',
    FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE CASCADE
);

-- ── Medicine detail ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS log_medicine (
    log_id        INT PRIMARY KEY,
    medicine_name VARCHAR(200),
    dose          DECIMAL(6,2),
    unit          VARCHAR(50),
    FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE CASCADE
);
