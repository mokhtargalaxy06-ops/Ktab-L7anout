# Morocco Debt Relief

Morocco Debt Relief is a Moroccan food-solidarity initiative. The French and Arabic platform supports verified grocery debt cases, simulated donations, shop settlement, and public financial transparency.

## Prerequisites

- PHP 8.5 with the Laravel extensions and `pdo_pgsql` enabled. Enable `pdo_sqlite` for the default in-memory test configuration.
- Composer 2, Node.js 24 with npm, and a running PostgreSQL instance.
- On Windows, find the CLI configuration with `php --ini`, enable `extension=pdo_pgsql`, and confirm with `php -m`. Use `npm.cmd` in PowerShell if execution policy blocks the npm script shim.

## Database setup

Connect as a PostgreSQL administrator using `psql -h 127.0.0.1 -U postgres -d postgres`, then create a local application role and an owned database. The password command prompts privately.

```sql
CREATE ROLE morocco_app LOGIN;
\password morocco_app
CREATE DATABASE morocco_debt_relief OWNER morocco_app;
\q
```

## Start the backend

From the repository root in one terminal:

```powershell
cd backend
composer install
Copy-Item .env.example .env
```

Set `DB_PASSWORD` in `backend/.env` to the password chosen above. Adjust the other PostgreSQL values if your local setup differs. Generate the key and apply migrations:

```powershell
php artisan key:generate
php artisan migrate
php artisan db:seed --class=MoroccoGeographySeeder
php artisan serve --host=127.0.0.1 --port=8000
```

The liveness endpoint is `GET http://127.0.0.1:8000/api/health`. It returns `{"data":{"status":"ok"}}`. It checks the HTTP application process.

### Email and administrator setup

Run a local SMTP inbox such as Mailpit before testing registration, verification, and password reset. The example uses SMTP at `127.0.0.1:1025`. Mailpit normally exposes its inbox at `http://127.0.0.1:8025`. If upgrading an earlier checkout, set `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`, and `FRONTEND_URL=http://127.0.0.1:5173` in the existing backend environment. After applying the Phase 2 migrations, sign in again to obtain a versioned session.

Run `php artisan queue:work` from `backend/` in a separate terminal to deliver password reset emails.

Public registration offers donor, beneficiary, and shopkeeper accounts. Provision a new administrator only from a trusted backend console.

```powershell
php artisan accounts:create-administrator operator@example.test
```

Enter the name and password at the prompts. The password is read without echo. Complete email verification before using administrator functions. Use an address you control in a deployed environment.

### Shop evidence and geography

See the [official geography sources and coverage](docs/geography-data.md). Shop evidence enters private quarantine. To make clean evidence available for review, install ClamAV, update its signatures, set `SHOP_CLAMSCAN_BINARY` to the absolute path of `clamscan`, and run `php artisan shops:scan-documents` from `backend/`. Repeat the command as needed because it processes up to 100 files per run.

For beneficiary and shop evidence on debt applications, run `php artisan shops:scan-documents --cases`. A beneficiary needs scanned evidence before submitting an application. Reviewers route it to the shop and can publish only after the assigned shop owner confirms the amount and an administrator approves it.

The public discovery pages are `/cases` and `/urgent`. Reviewers can set an anonymized story and urgency grades from the administrator case dashboard. Configure the urgency weights, normalization caps, and threshold in `backend/config/discovery.php`.

### Simulated donations

Use a dedicated local PostgreSQL database and apply migrations. Set `FUNDING_SIMULATOR_ENABLED=true` in `backend/.env` while running with `APP_ENV=local`. Sign in as a verified donor to open `/donations/new`. The checkout uses simulated outcomes and collects no payment details. Use a random simulator secret of at least 32 characters for signed webhook testing.

Run `php artisan funding:process` to retry waiting receipts and confirm queued simulated refunds. For recurring processing, run `php artisan schedule:work` separately. Simulation remains disabled in production and its totals are labeled and separated from live reporting. Shop payout dispatch and real payment integration are outside Phase 6.

To run the two-process PostgreSQL concurrency test, create a separate disposable database whose name starts with `mdr_funding_concurrency_`, migrate it, and set `FUNDING_CONCURRENCY_DATABASE` to that name before running PHPUnit. It uses committed fixtures and retains them because the ledger protects financial history from deletion.

## Start the frontend

From the repository root in another terminal:

```powershell
cd frontend
npm.cmd ci
Copy-Item .env.example .env
npm.cmd run dev
```

Open [Morocco Debt Relief](http://127.0.0.1:5173). Use `127.0.0.1` consistently for both services so the development origins and Sanctum cookies work. On POSIX shells, use `cp` and `npm` in place of `Copy-Item` and `npm.cmd`.

## Verify

From `backend/` after generating the application key:

```powershell
php artisan test
php vendor/bin/pint --test
composer validate
```

The standard tests use in-memory SQLite. To run them with PostgreSQL, create a separate disposable database using `CREATE DATABASE morocco_debt_relief_test OWNER morocco_app;` and use:

```powershell
$env:DB_CONNECTION = 'pgsql'
$env:DB_DATABASE = 'morocco_debt_relief_test'
php artisan test
Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE
```

The test suite refreshes the target schema. Use a dedicated test database.

From `frontend/`:

```powershell
npm.cmd test
npm.cmd run lint
npm.cmd run build
```

## Project structure

- `backend/app/Http/` owns routing behavior, request validation, JSON resources, and request locale selection. `backend/app/Models/` and `backend/app/Services/` own account records and transactional registration.
- `backend/config/`, `backend/routes/`, `backend/lang/`, and `backend/database/` hold service configuration, routes, response translations, factories, and migrations.
- `frontend/src/app/` contains routing, layout, and locale catalogs. `frontend/src/features/` contains the homepage, account flows, and not-found route.
- `frontend/src/shared/` contains Axios and reusable UI primitives. The homepage photograph is generated illustration.
- `backend/tests/` and `frontend/src/app/App.test.tsx` cover the foundation. The lockfiles pin dependencies.

See the [architecture](docs/architecture.md), [API contract](docs/api-documentation.md), and [deployment notes](docs/deployment.md).

### Simulated shop settlements

With the Phase 6 simulator enabled and migrations applied, fully fund a published case and open the administrator dashboard. Prepare the settlement, then use a different verified administrator to approve and submit it. Record the simulated transfer, confirm it as the assigned shopkeeper, then reconcile it as an administrator who did not initiate it. Use the administrator dashboard to inspect returns, journal events, and reconciliation shortages, or export the two CSV reports. Open the public `/transparency` page to view aggregate simulation results.

### Administrator workspace

Verified administrators can open `/dashboard/administrator` for overview, cases, users, shops, finances, regional activity, and audit history. Apply pending Laravel migrations before opening the workspace. Financial search, manual returns, and settlement controls use the local simulator.
