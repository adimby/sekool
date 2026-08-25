#!/bin/sh
# Le superuser Docker (postgres) contourne TOUJOURS le RLS, même avec FORCE.
# L'application et les tests doivent se connecter en `fanabe` (NOSUPERUSER, NOBYPASSRLS).
set -eu

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'SQL'
CREATE ROLE fanabe LOGIN PASSWORD 'fanabe' NOSUPERUSER NOCREATEDB NOCREATEROLE INHERIT NOBYPASSRLS;
CREATE DATABASE fanabe OWNER fanabe;
CREATE DATABASE fanabe_test OWNER fanabe;
SQL

for db in fanabe fanabe_test; do
  psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$db" <<'SQL'
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
GRANT ALL ON SCHEMA public TO fanabe;
ALTER SCHEMA public OWNER TO fanabe;
SQL
done
