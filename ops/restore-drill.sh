#!/usr/bin/env bash
set -euo pipefail

SOURCE_HOST="${RESTORE_SOURCE_HOST:-${DB_HOST:-127.0.0.1}}"
SOURCE_PORT="${RESTORE_SOURCE_PORT:-${DB_PORT:-5432}}"
SOURCE_DB="${RESTORE_SOURCE_DATABASE:-${DB_DATABASE:-netclean}}"
SOURCE_USER="${RESTORE_SOURCE_USERNAME:-${DB_MIGRATION_USERNAME:-${DB_USERNAME:-netclean_migrator}}}"
SOURCE_PASSWORD="${RESTORE_SOURCE_PASSWORD:-${DB_MIGRATION_PASSWORD:-${DB_PASSWORD:-}}}"

ADMIN_HOST="${RESTORE_ADMIN_HOST:-${SOURCE_HOST}}"
ADMIN_PORT="${RESTORE_ADMIN_PORT:-${SOURCE_PORT}}"
ADMIN_USER="${RESTORE_ADMIN_USER:-postgres}"
ADMIN_PASSWORD="${RESTORE_ADMIN_PASSWORD:-${SOURCE_PASSWORD}}"

STAMP="$(date -u +%Y%m%d%H%M%S)"
TARGET_DB="${RESTORE_TARGET_DATABASE:-netclean_restore_drill_${STAMP}}"
TARGET_DB="$(printf '%s' "${TARGET_DB}" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9_' '_')"
WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/netclean-restore-drill.XXXXXX")"
DUMP_FILE="${WORK_DIR}/database.dump"
EVIDENCE_FILE="${RESTORE_EVIDENCE_FILE:-${WORK_DIR}/evidence.json}"
KEEP_TARGET="${RESTORE_KEEP_TARGET:-0}"

