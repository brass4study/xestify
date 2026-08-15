-- Migration: 005_plugin_update_history.sql
-- Snapshots of plugin state before explicit updates.
-- Used to support transactional updates now and manual rollback in STORY 7.4.
-- manifest_json/schema_json mirror the plugins row being snapshotted (STORY
-- 10.3 §2bis) — no FK to plugins, this is an independent point-in-time copy.
-- Idempotent: safe to run multiple times.
-- STORY 7.2, STORY 10.3

CREATE TABLE IF NOT EXISTS plugin_update_history (
    id             UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    slug           VARCHAR(100) NOT NULL,
    status         VARCHAR(20)  NOT NULL,
    manifest_json  JSONB        NOT NULL,
    schema_json    JSONB        NULL,
    target_version VARCHAR(20)  NOT NULL,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    CONSTRAINT plugin_update_history_status_check
        CHECK (status IN ('active', 'inactive', 'error'))
);

CREATE INDEX IF NOT EXISTS idx_plugin_update_history_slug_created_at
    ON plugin_update_history (slug, created_at DESC);
