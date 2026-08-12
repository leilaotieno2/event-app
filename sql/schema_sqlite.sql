-- ============================================================
-- SQLite equivalent of schema.sql, for local demo/testing only
-- (no MySQL server required). The graded submission uses
-- sql/schema.sql against MySQL/MariaDB.
-- ============================================================

CREATE TABLE users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            VARCHAR(100)        NOT NULL,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,
    role            VARCHAR(10)         NOT NULL DEFAULT 'user' CHECK (role IN ('user','admin')),
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE events (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    title           VARCHAR(150)        NOT NULL,
    description     TEXT,
    location        VARCHAR(150),
    category        VARCHAR(50)         NOT NULL DEFAULT 'General',
    event_date      DATETIME            NOT NULL,
    total_slots     INTEGER             NOT NULL,
    created_by      INTEGER,
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE registrations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL,
    event_id        INTEGER NOT NULL,
    checkin_code    VARCHAR(20) NOT NULL,
    checked_in_at   TIMESTAMP NULL DEFAULT NULL,
    registered_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, event_id),
    UNIQUE (checkin_code),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE waitlist (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL,
    event_id        INTEGER NOT NULL,
    joined_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE event_feedback (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL,
    event_id        INTEGER NOT NULL,
    rating          INTEGER NOT NULL,
    comment         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);
