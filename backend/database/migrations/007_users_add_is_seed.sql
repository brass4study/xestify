-- Migration: 007_users_add_is_seed.sql
-- Marks fixed seed users (admin/normal) as protected: not editable nor deletable
-- from user management, so the STORY 10.1 quick-access login buttons keep working.
-- Idempotent: safe to run multiple times.

ALTER TABLE users ADD COLUMN IF NOT EXISTS is_seed BOOLEAN NOT NULL DEFAULT FALSE;

-- Backfill: on a database seeded before this migration existed, the admin row
-- predates the is_seed column (and UserSeeder's ON CONFLICT DO NOTHING never
-- retro-updates it), so it would otherwise stay unprotected on upgrade.
UPDATE users SET is_seed = TRUE WHERE email = 'admin@xestify.local' AND is_seed = FALSE;
