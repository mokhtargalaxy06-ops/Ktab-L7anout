# Deployment runbook

## Topology and prerequisites

This reference deployment uses one Linux application host with Nginx, PHP 8.5 FPM, systemd, and a separate private PostgreSQL endpoint. Stage the same topology with a different DNS name, database, secrets, evidence volume, and SMTP sink. Install supported OS packages, PHP CLI/FPM with extensions required by the Composer lock including PDO PostgreSQL, Composer 2, a Node version supported by the frontend lock, PostgreSQL client utilities, ClamAV and current signatures. Provision DNS, a renewed TLS certificate, and the PostgreSQL CA. The supplied templates assume the Nginx worker runs as www-data and use the disposable example domain relief.example.org.

The React build uses a single HTTPS origin for the SPA, API and session endpoints. Pass an empty VITE_API_URL at build time to select the browser origin. All VITE_ variables are public at build time. Nginx sends /api, /sanctum, signed email verification and authentication POSTs to Laravel. Browser GETs for sign-in, registration, password recovery, and direct SPA links fall back to index.html. Missing hashed assets return 404. Only Laravel's public/index.php is executed by FPM. Static content has HTTPS, HSTS, CSP, framing, MIME and referrer controls. [Nginx routing](https://nginx.org/en/docs/http/ngx_http_core_module.html) · [Nginx FastCGI](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html)

## First host setup

Run these commands on the provisioned Linux host from a trusted checkout. Replace example hostnames, certificate paths, service paths if the distribution differs, and placeholders before installing configuration. Keep port 5432 private, FPM on its Unix socket, and ingress limited to approved HTTP/HTTPS and operations access. Put staging behind access controls compatible with auth and callback testing. Use synthetic records and prevent staging SMTP from reaching real addresses.

```sh
id mdr >/dev/null 2>&1 || sudo useradd --system --home-dir /srv/mdr --shell /usr/sbin/nologin mdr
sudo install -d -o root -g root -m 0755 /srv/mdr /srv/mdr/releases
sudo install -d -o root -g mdr -m 0750 /etc/mdr
sudo install -d -o mdr -g mdr -m 0751 /srv/mdr/shared
sudo install -d -o mdr -g mdr -m 0700 /srv/mdr/shared/evidence
sudo install -m 0640 -o root -g mdr deploy/backend.production.env.example /etc/mdr/backend.env
sudo install -m 0644 deploy/php-fpm/mdr.conf /etc/php/8.5/fpm/pool.d/mdr.conf
sudo install -m 0644 deploy/nginx/morocco-debt-relief.conf /etc/nginx/conf.d/mdr.conf
sudo install -m 0644 deploy/systemd/mdr-worker.service deploy/systemd/mdr-scheduler.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo php-fpm8.5 -t
sudo nginx -t
```

Install the trusted PostgreSQL CA at /etc/mdr/postgres-ca.pem. Edit /etc/mdr/backend.env from the approved secret manager. Supply the runtime database password, application key and SMTP credentials. Generate the application key once with openssl rand -base64 32, prefix the result with base64:, store it securely, and retain it across releases and recovery. The web process reads the protected environment file linked into each release. Use APP_DEBUG=false, the canonical HTTPS values for both application URLs, host-only session cookies, and the canonical hostname without scheme in SANCTUM_STATEFUL_DOMAINS. Keep FRONTEND_ORIGINS empty for this topology.

For staging set APP_ENV=staging and FUNDING_SIMULATOR_ENABLED=true. Use a staging-only secret of at least 32 characters for signed callback tests. The production template keeps the flag false, and the application rejects simulation in production even if the flag is accidentally enabled.

### PostgreSQL access

Provision separate mdr_schema and mdr_runtime logins in the designated database. Use a database administration session, set passwords through the provider's secret tooling or psql password prompts, and restrict access through private networking and TLS with hostname verification. The schema role owns the application database objects. The runtime role connects and receives schema usage and application table privileges only. Verify the role cannot create schema objects, truncate tables, or modify audit and journal rows.

```sql
CREATE ROLE mdr_schema LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE ROLE mdr_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE DATABASE morocco_debt_relief OWNER mdr_schema;
REVOKE ALL ON DATABASE morocco_debt_relief FROM PUBLIC;
GRANT CONNECT ON DATABASE morocco_debt_relief TO mdr_runtime;
```

