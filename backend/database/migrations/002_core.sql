-- Migration: 002_core.sql
-- Creates core data model tables.
-- Idempotent: safe to run multiple times (CREATE TABLE IF NOT EXISTS).
--
-- Tables in this file:
--   1. system_entities   (STORY 2.1)
--   2. plugins_registry  (STORY 2.4)

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
