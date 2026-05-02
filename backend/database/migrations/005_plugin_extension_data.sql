-- Migration: 005_plugin_extension_data.sql
-- Generic storage table for all extension-type plugins.
-- Parallels plugin_entity_data but attaches data to existing entity records.
-- Each extension plugin stores its records here, identified by plugin_slug.
-- Idempotent: safe to run multiple times (CREATE TABLE IF NOT EXISTS).
-- STORY 6.3

CREATE TABLE IF NOT EXISTS plugin_extension_data (
    id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    plugin_slug  VARCHAR(100) NOT NULL,
    entity_slug  VARCHAR(100) NOT NULL,
    record_id    UUID         NOT NULL,
    content      JSONB        NOT NULL DEFAULT '{}',
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_plugin_extension_data_record
    ON plugin_extension_data (plugin_slug, entity_slug, record_id);
