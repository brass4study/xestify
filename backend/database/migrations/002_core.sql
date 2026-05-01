-- Migration: 002_core.sql
-- Creates core data model tables.
-- Idempotent: safe to run multiple times (CREATE TABLE IF NOT EXISTS).
--
-- Tables in this file:
--   1. system_entities        (STORY 2.1)
--   2. entity_metadata        (STORY 2.2)
--   3. entity_data            (STORY 2.3)
--   4. plugins_registry       (STORY 2.4)
--   5. plugin_hook_registry   (STORY 2.5)

-- ---------------------------------------------------------------------------
-- STORY 2.1: system_entities
-- Registry of all entity types known to the system.
-- Plugins register their entity types here on install.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS system_entities (
    id                  UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    slug                VARCHAR(100) NOT NULL,
    name                VARCHAR(255) NOT NULL,
    source_plugin_slug  VARCHAR(100) NULL,
    is_active           BOOLEAN      NOT NULL DEFAULT true,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    CONSTRAINT system_entities_slug_unique UNIQUE (slug)
);

-- ---------------------------------------------------------------------------
-- STORY 2.2: entity_metadata
-- Stores versioned schema definitions for each entity slug.
-- schema_json must be an object containing at least the "fields" key.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS entity_metadata (
    id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    entity_slug     VARCHAR(100) NOT NULL,
    schema_version  INTEGER      NOT NULL DEFAULT 1,
    schema_json     JSONB        NOT NULL,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    CONSTRAINT entity_metadata_schema_json_check
        CHECK (jsonb_typeof(schema_json) = 'object' AND schema_json ? 'fields')
);

CREATE INDEX IF NOT EXISTS idx_entity_metadata_slug_version
    ON entity_metadata (entity_slug, schema_version);

-- ---------------------------------------------------------------------------
-- STORY 2.3: entity_data
-- Business records for any registered entity type.
-- content is an untyped JSONB bag; schema validated at application layer.
-- Soft delete via deleted_at (NULL = active).
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS entity_data (
    id           UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    entity_slug  VARCHAR(100) NOT NULL,
    owner_id     UUID         NULL,
    content      JSONB        NOT NULL DEFAULT '{}',
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at   TIMESTAMPTZ  NULL
);

CREATE INDEX IF NOT EXISTS idx_entity_data_slug
    ON entity_data (entity_slug);

CREATE INDEX IF NOT EXISTS idx_entity_data_owner
    ON entity_data (owner_id);

CREATE INDEX IF NOT EXISTS idx_entity_data_content_gin
    ON entity_data USING GIN (content);

-- ---------------------------------------------------------------------------
-- STORY 2.5: plugin_hook_registry
-- Maps plugins to the entity hooks they handle.
-- hook_name: e.g. 'beforeSave', 'afterSave', 'registerTabs'
-- priority: lower value = executed first (default 10)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS plugin_hook_registry (
    id                  UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    plugin_slug         VARCHAR(100) NOT NULL,
    target_entity_slug  VARCHAR(100) NOT NULL,
    hook_name           VARCHAR(50)  NOT NULL,
    priority            INTEGER      NOT NULL DEFAULT 10,
    enabled             BOOLEAN      NOT NULL DEFAULT true
);

CREATE INDEX IF NOT EXISTS idx_plugin_hook_registry_target_hook
    ON plugin_hook_registry (target_entity_slug, hook_name);

-- ---------------------------------------------------------------------------
-- STORY 2.4: plugins_registry
-- Registry of all plugins installed in the system.
-- plugin_type: 'entity' | 'extension'
-- status:      'active' | 'inactive' | 'error'
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS plugins_registry (
    id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    plugin_slug  VARCHAR(100) NOT NULL,
    plugin_type  VARCHAR(20)  NOT NULL,
    version      VARCHAR(20)  NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'inactive',
    installed_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    CONSTRAINT plugins_registry_slug_unique   UNIQUE (plugin_slug),
    CONSTRAINT plugins_registry_type_check    CHECK  (plugin_type IN ('entity', 'extension')),
    CONSTRAINT plugins_registry_status_check  CHECK  (status IN ('active', 'inactive', 'error'))
);
