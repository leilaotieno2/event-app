-- ============================================================
-- Event Registration System - Database Schema
-- Run this once to create the database and tables.
-- ============================================================

CREATE DATABASE IF NOT EXISTS event_registration
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE event_registration;

-- ------------------------------------------------------------
-- Users table
-- Passwords are NEVER stored in plain text - only bcrypt hashes
-- (created with PHP's password_hash()) are stored.
-- ------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)        NOT NULL,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Events table
-- ------------------------------------------------------------
CREATE TABLE events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(150)        NOT NULL,
    description     TEXT,
    location        VARCHAR(150),
    event_date      DATETIME            NOT NULL,
    total_slots     INT UNSIGNED        NOT NULL,
    created_by      INT,
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Registrations table
-- UNIQUE(user_id, event_id) is the database-level guarantee
-- that stops a user registering twice for the same event, even
-- under concurrent requests.
-- ------------------------------------------------------------
CREATE TABLE registrations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    event_id        INT NOT NULL,
    registered_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_event (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed an admin account (password: Admin@1234 - CHANGE AFTER FIRST LOGIN)
-- Hash below corresponds to 'Admin@1234' via PHP password_hash(PASSWORD_BCRYPT)
-- Generate your own with: php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
-- ------------------------------------------------------------
-- INSERT INTO users (name, email, password_hash, role)
-- VALUES ('System Admin', 'admin@example.com', '$2y$10$REPLACE_WITH_REAL_HASH', 'admin');
