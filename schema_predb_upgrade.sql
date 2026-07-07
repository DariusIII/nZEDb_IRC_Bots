ALTER TABLE predb
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    DROP INDEX idx_predb_title,
    ADD UNIQUE KEY uk_predb_title (title),
    ADD KEY idx_predb_md5 (md5),
    ADD KEY idx_predb_shared_predate (shared, predate);
