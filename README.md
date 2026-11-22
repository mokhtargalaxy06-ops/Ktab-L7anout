# Morocco Debt Relief

Morocco Debt Relief is a Moroccan food-solidarity initiative. The foundation provides a French and Arabic website, account registration, and session-based sign-in. Donation and payment processing are outside this phase.

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
