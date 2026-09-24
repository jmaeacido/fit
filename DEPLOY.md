# FIT deployment

Production target: https://fit.kaila-app.com

- Server: `kaila-oracle` (`152.69.222.206`), accessed through the existing SSH configuration.
- Application: `/var/www/fit`; nginx serves only `/var/www/fit/public` using PHP 8.3 FPM.
- Database: `/var/www/fit/database/database.sqlite`.
- Production configuration: `/var/www/fit/.env`; debug disabled, secure session cookies enabled.
- nginx configuration: `/etc/nginx/sites-available/fit.kaila-app.com`.
- DNS: Spaceship A record, host `fit`, value `152.69.222.206`.

## Updating

Run `php artisan test` and `npm run build` locally first. Upload the application and `public/build` to the server, excluding `.env`, `.git`, `node_modules`, `vendor`, the SQLite database (including WAL/SHM files), runtime storage, bootstrap caches, and `public/hot`. Preserve production data and configuration. Back up the production database before applying migrations.

From `/var/www/fit`, run:

```sh
composer install --no-dev --optimize-autoloader --no-interaction
php8.3 artisan migrate --force
php8.3 artisan optimize
```

Keep `storage`, `bootstrap/cache`, and the database writable by `www-data`. The environment file must remain private. Verify `/up`, `/`, `/admin`, `/login`, and the built assets over HTTPS after deployment.

The selections dashboard at `/admin` is public for viewing and CSV export. Editing any submission requires an administrator session. Create an administrator interactively with `php8.3 artisan admin:create`. Do not put passwords in commands, documentation, or source control.