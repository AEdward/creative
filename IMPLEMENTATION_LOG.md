# Implementation log

## 2026-10-29 — project environment preparation

- Confirmed the selected GitHub repository has no refs or commits; local branch is unborn and the checkout was empty.
- Read the supplied build specification and retained it in `docs/BUILD_PLAN.md`.
- Verified current package compatibility using Composer package metadata.
- Native PHP, Composer, and MySQL are absent. Signed Debian package installation on the host is blocked by filesystem permissions. Built development-only PHP/Composer tooling through the managed Docker daemon instead.
- Verified PHP 8.4.27 and pdo_mysql, pdo_sqlite, mbstring, intl, dom, xml, zip, curl, and bcmath.
- Started MySQL 8.4.13 privately and verified PHP database connectivity, utf8mb4, InnoDB table creation, and transaction rollback.
- Laravel skeleton download failed with an HTTP 403 from the proxy for `api.github.com`; saved that precise domain addition in the environment draft. Runtime policy still lacks the domain.
- Application installation, migrations, tests, frontend build, and HTTP checks are unrun. Phase 0 is incomplete. Phase 1 has not started.
- No application source, test suite, dependency manifest, or lockfile existed or was changed. No deployment or Git commits were made.

## 2026-10-29 — application foundation installed

- Pushed the original checklist/documentation to GitHub main at c4f4af0.
- Tested source installation; configured Git to use the mounted system CA bundle. Full repository history was costly, so installed the exact official Laravel skeleton release archive from the permitted GitHub codeload endpoint instead.
- Resolved Laravel 13.39.0, Livewire 4.4.8, Filament 5.11.0, and Laravel Boost 2.10.3; retained composer.lock and package-lock.json. Cached 150 immutable official package archives without disabling TLS, changing lockfile URLs, or ignoring declared checksums.
- Added safe local environment configuration, MySQL defaults, a disabled payment flag, and GitHub CI. The upstream scaffold required Laravel Boost; installed it and retained generated guidance.
- Removed the build-time external font download and used system-font fallbacks. Final visual design is not implemented.
- Ran the three framework migrations on MySQL successfully (users/sessions, cache, and jobs). Corrected ignored local database settings so the web server uses the private MySQL service.
- Baseline suite: 4 tests passed, 5 assertions. Includes the upstream trivial unit example plus homepage, health, and database user-persistence/password-hashing checks. Local feature tests use SQLite memory; a dedicated MySQL test database is configured in CI, whose hosted run is not yet verified.
- PHP formatting passed. Composer manifest validation and platform requirements passed. Composer dependency resolution reported no vulnerability advisories.
- Frozen npm installation and Vite production asset build passed. Real GET / and /up returned HTTP 200 with MySQL-backed sessions.
- Executed the reusable installation script again successfully; restarted the project web container and verified both HTTP responses again.
- Saved updated install/start configuration and removed the now-unnecessary draft api.github.com allowance. Configuration is saved, not published; fresh-task snapshot restoration and a clean-machine install are not yet verified.
- Filament is a dependency only; there is no admin panel or role system yet. Public branding, courses, authentication, R2, payments, and the remaining LMS are pending.

## 2026-10-29 — public catalog and account foundations

- Implemented the reference-led public homepage, five creative-path links, live course catalog/detail pages, About/FAQ, legal-review placeholders, and a contact-channel placeholder. No fabricated courses, ratings, or student counts were seeded.
- Added category/course/module/lesson schema, factories, publication scoping, filters, search, sorting, pagination, escaped descriptions, and published curriculum visibility.
- Added native session registration/login/logout, verification and password reset with throttling; canonical lowercase registration emails; student-only self-registration; active-account checks; instructor gate and ownership policy; restricted Filament admin shell.
- Tests grew from 4 to 27 (116 assertions). All passed after correcting the original homepage fixture to migrate its now-required database and aligning model defaults with database defaults.
- Desktop/mobile Chromium checks passed for homepage, catalog, login, registration, About and FAQ, including overflow, mobile navigation and page-error checks. Screenshots retained outside Git under /workspace/setup/creative/screenshots. Browser tooling uses a local-only loopback port; no public preview or deployment was created.
- Final official logo files are visible in chat but are not present among the readable local attachments. The UI uses a typographic brand lockup rather than substituting the earlier unapproved marks. Original file integration remains pending.
- Ruling: use a locally rendered creative-workspace illustration rather than unlicensed or fabricated course photography. Cost if changed: replace the decorative hero artwork once approved assets are available.
- Contact submission, profile editing, course builder, enrollment/player, live media/payments, admin resources/reporting, sitemap and deeper accessibility audit remain unfinished.
