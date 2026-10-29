# Creative Lab Academy

A Laravel monolith for the academy described in [the build specification](docs/BUILD_PLAN.md). Available now: branded public pages and a searchable catalog; registration/login, verification and password reset; student/instructor/admin boundaries; a Livewire course editor with text lessons and administrator publication; free enrollment, private learning notes/bookmarks, explicit completion and progress; profile/password settings, wishlist, contact inbox, and basic Filament management with audited role/status changes.

Real video uploads/playback, paid checkout, advanced assessments/certificates, and production deployment remain unfinished. Do not treat this development build as a launched service. See [TODO.md](TODO.md).

## Stack

PHP 8.4, Laravel 13, Livewire 4 (including Alpine), Filament 5, Tailwind 4, Vite 8, MySQL 8.4, and Node 24. Exact PHP/JS dependencies are committed in their lockfiles. Filament provides the restricted admin panel. No administrator credentials are seeded or hardcoded.

## Standard installation

With the required PHP extensions, Composer, Node, and a private local MySQL database available:

```sh
composer install --no-interaction --prefer-dist
cp .env.example .env
# Configure your local database credentials securely in .env.
php artisan key:generate
php artisan migrate
php artisan db:seed --class=CategorySeeder
npm ci --ignore-scripts
npm run build
php artisan serve
```

Never overwrite an existing .env or regenerate an existing application key during routine startup. Use a separate test database for integration tests. Production deployment, real payments, SMTP, and R2 are not configured. The payment activation flag defaults to false and does not itself implement a gateway.

## This managed cloud environment

Use the existing isolated checkout; do not create a Git worktree unless explicitly requested. The host lacks native PHP and installation privileges, so development-only tooling lives in `/workspace/setup/creative`. Docker is not a production deployment requirement.

```sh
/workspace/setup/creative/start-db
/workspace/setup/creative/php php artisan migrate --no-interaction
/workspace/setup/creative/start-web
```

MySQL uses a private Docker network without a published database port. The development web process exposes only a local loopback port for internal browser checks. Generated local credentials are outside the checkout and in ignored local .env configuration; never print or commit them. Files/dependencies are retained separately from processes, which must be restarted in new tasks. No public preview is configured.

### Dependency refresh under restricted networking

Composer metadata is available but the GitHub API archive host is blocked. The following helper downloads the locked immutable releases from GitHub's allowed official archive host into Composer's cache, preserving TLS verification and any declared checksums. It does not change lockfile URLs or disable verification.

```sh
python3 /workspace/setup/creative/cache-packages.py
/workspace/setup/creative/php composer install --no-interaction --prefer-dist
npm ci --ignore-scripts
npm run build
```

The helper is retained cloud setup tooling, not a prerequisite on normal developer machines. The current build uses system fonts and does not require an external font service.

## Verification

The cloud wrapper injects development MySQL settings. Remove those selectors when running the default PHPUnit SQLite-in-memory configuration so tests cannot reset the development database:

```sh
/workspace/setup/creative/php env -u DB_CONNECTION -u DB_DATABASE -u DB_USERNAME -u DB_PASSWORD -u DB_HOST php artisan test
/workspace/setup/creative/php vendor/bin/pint --dirty --format agent
/workspace/setup/creative/php composer validate --strict
npm run build
```

With native PHP, `php artisan test` uses phpunit.xml defaults unless you export database overrides. CI uses a dedicated MySQL `creative_test` database. Feature coverage includes catalog visibility/filtering, native authentication, role/ownership boundaries, admin operations, course editing/publication, enrollment, learning progress, profile settings, contact validation, wishlists, and private notes. Media authorization, upload validation and SDK signing are tested locally. Real R2 behavior, live payments, and deployment are not verified. The scaffold also retains its example unit test. The GitHub CI workflow is added but its hosted run must be checked separately.

See [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md) for executed checks and [DECISIONS.md](DECISIONS.md) for setup choices.

## Administrator bootstrap

From a trusted interactive terminal, run `php artisan academy:admin you@example.com --name="Your name"`. It securely prompts for a password, creates a verified administrator, and refuses to promote or overwrite an existing account. In this cloud runtime, prefix the command with `CREATIVE_INTERACTIVE=1 /workspace/setup/creative/php`. Never put a password in command arguments, files committed to Git, or chat. Administrators can approve instructors from the Users resource; role/status changes are audited.

Default seeding creates only the five creative categories and is repeatable. It never creates demo students, instructors, paid transactions, or courses. Instructor edits return published courses to draft for review. Publication requires a complete curriculum. Video lessons require verified assets and configured private storage; unattached videos prevent publication.

Mail is logged locally until a verified SMTP service is configured. Legal pages remain review placeholders. Real payment processing stays off and has no enabled provider.

## Private video storage (integration prepared; real R2 unverified)

The R2 disk uses the official S3 adapter and the R2_* settings in .env.example. Configure a private bucket and account HTTPS endpoint securely on the selected host. Never share secret keys in chat or commit them. Uploads and playback remain unavailable when configuration is incomplete.

Instructors can upload an MP4 directly to storage, see progress/cancel, confirm it, and attach it to a lesson. The server verifies byte count, MIME metadata, and MP4 header before and after copying to a separate private final key. This is container validation, not malware scanning or codec transcoding. The declared 1 GB application limit is checked at request/confirmation; the installed SDK does not bind PUT signatures to size or MIME, so it is not an upload-time storage quota. Restrict instructor access and configure provider storage limits, alerts and cleanup before enabling uploads.

PUT URLs last 15 minutes; confirmation has a further two-hour grace for transfers started before expiry. Playback URLs last five minutes. The player supports manual refresh after expiry and periodic resume saving. Page views never mark lessons complete; the completion button records a learner's explicit action rather than certifying viewing time. Signed links can be shared until expiry; this is access control, not DRM.

Run `php artisan academy:prune-video-uploads` from a trusted scheduled job after configuring storage. It removes staging objects after the confirmation window, expires abandoned uploads, and removes ready objects left unattached for over 24 hours. It preserves all attached final assets, including hidden lessons, and is retryable after failures. Schedule hourly and configure a provider lifecycle rule for the `uploads/` prefix as a cleanup backstop; confirm retention and transfer behavior on staging. Cleanup is not scheduled or verified against a live bucket here. Changing buckets requires migrating stored object keys/assets deliberately.

Before activation, verify browser CORS for the exact app origin and signed request headers, PUT completion, copy/HEAD/range permissions, GET range seeking, URL expiry/refresh, codec support, cleanup permissions and quota alarms on real R2. Captions, transcoding, downloadable lesson resources and richer media administration remain pending.

## Commerce foundation (real checkout disabled)

Orders snapshot integer minor-unit prices. Payment adapters must independently verify provider evidence before payment processing can grant enrollment. The domain handles duplicate events, mismatched amounts/references, terminal conflicts and audited fulfillment. Account-row locking serializes checkout eligibility with fulfillment. Reusing the original checkout key resumes its order; a different key is rejected while an order is pending.

The default gateway is unavailable even if PAYMENTS_ENABLED is set. The fake gateway exists only for local/test fixtures, implements no vendor contract and is rejected in production. There is no public payment callback endpoint. `/payments` shows only the authenticated student's own records; the admin ledger is read-only. Provider documentation, signatures, merchant verification, sandbox reconciliation, tax/refund policy and authorization are prerequisites for real checkout.