Apply the supplied runtime grant matrix as the schema owner after migration. Keep role ownership, DDL and TRUNCATE outside the runtime role. Test registration, account revocation, queue and document processing, and the simulator using the staged runtime role. Review grants against the actual managed PostgreSQL environment before approving production.

## Build, migrate and activate a release

Use a clean checkout at the approved commit. Build in an isolated release directory. Run release commands on the host under a shell with set -e enabled. Record the prior release before changing current. Before migrating, enter the maintenance gate, drain requests, stop background services and take the pre-release checkpoint described in [backup and recovery](backup-and-recovery.md). On the first release, skip stopping units that have never run. Skip the pre-release checkpoint only after confirming the new database and evidence volume are empty. Provide mdr_schema credentials only to the migration process through the secret manager. Keep the runtime environment file linked throughout.

```sh
set -eu
commit=$(git rev-parse HEAD)
release="/srv/mdr/releases/$commit"
sudo install -d -o mdr -g mdr -m 0755 "$release"
git archive "$commit" | sudo -u mdr tar -x -C "$release"
sudo -u mdr ln -s /etc/mdr/backend.env "$release/backend/.env"
sudo -u mdr sh -c 'cd "$1/frontend" && npm ci && VITE_API_URL="" npm run build' sh "$release"
sudo -u mdr sh -c 'cd "$1/backend" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction && composer check-platform-reqs --no-dev' sh "$release"
sudo touch /srv/mdr/shared/edge-maintenance
sudo systemctl stop mdr-scheduler mdr-worker
```

After draining and taking the backup, run the next commands. Set MDR_SCHEMA_PASSWORD in the operator session from the secret manager immediately before the migration section. Use a controlled privileged shell to supply it without command-line arguments or shell tracing. Apply the reviewed runtime grant matrix after the schema command and before checking the runtime connection.

```sh
cd "$release/backend"
sudo -u mdr php8.5 artisan config:clear
: "${MDR_SCHEMA_PASSWORD:?Load the migration password from the secret manager}"
export DB_USERNAME=mdr_schema DB_PASSWORD="$MDR_SCHEMA_PASSWORD"
sudo --preserve-env=DB_USERNAME,DB_PASSWORD -u mdr php8.5 artisan migrate --force
# Run the next seed command on the initial installation only.
sudo --preserve-env=DB_USERNAME,DB_PASSWORD -u mdr php8.5 artisan db:seed --class=MoroccoGeographySeeder --force
# Configure mdr_schema as a protected libpq service for the same database.
psql 'service=mdr_schema' -v ON_ERROR_STOP=1 -f ../deploy/postgres/runtime-grants.sql
unset DB_USERNAME DB_PASSWORD MDR_SCHEMA_PASSWORD
sudo chown -hR root:root "$release"
sudo chown -R mdr:mdr "$release/backend/storage" "$release/backend/bootstrap/cache"
sudo chmod 0750 "$release/backend/storage" "$release/backend/bootstrap/cache"
sudo -u mdr sh -c 'umask 077; php8.5 artisan config:cache'
sudo -u mdr php8.5 artisan route:cache
sudo -u mdr php8.5 artisan migrate:status
sudo -u mdr php8.5 artisan ops:check
sudo ln -s "$release" /srv/mdr/current.next
sudo mv -Tf /srv/mdr/current.next /srv/mdr/current
sudo php-fpm8.5 -t
sudo nginx -t
sudo systemctl enable php8.5-fpm nginx mdr-worker mdr-scheduler
sudo systemctl restart php8.5-fpm nginx mdr-worker mdr-scheduler
sudo rm -f /srv/mdr/shared/edge-maintenance
```

After activation, run the smoke checks. For an initial deployment use them in staging before approving access for real users. Verify certificate renewal and Nginx reload monitoring separately.

```sh
curl -fsS https://relief.example.org/api/health
curl -fsSI https://relief.example.org/cases
curl -fsSI https://relief.example.org/login
curl -fsSI https://relief.example.org/sanctum/csrf-cookie
curl -sS -o /dev/null -w '%{http_code}\n' https://relief.example.org/storage/private
sudo systemctl is-active php8.5-fpm nginx mdr-worker mdr-scheduler
sudo -u mdr sh -c 'cd /srv/mdr/current/backend && php8.5 artisan schedule:list && php8.5 artisan ops:check'
```

