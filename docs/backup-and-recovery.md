# Backup and recovery

Use managed PostgreSQL base backups and continuous WAL archiving for point-in-time recovery. Retain encrypted off-host copies in a separate access boundary. Keep a nightly logical backup for portability and a coordinated logical checkpoint before migrations. The logical dump is consistent for PostgreSQL. It does not capture filesystem objects, database roles, or encryption keys. Record the release commit, database identity, evidence snapshot ID, UTC time, key reference, and backup checksums together. [PostgreSQL backup and restore](https://www.postgresql.org/docs/current/backup.html) · [pg_dump](https://www.postgresql.org/docs/current/app-pgdump.html) · [pg_restore](https://www.postgresql.org/docs/current/app-pgrestore.html)

## Coordinated release checkpoint

Configure two libpq service entries via protected configuration. Use `mdr_backup` for a read-only source connection with access to all application data, and `mdr_restore` for a pre-created, empty, isolated recovery database owned by the recovery schema administrator. Use `sslmode=verify-full`. Load credentials from approved secrets and keep them out of commands and manifests. Use PostgreSQL client tools at least as recent as the source database.

Enter the Nginx maintenance gate and stop the worker and scheduler as described in the deployment runbook. Wait for in-flight writers and scanners to finish or stop them cleanly. Pause other operational writers before starting the checkpoint. Use an encrypted vault mount restricted to the backup operator. The backup below includes quarantined evidence. Never open it directly from the archive.

```sh
set -eu
umask 077
vault=/mnt/mdr-encrypted-vault
test -d "$vault"
stamp="$vault/$(date -u +%Y%m%dT%H%M%SZ)"
mkdir "$stamp"
pg_dump --dbname=service=mdr_backup --format=custom --file="$stamp/database.dump"
sudo tar -C /srv/mdr/shared -cf - evidence > "$stamp/evidence.tar"
readlink -f /srv/mdr/current > "$stamp/release.txt"
date -u > "$stamp/created-utc.txt"
pg_restore --list "$stamp/database.dump" > "$stamp/archive-contents.txt"
(cd "$stamp" && sha256sum database.dump evidence.tar release.txt created-utc.txt archive-contents.txt > SHA256SUMS)
```

Record the key and snapshot references without storing credentials in the manifest. Replicate the encrypted checkpoint off-host, verify integrity at the destination, and apply the approved retention policy. Do not resume ingress after a failed pre-release checkpoint. For ongoing operations pair database recovery points with versioned evidence backups and check file presence and digests against the target restore point. Keep the original application key in restricted recovery escrow for encrypted queued data.

## Restore drill

Restore to an isolated host or database with no route to production SMTP, payment systems or workers. Create the empty database and storage target first. Verify database identity before proceeding. Run the following only against the approved isolated target. Use tar and pg_restore only with trusted, verified archives.

```sh
set -eu
umask 077
checkpoint=/mnt/mdr-encrypted-vault/REPLACE_WITH_CHECKPOINT
restore_root=/srv/mdr/recovery
sudo install -d -o mdr -g mdr -m 0700 "$restore_root"
(cd "$checkpoint" && sha256sum -c SHA256SUMS)
psql 'service=mdr_restore' -v ON_ERROR_STOP=1 -c 'SELECT current_database(), current_user;'
pg_restore --dbname=service=mdr_restore --exit-on-error --single-transaction --no-owner --no-privileges "$checkpoint/database.dump"
target=$(sudo -u mdr mktemp -d "$restore_root/evidence-restore.XXXXXXXX")
sudo -u mdr tar -xf - -C "$target" --no-same-owner --no-same-permissions < "$checkpoint/evidence.tar"
printf '%s\n' "$target/evidence"
```

Apply the approved recovery role grants and set PRIVATE_STORAGE_ROOT to the extracted evidence directory. Use the recorded application release and key with isolated configuration. Check migration status and operations metrics, compare ledger counts and balances, verify evidence hashes and controlled downloads, and confirm audit protections. Reconcile payouts, refunds, and any operations after the recovery point. Review queue and scheduler state before restarting background processing. Force new sessions before opening recovered access, using the deployment operator's approved revocation procedure. A tested isolated restore and financial reconciliation are required before a production cutover.

## Monitoring

Alert on stale backups, checksum mismatch, WAL archival delay, restore failures, encrypted volume capacity, and evidence recovery errors. Establish approved recovery time and recovery point objectives and retention periods with the operator. Record observed recovery time, recovery point, and discrepancies on each drill. Rehearse regularly and before a risky migration.
