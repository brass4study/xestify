-- Migration: 004_plugin_hooks.sql
-- Maps plugins to the entity hooks they handle.
-- hook_name: e.g. 'beforeSave', 'afterSave', 'registerTabs'
-- priority: lower value = executed first (default 10)
-- Idempotent: safe to run multiple times (CREATE TABLE IF NOT EXISTS).
-- STORY 2.5

CREATE TABLE IF NOT EXISTS plugin_hooks (
    id                  UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    slug                VARCHAR(100) NOT NULL,
    target_entity_slug  VARCHAR(100) NOT NULL,
    hook_name           VARCHAR(50)  NOT NULL,
    priority            INTEGER      NOT NULL DEFAULT 10,
    enabled             BOOLEAN      NOT NULL DEFAULT true
);

CREATE INDEX IF NOT EXISTS idx_plugin_hooks_target_hook
    ON plugin_hooks (target_entity_slug, hook_name);
