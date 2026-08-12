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
    registered_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, event_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);
