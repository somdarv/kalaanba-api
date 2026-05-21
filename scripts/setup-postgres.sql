-- Kalaanba API — Postgres bootstrap
-- Run as the postgres superuser:
--   & 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -U postgres -v app_password=`"$(Get-Content .pg-app-password)`" -f scripts/setup-postgres.sql
-- This creates the kalaanba application role and the kalaanba_dev / kalaanba_test databases.
-- Idempotent: safe to re-run.

\set ON_ERROR_STOP on

-- 1. Application role (login, no superuser, no createdb in prod — createdb here for local convenience).
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'kalaanba') THEN
        EXECUTE format('CREATE ROLE kalaanba LOGIN PASSWORD %L CREATEDB', :'app_password');
    ELSE
        EXECUTE format('ALTER ROLE kalaanba WITH LOGIN PASSWORD %L', :'app_password');
    END IF;
END
$$;

-- 2. Dev database.
SELECT 'CREATE DATABASE kalaanba_dev OWNER kalaanba ENCODING ''UTF8'' TEMPLATE template0'
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'kalaanba_dev')\gexec

-- 3. Test database (separate, used by Pest feature tests with RefreshDatabase).
SELECT 'CREATE DATABASE kalaanba_test OWNER kalaanba ENCODING ''UTF8'' TEMPLATE template0'
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'kalaanba_test')\gexec

-- 4. Per-engine schemas live INSIDE kalaanba_dev/kalaanba_test, owned by kalaanba.
--    Schemas themselves are created lazily by the EnsureSchemas console command
--    (kalaanba-api/app/Console/Commands/EnsureSchemasCommand.php, added in a later WP)
--    so this script does not create them here.

\echo 'kalaanba postgres bootstrap complete.'
