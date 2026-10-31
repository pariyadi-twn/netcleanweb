#!/usr/bin/env bash
set -euo pipefail

: "${BACKUP_ENCRYPTION_KEY:?BACKUP_ENCRYPTION_KEY is required}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-netclean}"
DB_USERNAME="${BACKUP_DB_USERNAME:-${DB_MIGRATION_USERNAME:-${DB_USERNAME:-netclean_migrator}}}"
DB_PASSWORD="${BACKUP_DB_PASSWORD:-${DB_MIGRATION_PASSWORD:-${DB_PASSWORD:-}}}"
BACKUP_DIR="${OPS_BACKUP_PATH:-$(pwd)/storage/app/backups}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
WORK_DIR="${BACKUP_DIR}/${STAMP}"

umask 077
mkdir -p "${WORK_DIR}"
export PGPASSWORD="${DB_PASSWORD}"

pg_dump \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --username="${DB_USERNAME}" \
  --dbname="${DB_DATABASE}" \
  --format=custom \
  --no-owner \
  --no-privileges \
  | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_ENCRYPTION_KEY \
  > "${WORK_DIR}/database.dump.enc"

if [ -d storage/app/private ]; then
  tar -C storage/app/private -czf - . \
    | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_ENCRYPTION_KEY \
    > "${WORK_DIR}/storage.tar.gz.enc"
fi

(
  cd "${WORK_DIR}"
  sha256sum ./*.enc > SHA256SUMS
)

printf '{"created_at":"%s","database":"%s","host":"%s"}\n' "${STAMP}" "${DB_DATABASE}" "${DB_HOST}" > "${WORK_DIR}/manifest.json"
chmod -R go-rwx "${WORK_DIR}"
echo "${WORK_DIR}"
