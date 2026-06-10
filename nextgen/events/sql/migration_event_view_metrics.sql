-- Esemény metrikák: oldalmegtekintés vs. naptár előnézet + forrás (direct, calendar, list, …).
-- Futtatás: events_calendar_event_views tábla létezik (migration_events.sql).

ALTER TABLE `events_calendar_event_views`
    ADD COLUMN `metric_type` VARCHAR(32) NOT NULL DEFAULT 'page_view' COMMENT 'page_view | calendar_preview' AFTER `ip_hash`,
    ADD COLUMN `source` VARCHAR(32) NULL COMMENT 'direct, calendar, cal_preview, list, …' AFTER `metric_type`,
    ADD KEY `idx_ec_ev_metric_type` (`metric_type`),
    ADD KEY `idx_ec_ev_esemeny_metric` (`esemény_id`, `metric_type`);

UPDATE `events_calendar_event_views`
SET `metric_type` = 'page_view', `source` = 'direct'
WHERE `metric_type` = 'page_view' AND `source` IS NULL;
