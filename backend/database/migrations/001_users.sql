-- Migration: 001_users.sql
-- Creates the users table for authentication.
-- Idempotent: safe to run multiple times.

CREATE TABLE IF NOT EXISTS users (
    id            UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    email         VARCHAR(255) NOT NULL,
    password_hash TEXT        NOT NULL,
    roles         JSONB       NOT NULL DEFAULT '["operador"]',
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT users_email_unique UNIQUE (email)
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
