-- ============================================================
-- Migration 002: categories, waitlist, check-in codes, feedback
-- Run against an existing database created from the original
-- schema.sql. Safe to run once.
-- ============================================================

ALTER TABLE events
    ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'General' AFTER location;

ALTER TABLE registrations
    ADD COLUMN checkin_code VARCHAR(20) NULL AFTER event_id,
    ADD COLUMN checked_in_at TIMESTAMP NULL DEFAULT NULL AFTER checkin_code;

-- Backfill existing rows with a random check-in code, then enforce NOT NULL + UNIQUE.
UPDATE registrations SET checkin_code = UPPER(SUBSTRING(MD5(RAND()), 1, 8)) WHERE checkin_code IS NULL;
ALTER TABLE registrations
    MODIFY COLUMN checkin_code VARCHAR(20) NOT NULL,
    ADD UNIQUE KEY uniq_checkin_code (checkin_code);

CREATE TABLE IF NOT EXISTS waitlist (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    event_id        INT NOT NULL,
    joined_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_waitlist_user_event (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_feedback (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    event_id        INT NOT NULL,
    rating          TINYINT UNSIGNED NOT NULL,
    comment         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_feedback_user_event (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;
