-- Migration: 006_plugin_update_history.sql
-- Snapshots of plugin state before explicit updates.
-- Used to support transactional updates now and manual rollback in STORY 7.4.
-- Idempotent: safe to run multiple times.
-- STORY 7.2

CREATE TABLE IF NOT EXISTS plugin_update_history (
    id             UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    slug           VARCHAR(100) NOT NULL,
    name           VARCHAR(255) NOT NULL DEFAULT '',
    plugin_type    VARCHAR(20)  NOT NULL,
    version        VARCHAR(20)  NOT NULL,
    status         VARCHAR(20)  NOT NULL,
    schema_version INTEGER      NOT NULL DEFAULT 1,
    schema_json    JSONB        NULL,
    target_version VARCHAR(20)  NOT NULL,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    CONSTRAINT plugin_update_history_type_check
        CHECK (plugin_type IN ('entity', 'extension')),
    CONSTRAINT plugin_update_history_status_check
        CHECK (status IN ('active', 'inactive', 'error'))
);

CREATE INDEX IF NOT EXISTS idx_plugin_update_history_slug_created_at
    ON plugin_update_history (slug, created_at DESC);
