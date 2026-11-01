# Operational Runbook

## Ownership

Product Owner is the interim operational owner until a named Platform Ops Lead is appointed before pilot. The owner approves restore drills, backup-key custody, retention changes, and incident communications.

## Health and alerts

- External probe: `GET /api/v1/health` every 60 seconds; non-2xx or `status != ok` is an alert.
- Metrics: `GET /api/v1/ops/metrics` with `X-Metrics-Token`; scrape every 60 seconds.
- Alarm thresholds: backup older than 36 hours, queue oldest job over 15 minutes, outbox delivery without receipt over 15 minutes, any waiting database lock, private storage failure, or 5xx ratio above the agreed SLO.
- `php artisan ops:health-check` exits non-zero for the same thresholds and is suitable for cron or an external monitor.

## Backup

- Schedule `ops/backup.sh` daily outside the application host. Required secret: `BACKUP_ENCRYPTION_KEY`; keep it in a separate secret store from the application host.
- `ops/backup.sh` creates an encrypted PostgreSQL custom dump, encrypted private storage archive, SHA-256 checksums, and a manifest under `OPS_BACKUP_PATH`.
- Recommended retention is 35 days, following the approved T005 policy. Move copies to a separate host/account and verify checksums after transfer.
- PostgreSQL WAL/PITR: enable `archive_mode=on`, a durable `archive_command` to encrypted off-host storage, and retain WAL for the approved recovery window. Take a `pg_basebackup` at least weekly. The backup role must be separate from the application runtime role.
- Key recovery: store the encryption key and restore procedure separately from the database host. Test that the key can be retrieved without the application server. Never place the key in the repository or in the backup directory.

## Restore drill

If the host PostgreSQL client is older than the server, run `pg_dump`/`pg_restore` from a matching client container; do not treat a dump/restore that fails the major-version preflight as evidence. `ops/restore-drill.sh` checks this before writing evidence.

1. Provision an isolated PostgreSQL host and empty storage target.
2. Retrieve the base dump and required WAL segments; verify SHA-256 and key availability.
3. Decrypt the dump with the escrowed key, restore with `pg_restore --clean --if-exists`, and replay WAL to the chosen recovery point.
4. Restore private storage, apply the approved deletion list, and verify attachment checksums.
5. Run migrations check, application smoke tests, tenant isolation checks, and attachment/signed-URL checks.
6. Record RPO/RTO, failures, and follow-up owners. T050 owns the formal restore evidence; this runbook does not claim a successful restore.

## Incident flow

Detect → assess scope → contain → preserve evidence → restore service → verify money, goods, access, and storage → review root cause. Do not delete logs or backups to hide symptoms. Redact tokens, passwords, raw photos, addresses, and webhook bodies from logs and incident reports.

## Ownership of secrets

Application secrets, backup encryption keys, database credentials, S3 credentials, and metrics tokens have different owners and rotation procedures. Rotating a key requires a transition plan; never rotate the application encryption key without verifying old data remains readable.
