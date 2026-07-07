ALTER TABLE predb
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    DROP INDEX idx_predb_title,
    ADD UNIQUE KEY uk_predb_title_group (title, groupname),
    ADD KEY idx_predb_group_reqid (groupname, reqid);
