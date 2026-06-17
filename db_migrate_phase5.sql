-- Nexus Calendar — Phase 5 migration. Run once in phpMyAdmin against `nexuscal`.
-- Adds a per-event emoji/icon. The app reads/writes this defensively, so events
-- keep working before this runs; per-event icons simply won't persist until then.
SET NAMES utf8mb4;

ALTER TABLE events
  ADD COLUMN icon VARCHAR(64) DEFAULT NULL AFTER location;
