-- Schema: 005_plugin_update_history.sql
-- Snapshots of plugin state before explicit updates.
-- Used to support transactional updates now and manual rollback in STORY 7.4.
-- manifest_json/schema_json mirror the plugins row being snapshotted (STORY
-- 10.3 §2bis) — no FK to plugins, this is an independent point-in-time copy.
-- Idempotent: safe to run multiple times.
-- STORY 7.2, STORY 10.3

-- In-place upgrade for a table created by the pre-STORY-10.3 version of this
-- file (columns name/plugin_type/version/schema_version instead of
-- manifest_json). CREATE TABLE IF NOT EXISTS below is a no-op against an
-- existing table, so without this block, picking up the new schema on an
-- environment that already ran the old migration required a manual DROP —
-- which is exactly what silently wiped every snapshot row during STORY 10.3.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'plugin_update_history'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'plugin_update_history'
          AND column_name = 'manifest_json'
    ) THEN
        ALTER TABLE plugin_update_history ADD COLUMN manifest_json JSONB;

        UPDATE plugin_update_history
        SET manifest_json = jsonb_build_object(
            'name', name,
            'type', plugin_type,
            'version', version
        );

        ALTER TABLE plugin_update_history ALTER COLUMN manifest_json SET NOT NULL;
        ALTER TABLE plugin_update_history DROP COLUMN name;
        ALTER TABLE plugin_update_history DROP COLUMN plugin_type;
        ALTER TABLE plugin_update_history DROP COLUMN version;
        ALTER TABLE plugin_update_history DROP COLUMN schema_version;
    END IF;
END $$;

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