cleanup() {
  rm -rf "${WORK_DIR}"
  if [ "${KEEP_TARGET}" != "1" ]; then
    PGPASSWORD="${ADMIN_PASSWORD}" psql -h "${ADMIN_HOST}" -p "${ADMIN_PORT}" -U "${ADMIN_USER}" -d postgres -v ON_ERROR_STOP=0 -c "DROP DATABASE IF EXISTS \"${TARGET_DB}\"" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

if [ -z "${SOURCE_PASSWORD}" ]; then
  echo "SOURCE_PASSWORD is required" >&2
  exit 1
fi
if [ -z "${ADMIN_PASSWORD}" ]; then
  echo "RESTORE_ADMIN_PASSWORD or SOURCE_PASSWORD is required" >&2
  exit 1
fi

export PGPASSWORD="${SOURCE_PASSWORD}"
server_major="$(psql --host="${SOURCE_HOST}" --port="${SOURCE_PORT}" --username="${SOURCE_USER}" --dbname="${SOURCE_DB}" -tAc "SELECT current_setting('server_version_num')::int / 10000")"
dump_major="$(pg_dump --version | sed -E 's/^pg_dump \(PostgreSQL\) ([0-9]+).*/\1/')"
if [ -z "${server_major}" ] || [ "${dump_major}" -lt "${server_major}" ]; then
  echo "pg_dump major (${dump_major}) must be >= PostgreSQL server major (${server_major})." >&2
  exit 1
fi
dump_start="$(date +%s)"
pg_dump --host="${SOURCE_HOST}" --port="${SOURCE_PORT}" --username="${SOURCE_USER}" --dbname="${SOURCE_DB}" --format=custom --no-owner --no-privileges --file="${DUMP_FILE}"
dump_seconds="$(( $(date +%s) - dump_start ))"

export PGPASSWORD="${ADMIN_PASSWORD}"
psql --host="${ADMIN_HOST}" --port="${ADMIN_PORT}" --username="${ADMIN_USER}" --dbname=postgres -v ON_ERROR_STOP=1 -c "CREATE DATABASE \"${TARGET_DB}\"" >/dev/null

restore_start="$(date +%s)"
pg_restore --host="${ADMIN_HOST}" --port="${ADMIN_PORT}" --username="${ADMIN_USER}" --dbname="${TARGET_DB}" --no-owner --no-privileges --exit-on-error "${DUMP_FILE}"
restore_seconds="$(( $(date +%s) - restore_start ))"

counts_source="$(PGPASSWORD="${SOURCE_PASSWORD}" psql --host="${SOURCE_HOST}" --port="${SOURCE_PORT}" --username="${SOURCE_USER}" --dbname="${SOURCE_DB}" -tAc "SELECT json_build_object('tenants',(SELECT count(*) FROM tenants),'branches',(SELECT count(*) FROM branches),'orders',(SELECT count(*) FROM orders),'invoices',(SELECT count(*) FROM invoices),'payments',(SELECT count(*) FROM payments),'cash_entries',(SELECT count(*) FROM cash_entries),'refunds',(SELECT count(*) FROM refunds),'production_tasks',(SELECT count(*) FROM production_tasks),'notification_deliveries',(SELECT count(*) FROM notification_deliveries))")"
counts_target="$(PGPASSWORD="${ADMIN_PASSWORD}" psql --host="${ADMIN_HOST}" --port="${ADMIN_PORT}" --username="${ADMIN_USER}" --dbname="${TARGET_DB}" -tAc "SELECT json_build_object('tenants',(SELECT count(*) FROM tenants),'branches',(SELECT count(*) FROM branches),'orders',(SELECT count(*) FROM orders),'invoices',(SELECT count(*) FROM invoices),'payments',(SELECT count(*) FROM payments),'cash_entries',(SELECT count(*) FROM cash_entries),'refunds',(SELECT count(*) FROM refunds),'production_tasks',(SELECT count(*) FROM production_tasks),'notification_deliveries',(SELECT count(*) FROM notification_deliveries))")"

if [ "${counts_source}" != "${counts_target}" ]; then
  echo "Restore row counts differ: source=${counts_source} target=${counts_target}" >&2
  exit 1
fi

storage_status="skipped"
storage_files=0
SOURCE_STORAGE="${RESTORE_STORAGE_SOURCE:-storage/app/private}"
TARGET_STORAGE="${RESTORE_STORAGE_TARGET:-${WORK_DIR}/storage}"
if [ -d "${SOURCE_STORAGE}" ]; then
  mkdir -p "${TARGET_STORAGE}"
  ( cd "${SOURCE_STORAGE}" && find . -type f -print0 | sort -z | xargs -0 -r sha256sum ) > "${WORK_DIR}/storage.sha256"
  ( cd "${SOURCE_STORAGE}" && tar -cf - . ) | ( cd "${TARGET_STORAGE}" && tar -xf - )
  ( cd "${TARGET_STORAGE}" && sha256sum -c "${WORK_DIR}/storage.sha256" >/dev/null )
  storage_files="$(find "${SOURCE_STORAGE}" -type f | wc -l | tr -d ' ')"
  storage_status="passed"
fi

# Apply the durable deletion tombstones against the restored copy using the application path.
tombstones_reapplied=0
if [ "${RESTORE_APPLY_TOMBSTONES:-1}" = "1" ]; then
  tombstone_count="$(PGPASSWORD="${ADMIN_PASSWORD}" psql --host="${ADMIN_HOST}" --port="${ADMIN_PORT}" --username="${ADMIN_USER}" --dbname="${TARGET_DB}" -tAc "SELECT count(*) FROM data_deletion_tombstones WHERE subject_type = 'customer'" 2>/dev/null || echo 0)"
  if [ "${tombstone_count:-0}" != "0" ]; then
    PGPASSWORD="${ADMIN_PASSWORD}" psql --host="${ADMIN_HOST}" --port="${ADMIN_PORT}" --username="${ADMIN_USER}" --dbname="${TARGET_DB}" -v ON_ERROR_STOP=1 -c "UPDATE customers SET name = 'Restored PII' WHERE public_id IN (SELECT subject_public_id FROM data_deletion_tombstones WHERE subject_type = 'customer')" >/dev/null
  fi
  DB_HOST="${ADMIN_HOST}" DB_PORT="${ADMIN_PORT}" DB_DATABASE="${TARGET_DB}" DB_USERNAME="${ADMIN_USER}" DB_PASSWORD="${ADMIN_PASSWORD}" \
    php artisan data:reapply-deletions >/dev/null
  tombstones_reapplied="${tombstone_count:-0}"
  if [ "${tombstones_reapplied}" != "0" ]; then
    remaining="$(PGPASSWORD="${ADMIN_PASSWORD}" psql --host="${ADMIN_HOST}" --port="${ADMIN_PORT}" --username="${ADMIN_USER}" --dbname="${TARGET_DB}" -tAc "SELECT count(*) FROM customers c JOIN data_deletion_tombstones t ON t.subject_public_id = c.public_id AND t.subject_type = 'customer' WHERE c.name <> 'Deleted Customer'")"
    if [ "${remaining}" != "0" ]; then
      echo "Tombstones were not fully reapplied: ${remaining}" >&2
      exit 1
    fi
  fi
fi

printf '{"status":"passed","source_database":"%s","restore_database":"%s","dump_seconds":%s,"restore_seconds":%s,"source_counts":%s,"target_counts":%s,"tombstones_reapplied":%s,"storage_status":"%s","storage_files":%s,"rpo_note":"RPO is measured by backup cadence, not by this dump duration","generated_at":"%s"}\n' \
  "${SOURCE_DB}" "${TARGET_DB}" "${dump_seconds}" "${restore_seconds}" "${counts_source}" "${counts_target}" "${tombstones_reapplied}" "${storage_status}" "${storage_files}" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "${EVIDENCE_FILE}"

cat "${EVIDENCE_FILE}"
if [ -n "${RESTORE_EVIDENCE_FILE:-}" ]; then
  cp "${EVIDENCE_FILE}" "${RESTORE_EVIDENCE_FILE}"
fi
