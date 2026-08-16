-- Migration: 003_plugins.sql
-- Registry of all plugins installed in the system.
-- Stores lifecycle metadata and optional schema definition.
-- status: 'active' | 'inactive' | 'error'
-- manifest_json: mirrors the plugin's manifest.json on disk — {name, label,
--   label_singular, version, type, core_version, target_entity, description}.
--   Live, not a frozen install-time snapshot:
--   - name/version/type/core_version/label_singular always mirror the
--     manifest on disk, refreshed on every update() (never admin-editable).
--   - label/description/target_entity are admin-editable via PluginConfig
--     (PluginIdentityService::updateIdentity() / ExtensionPluginConfigService);
--     update() preserves them (merge, never overwrite).
--   manifest_json->>'name' is the fixed technical identity (= plugin folder
--   name / PHP namespace), used by PluginClassLoader/PluginHookRegistrar/
--   PluginLifecycleInvoker to load classes. Not unique: the same folder can
--   be registered as multiple independent instances (each with its own
--   unique slug/data), e.g. two "persons" rows with different slugs.
-- slug: editable navigation/URL identifier (STORY 10.3), unique.
-- sort_order: manual display order (Subir/Bajar actions in PluginManager),
--   drives both the plugin table order and the navigation menu order for
--   entities.
-- Idempotent: safe to run multiple times (CREATE TABLE IF NOT EXISTS).
-- STORY 2.4, STORY 10.3

CREATE TABLE IF NOT EXISTS plugins (
    id             UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    slug           VARCHAR(100) NOT NULL,
    status         VARCHAR(20)  NOT NULL DEFAULT 'inactive',
    manifest_json  JSONB        NOT NULL,
    schema_json    JSONB        NULL,
    sort_order     INTEGER      NOT NULL DEFAULT 0,
    installed_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

    CONSTRAINT plugins_slug_unique   UNIQUE (slug),
    CONSTRAINT plugins_type_check    CHECK  (manifest_json->>'type' IN ('entity', 'extension')),
    CONSTRAINT plugins_status_check  CHECK  (status IN ('active', 'inactive', 'error'))
);

CREATE INDEX IF NOT EXISTS idx_plugins_type_status
    ON plugins ((manifest_json->>'type'), status);

CREATE INDEX IF NOT EXISTS idx_plugins_plugin_name
    ON plugins ((manifest_json->>'name'));
