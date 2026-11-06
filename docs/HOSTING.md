# Hosting on Yegara cPanel — creativelabet.com

How it works: every push to `main` runs the tests on GitHub. When they pass, GitHub builds a ready-to-run copy
(PHP libraries and compiled CSS/JS included) and pushes it to the **`deploy`** branch. The server only pulls that
branch, so it needs no Composer or Node. Updating later is one command (step 12).

Do the steps in order. Each says what you should see; stop and ask if you see something else.

## 1. PHP version and extensions
cPanel → **Select PHP Version** (or MultiPHP Manager) → set **PHP 8.4** for creativelabet.com.
Under Extensions make sure these are ticked: `bcmath`, `ctype`, `curl`, `fileinfo`, `intl`, `mbstring`,
`openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd`.
Check in **Terminal**: `php -v` → shows `PHP 8.4.x`.

## 2. Database
cPanel → **MySQL Databases**:
1. Create database `creative` (cPanel names it `<cpaneluser>_creative`).
2. Create user `creative` with a generated strong password (save it in your password manager).
3. Add the user to the database with **ALL PRIVILEGES**.

## 3. Email account for sending
cPanel → **Email Accounts** → Create `noreply@creativelabet.com` with a strong password.
Then open **Connect Devices** for that account and note the **Outgoing Server** and **SMTP Port** (usually
`mail.creativelabet.com`, port `465`, SSL).
Also in cPanel → **Email Deliverability**: make sure SPF and DKIM show as valid for creativelabet.com
(click *Repair* if not) — otherwise password-reset emails land in spam.

## 4. Get the app
In **Terminal**:
```
cd ~
git clone --branch deploy --depth 1 https://github.com/AEdward/creative.git creative
cd creative && cat RELEASE
```
You should see a short commit id and date.

## 5. Point the website at the app's `public` folder
The site must serve `~/creative/public`, never `~/creative` (that would expose `.env`).
```
cd ~
mv public_html public_html.old
ln -s ~/creative/public public_html
ls -la ~ | grep public_html
```
You should see `public_html -> /home/<cpaneluser>/creative/public`.
(If Yegara blocks the symlink, ask their support to set the document root of creativelabet.com to
`creative/public`.)

## 6. Settings file
```
cd ~/creative
cp .env.production.example .env
nano .env
```
Fill in every `CHANGE`: the database name/user/password from step 2 and the email password from step 3.
Save (Ctrl+O, Enter, Ctrl+X). Then:
```
php artisan key:generate --force
chmod 600 .env
chmod -R 775 storage bootstrap/cache
```

## 7. Create the tables and caches
```
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan db:seed --class=CategorySeeder --force
```
Migrations should all say `DONE`.

## 8. Your admin account
```
php artisan academy:admin you@example.com --name="Your Name"
```
Use a real email you can read. It asks for a password (at least 12 characters), which isn't shown as you type.

## 9. HTTPS
cPanel → **SSL/TLS Status** → run **AutoSSL** for creativelabet.com and www.creativelabet.com.
Open https://creativelabet.com — the home page should load with a padlock.

## 10. Cron jobs (scheduled tasks + email queue)
cPanel → **Cron Jobs** → add both, each "Once Per Minute" (`* * * * *`):
```
cd ~/creative && php artisan schedule:run >> /dev/null 2>&1
cd ~/creative && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```
After a few minutes, **Admin → Dashboard** should show no "Scheduled jobs have never run" warning.

## 11. Test email
```
php artisan tinker --execute="Illuminate\Support\Facades\Mail::raw('Creative Lab test', fn (\$m) => \$m->to('YOUR@EMAIL')->subject('Test'));"
```
The email should arrive within a minute. Then try **Forgot password** on the site with a real account.

## 12. Updating later
Push to `main` on GitHub → wait until the "Release bundle" action is green → on the server:
```
cd ~/creative && bash scripts/server-update.sh
```
It takes a database backup, pulls the release, migrates and refreshes caches.

## After go-live
- **Admin → Site settings**: contact details, legal pages, VAT (leave off until your accountant confirms).
- **Admin → Bank accounts**: add your real bank / telebirr accounts and logos.
- Copy `~/creative/storage/app/backups` off the server regularly (see docs/OPERATIONS.md).
- Uptime monitor on `https://creativelabet.com/up`.
