#!/bin/sh
set -eu

psql --username "$POSTGRES_USER" --dbname postgres --set ON_ERROR_STOP=1 \
    --set migration_password="$DB_MIGRATION_PASSWORD" \
    --set runtime_password="$DB_RUNTIME_PASSWORD" <<'SQL'
CREATE ROLE netclean_migrator LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS PASSWORD :'migration_password';
CREATE ROLE netclean_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS PASSWORD :'runtime_password';
CREATE DATABASE netclean OWNER netclean_migrator;
CREATE DATABASE netclean_test OWNER netclean_migrator;
REVOKE ALL ON DATABASE netclean FROM PUBLIC;
REVOKE ALL ON DATABASE netclean_test FROM PUBLIC;
GRANT CONNECT ON DATABASE netclean, netclean_test TO netclean_migrator, netclean_runtime;
SQL

for database in netclean netclean_test; do
    psql --username "$POSTGRES_USER" --dbname "$database" --set ON_ERROR_STOP=1 <<'SQL'
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO netclean_migrator;
GRANT USAGE ON SCHEMA public TO netclean_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE netclean_migrator IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO netclean_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE netclean_migrator IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO netclean_runtime;
SQL
done
