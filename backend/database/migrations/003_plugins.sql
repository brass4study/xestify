-- Migration: 003_plugins.sql
-- Registry of all plugins installed in the system.
-- Stores lifecycle metadata and optional schema definition.
-- plugin_type: 'entity' | 'extension'
-- status:      'active' | 'inactive' | 'error'
-- Idempotent: safe to run multiple times (CREATE TABLE IF NOT EXISTS).
-- STORY 2.4

CREATE TABLE IF NOT EXISTS plugins (
    id             UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    slug           VARCHAR(100) NOT NULL,
    name           VARCHAR(255) NOT NULL DEFAULT '',
    plugin_type    VARCHAR(20)  NOT NULL,
    version        VARCHAR(20)  NOT NULL,
    status         VARCHAR(20)  NOT NULL DEFAULT 'inactive',
    schema_version INTEGER      NOT NULL DEFAULT 1,
    schema_json    JSONB        NULL,
    installed_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    CONSTRAINT plugins_slug_unique   UNIQUE (slug),
    CONSTRAINT plugins_type_check    CHECK  (plugin_type IN ('entity', 'extension')),
    CONSTRAINT plugins_status_check  CHECK  (status IN ('active', 'inactive', 'error'))
);

CREATE INDEX IF NOT EXISTS idx_plugins_type_status
    ON plugins (plugin_type, status);
