# Foundation deployment

Use the [README](../README.md) for local setup. The Vite server uses port 5173 and Laravel uses port 8000. Give the Laravel process its own PostgreSQL application credentials and a generated application key.

For a future HTTPS deployment, build the SPA with `npm ci` and `npm run build`. Serve `frontend/dist/` and configure the reverse proxy to send Laravel's API, Sanctum, login, registration, and logout routes to Laravel before the SPA fallback. Use `backend/public/` as Laravel's document root. Install the locked PHP dependencies with `composer install --no-dev --optimize-autoloader` and run `php artisan migrate --force` through the deployment workflow. Keep application storage and bootstrap cache writable by the application runtime.

Set `APP_ENV=production`, `APP_DEBUG=false`, the HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`, and the deployed hostname in `SANCTUM_STATEFUL_DOMAINS`. Use an empty `FRONTEND_ORIGINS` for same-origin production. Leave `VITE_API_URL` unset for a same-origin build. Secure environment values and database backups outside the repository.

Route `GET /email/verify/*` and `POST /forgot-password` and `POST /reset-password` to Laravel. GET requests for the two recovery pages go to the SPA. Configure `FRONTEND_URL` to the frontend's canonical HTTPS origin and `APP_URL` to the public Laravel origin. Use an SMTP transport and a supervised Laravel queue worker for encrypted password recovery jobs. Keep request bodies, signed verification links, and recovery URLs out of logs. Preserve the database session driver for account-wide revocation.

Use `GET /api/health` for liveness. Verify database access and SMTP delivery separately during deployment. Production readiness and activation of real-data workflows remain future work.
