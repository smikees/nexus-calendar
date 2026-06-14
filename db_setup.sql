-- Nexus Calendar schema. Run once in phpMyAdmin against t2hu9otd1ek3_nexuscal.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  google_sub    VARCHAR(255) NOT NULL,
  email         VARCHAR(255) NOT NULL,
  display_name  VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_google_sub (google_sub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendars (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_user_id    BIGINT UNSIGNED DEFAULT NULL,   -- NULL => public/admin-managed
  slug             VARCHAR(120) NOT NULL,
  name             VARCHAR(255) NOT NULL,
  description      TEXT,
  color            VARCHAR(9) NOT NULL DEFAULT '#38bdf8',
  icon             VARCHAR(64) DEFAULT NULL,
  visibility       ENUM('public','private') NOT NULL DEFAULT 'private',
  default_priority INT NOT NULL DEFAULT 100,
  feed_token       VARCHAR(64) DEFAULT NULL,
  timezone         VARCHAR(64) NOT NULL DEFAULT 'UTC',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_calendars_slug (slug),
  KEY idx_calendars_owner (owner_user_id),
  KEY idx_calendars_visibility (visibility),
  CONSTRAINT fk_calendars_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  calendar_id  BIGINT UNSIGNED NOT NULL,
  uid          VARCHAR(255) NOT NULL,
  title        VARCHAR(512) NOT NULL,
  description  TEXT,
  location     VARCHAR(512) DEFAULT NULL,
  starts_at    DATETIME NOT NULL,              -- stored UTC
  ends_at      DATETIME NOT NULL,
  all_day      TINYINT(1) NOT NULL DEFAULT 0,
  timezone     VARCHAR(64) NOT NULL DEFAULT 'UTC',
  rrule        TEXT,
  exdates      TEXT,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_events_uid (uid),
  KEY idx_events_calendar (calendar_id),
  KEY idx_events_starts (starts_at),
  CONSTRAINT fk_events_calendar FOREIGN KEY (calendar_id) REFERENCES calendars(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_calendar_prefs (
  user_id           BIGINT UNSIGNED NOT NULL,
  calendar_id       BIGINT UNSIGNED NOT NULL,
  enabled           TINYINT(1) NOT NULL DEFAULT 1,
  color_override    VARCHAR(9) DEFAULT NULL,
  priority_override INT DEFAULT NULL,
  PRIMARY KEY (user_id, calendar_id),
  CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_prefs_calendar FOREIGN KEY (calendar_id) REFERENCES calendars(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
