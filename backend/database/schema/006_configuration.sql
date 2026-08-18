-- Schema: 006_configuration.sql
-- Global application configuration storage.
-- Reusable key/value JSON store for UI preferences and future global settings.
-- Idempotent: safe to run multiple times.

CREATE TABLE IF NOT EXISTS configuration (
    config_key  VARCHAR(120) PRIMARY KEY,
    value_json  JSONB        NOT NULL DEFAULT '{}'::jsonb,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_configuration_updated_at
    ON configuration (updated_at DESC);
