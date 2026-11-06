# Operations

## Scheduler

One cron entry runs everything (cPanel → Cron Jobs, every minute):

```
* * * * * cd /path/to/creative && php artisan schedule:run >> /dev/null 2>&1
```

| Job | When | What |
|---|---|---|
| `academy:expire-orders` | hourly | Expires unpaid orders after 24 h (bank transfers waiting for review are skipped) |
| `academy:prune-video-uploads` | hourly | Deletes half-finished video uploads from R2 |
| `academy:backup` | 02:30 daily | MySQL dump → `storage/app/backups/db-*.sql.gz`, keeps 14 |
| `academy:check-storage` | 06:00 daily | Notifies admins when video storage passes the alert % set in **Admin → Site settings** |

## Health check

`GET /up` returns 200 when the app boots, the database answers and storage is writable; otherwise 500.
Point an uptime monitor (UptimeRobot, Better Stack, cPanel) at `https://your-domain/up`.

## Logs

`LOG_STACK=daily` writes `storage/logs/laravel-YYYY-MM-DD.log`, kept `LOG_DAILY_DAYS` days (30).
Production must run with `APP_DEBUG=false` so errors never show stack traces to visitors.

## Backups

Backups on the same server don't survive losing the server. Copy `storage/app/backups` off-host daily,
for example with cPanel's backup to remote destination, or rclone to a separate private R2 bucket.

If `mysqldump` isn't on the PATH, set `MYSQLDUMP_PATH` in `.env`.

Run one by hand: `php artisan academy:backup`

## Restore (rehearse this before launch, then every few months)

1. Create an empty database (e.g. `creative_restore_test`), never restore over production first.
2. Load the dump:
   ```
   gunzip -c storage/app/backups/db-2026-11-06-023000.sql.gz | mysql -u USER -p creative_restore_test
   ```
3. Point a copy of the app at it (`DB_DATABASE=creative_restore_test`) and check: sign in, open a course,
   play a lesson, open **Admin → Orders & Refunds**.
4. Record the date and how long it took below. For a real disaster, repeat with the production database
   name after putting the site in maintenance mode (`php artisan down`, then `php artisan up`).

Videos live in R2 and payment screenshots in `storage/app/private/payment-proofs`; back up that folder too.

| Rehearsal date | Backup file | Duration | Result |
|---|---|---|---|
| | | | |
