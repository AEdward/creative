# Creative Lab Academy

A Laravel monolith for the academy described in [the build specification](docs/BUILD_PLAN.md). The runtime/application foundation is installed; the branded public site, authentication, course management, learning, and payments are upcoming work in [TODO.md](TODO.md).

## Stack

PHP 8.4, Laravel 13, Livewire 4 (including Alpine), Filament 5, Tailwind 4, Vite 8, MySQL 8.4, and Node 24. Exact PHP/JS dependencies are committed in their lockfiles. Filament is installed as a dependency; no admin panel, role system, or admin account exists yet. The homepage is still the framework welcome page.

## Standard installation

With the required PHP extensions, Composer, Node, and a private local MySQL database available:

```sh
composer install --no-interaction --prefer-dist
cp .env.example .env
# Configure your local database credentials securely in .env.
php artisan key:generate
php artisan migrate
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

MySQL and the web process use a private Docker network with no published host ports. Generated local credentials are outside the checkout and in ignored local .env configuration; never print or commit them. Files/dependencies are retained separately from processes, which must be restarted in new tasks. No public preview is configured.

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
/workspace/setup/creative/php vendor/bin/pint --test
/workspace/setup/creative/php composer validate --strict
npm run build
```

With native PHP, `php artisan test` uses phpunit.xml defaults unless you export database overrides. CI uses a dedicated MySQL `creative_test` database. Baseline coverage checks homepage/health responses and user persistence/password hashing; the scaffold also includes its example unit test. This does not validate future LMS workflows. The GitHub CI workflow is added but its hosted run must be checked separately.

See [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md) for executed checks and [DECISIONS.md](DECISIONS.md) for setup choices.