Expect 404 for /storage/private. Test DNS and HTTPS, deep links, secure cookies and CSRF, sign-in, SMTP recovery delivery, private evidence upload and clean/quarantine behavior in staging. Observe queue activity, scanner outcomes and logs. The health URL is liveness. ops:check exercises operational database reads. Monitor the services and their job execution independently.

## Evidence and monitoring

Keep /srv/mdr/shared/evidence on a restricted, encrypted durable volume. Every download is a private attachment streamed through an authenticated policy check for that request. Access ends with session expiry or revocation, and FPM bounds request lifetime. Nginx has no private storage alias and Laravel's generic disk-serving route is disabled. Do not use storage:link for evidence. The scheduler runs both ClamAV scan queues when configured. Verify the virus definition update service, scanner execution and both queue schedules on staging.

| Signal | Collection and initial alert |
| --- | --- |
| TLS and HTTP | HTTPS synthetic checks, certificate expiry, 5xx rate or sustained latency from the edge log. |
| PHP and jobs | Ship journald, PHP-FPM and sanitized Nginx logs to restricted storage. Alert on service failure, repeated errors, failed jobs increasing, or jobs older than five minutes. Inspect artisan queue:failed before retrying individual job IDs. |
| PostgreSQL | Monitor connection saturation, replication lag, long or blocked transactions, disk headroom and WAL archive age. Establish thresholds with the database operator. |
| Reconciliation | Poll artisan ops:check as mdr. Alert immediately when funding_frozen is true or negative_receipt_balances is nonzero and investigate unresolved payouts and refunds. The finance CSVs and journal support operator review. |
| Evidence and backups | Alert on both quarantine-age metrics, stale scanner signatures, missed snapshots or logical backups, WAL archival failure, evidence backup mismatch and overdue restore exercises. |

Treat telemetry silence as an incident. Configure and exercise alert delivery and ownership before launch. The application emits signals. It does not install an external monitoring platform.

## Rollback

Close ingress using the same maintenance gate and stop worker and scheduler services. Check the migration history and confirm the previous code can run safely against the current schema. If compatible, point current back to the prior release and restart processes. The prior release retains its locked build and config. Rebuild its runtime cache if shared configuration changed. Restart and run the same smoke checks before considering the rollback complete.

```sh
set -eu
sudo touch /srv/mdr/shared/edge-maintenance
sudo systemctl stop mdr-scheduler mdr-worker
previous=/srv/mdr/releases/REPLACE_WITH_VERIFIED_PREVIOUS_COMMIT
test -f "$previous/backend/artisan"
test -f "$previous/frontend/dist/index.html"
sudo ln -s "$previous" /srv/mdr/current.next
sudo mv -Tf /srv/mdr/current.next /srv/mdr/current
sudo systemctl restart php8.5-fpm mdr-worker mdr-scheduler
sudo rm -f /srv/mdr/shared/edge-maintenance
```

For an incompatible migration or writes that depend on the new schema, keep ingress closed. Prefer a reviewed forward fix. Recover to an isolated target using the [recovery runbook](backup-and-recovery.md) if necessary. Reconcile database, evidence, outgoing operations and journal history before switching traffic. Never run a bulk migration rollback or overwrite a current financial database as a routine code rollback.

## Payment launch gate

Keep real donations disabled until the legal operating structure and privacy procedures are approved, the provider explicitly permits the intended charitable model, payment and refund workflows and failure recovery are tested, webhook authentication and replay protection are configured, and operator reconciliation and alerts are operational. Use staging simulation for rehearsals. Production finance also requires privileged MFA assurance and verified restricted database grants.

## Readiness checklist

- [ ] Approve the Linux host, DNS, TLS certificate and renewal, and PostgreSQL CA and secrets.
- [ ] Rehearse migration, permissions, immutable release activation, smoke checks and compatible rollback in staging.
- [ ] Verify real private storage, session revocation, evidence quarantine and malware scanning.
- [ ] Verify SMTP, queue and scheduler execution, operational alerts, and a coordinated recovery drill.
- [ ] Approve retention, backup recovery objectives, privileged MFA and PostgreSQL grants.
- [ ] Complete the payment launch gates and obtain explicit deployment authorization.
