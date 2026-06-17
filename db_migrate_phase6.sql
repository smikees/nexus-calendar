-- Nexus Calendar — Phase 6 migration. Run once in phpMyAdmin against `nexuscal`.
-- Adds iCalendar feed-subscription columns to calendars. The app reads/writes these
-- defensively (calendars_has_feed()), so the app keeps working before this runs;
-- URL subscriptions return 503 'migration_required' until then. File import works regardless.
SET NAMES utf8mb4;

ALTER TABLE calendars
  ADD COLUMN feed_url         VARCHAR(1024) DEFAULT NULL AFTER feed_token,
  ADD COLUMN feed_last_synced DATETIME      DEFAULT NULL AFTER feed_url,
  ADD COLUMN feed_etag        VARCHAR(255)  DEFAULT NULL AFTER feed_last_synced;
