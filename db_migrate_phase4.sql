-- Run once in phpMyAdmin on the nexuscal database.
-- (If avatar_url already exists, the ALTER will warn — safe to ignore.)
ALTER TABLE users ADD COLUMN avatar_url VARCHAR(512) DEFAULT NULL AFTER display_name;

CREATE TABLE IF NOT EXISTS calendar_shares (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  calendar_id         BIGINT UNSIGNED NOT NULL,
  shared_with_email   VARCHAR(255) NOT NULL,
  shared_with_user_id BIGINT UNSIGNED DEFAULT NULL,
  role                ENUM('viewer','editor') NOT NULL DEFAULT 'viewer',
  created_by          BIGINT UNSIGNED NOT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_share (calendar_id, shared_with_email),
  KEY idx_share_email (shared_with_email),
  CONSTRAINT fk_share_cal FOREIGN KEY (calendar_id) REFERENCES calendars(id) ON DELETE CASCADE,
  CONSTRAINT fk_share_user FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_share_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
