-- Migration: 002_plugin_entity_data.sql
-- Business records for any registered entity type.
-- content is an untyped JSONB bag; schema validated at application layer.
-- Soft delete via deleted_at (NULL = active).
-- Idempotent: safe to run multiple times (CREATE TABLE IF NOT EXISTS).
-- STORY 2.3

CREATE TABLE IF NOT EXISTS plugin_entity_data (
    id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    entity_slug  VARCHAR(100) NOT NULL,
    owner_id     UUID         NULL,
    content      JSONB        NOT NULL DEFAULT '{}',
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ  NULL
);

CREATE INDEX IF NOT EXISTS idx_plugin_entity_data_slug
    ON plugin_entity_data (entity_slug);

CREATE INDEX IF NOT EXISTS idx_plugin_entity_data_owner
    ON plugin_entity_data (owner_id);

CREATE INDEX IF NOT EXISTS idx_plugin_entity_data_content_gin
    ON plugin_entity_data USING GIN (content);
