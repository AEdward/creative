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

## 2026-10-29 — working text-course LMS and basic administration

- Implemented Livewire course create/edit, modules, text lesson create/edit/unpublish, and transactional administrator publication. Instructor changes return published courses to draft. Ownership and verified/active-role checks run on actions, and internal record identities are locked.
- Added free enrollment with unique membership, expiry/revocation enforcement, published previews, private text lesson access, explicit completion, bounded resume-position storage, and student progress. Page views do not create completion records.
- Added private lesson notes/bookmarks, wishlist, current-password-protected profile/email/password changes, and contact messages with a honeypot, rate limit, and restricted inbox.
- Added secure prompted CLI admin bootstrap (never default credentials), basic Filament category/user/course/contact resources, real dashboard counts, instructor approval, suspension/reactivation, and audit records for sensitive role/status/publication actions. Suspension invalidates database sessions and rotates remember tokens.
- Replaced upstream demo-user seeding with idempotent category-only seeding. No sample courses or students are installed in development data.
- Independent review found malformed old-input rendering and GET validation redirect loops. Both were reproduced first and fixed. A further profile current-password array-input failure was reproduced and fixed with bail/string validation. Redirect-following tests carry the real session cookie.
- Successful CSRF-enabled Chromium journey on isolated MySQL fixtures: instructor creates course/module/lesson; admin publishes and opens management pages; student enrolls, reads, explicitly completes, and sees 100% progress. Temporary fixtures and QA web container removed afterward.
- SQLite suite reached 62 passing tests / 258 assertions. The same 62 tests / 258 assertions passed against isolated MySQL. Browser screenshots and test logs are retained outside Git.
- Ruling: outcomes/requirements use ordered JSON arrays in the initial course record; normalize to separate rows if later querying requires it. Cost if changed: a data migration and editor update.
- Ruling: instructor approval is administrator-controlled; public applications/payouts remain undecided. Cost if changed: add a separate application/review workflow.
- Real R2 credentials, real course content, SMTP configuration, provider documentation/credentials, final legal copy, hosting/domain, and readable original logo files remain external prerequisites for their respective launch capabilities. Paid checkout is still disabled.

## 2026-10-29 — commerce foundation and private media integration

- Added integer-price order snapshots, payment/event ledgers, test-only gateway fixtures, verified fulfillment, per-user order history and a read-only Filament order ledger. Default checkout remains unavailable; no bank/telebirr contract or callback endpoint was invented.
- Independent review found an enrollment/checkout race and ambiguous acceptance of additional checkout keys. Reproduced both, then serialized eligibility and fulfillment with a shared account lock, rechecked enrollment, and rejected different pending keys. Review also corrected a MySQL snapshot-before-lock lookup; the immutable owner lookup now precedes the transaction.
- Added the official Flysystem S3 adapter, a private R2 disk, instructor upload intents, byte/MIME/MP4-header confirmation, staging-to-final copy, same-course attachment, five-minute authorized playback URLs, player refresh/resume, and explicit completion. Upload progress/cancel and video lesson edit/restore are available when storage is configured.
- Independent media review found and prompted fixes for slow-upload confirmation and video restoration. Added a two-hour confirmation grace and regression tests preserving video type/asset. Cleanup serializes with confirmation/attachment, removes expired staging and abandoned finals, and preserves attached finals. A reviewer independently reran all 10 media tests (64 assertions) successfully.
- SDK signing tests use dummy credentials without network requests. The installed SDK does not bind PUT signatures to size/MIME; explicit browser headers and server confirmation do not constitute a storage quota. Documented quota/lifecycle/scheduling and real-provider verification prerequisites. No real R2 bucket or payment provider was contacted or activated.
- Reproduced a MySQL-only failure for 25,000 Amharic characters and widened course/lesson content to LONGTEXT. Also separated route throttle budgets and fixed administration's last-lesson publication invariant with regression coverage.
- Final complete suites: SQLite 89 passed / 382 assertions; isolated MySQL 89 passed / 382 assertions. Composer strict validation, PHP formatting, asset build, desktop/mobile browser loads/navigation/overflow/page-error checks and repeatable environment installation passed. Hosted CI, actual browser uploads/playback against R2, provider payment verification and production deployment remain unverified.
- Updated TODO, operator notes and decisions; retained no real users, credentials, transactions or course fixtures in committed files. Original final-logo files remain unavailable locally; legal review, SMTP, real content, integrations, assessments/reporting and launch tasks remain open.

## Local application completion — integrations skipped by request

- Implemented configurable quizzes with protected answer keys and immutable attempts; private assignments with instructor review; opt-in non-accredited completion certificates with current-curriculum eligibility, public opaque-reference status verification, printable/save-as-PDF copies and audited revocation.
- Added moderated eligible-learner reviews, approved-only averages, scoped instructor announcements, queued database notifications/preferences, and private student/admin support tickets with replies and close/reopen behavior.
- Added opt-in instructor profiles/directory and separate photo consent, plus authorized own-course learner reporting. Reporting excludes student contact/payment information and counts completion against current published lessons only.
- Added module rename/reordering, normalized cross-module lesson positions, private local lesson downloads/WEBVTT captions, local course/profile image validation, recently viewed learning and workspace navigation.
- Added native site-content/banner/contact settings, real enrollment/date/course charts, administrator enrollment revocation, read-only audit/media views, protected CSV export, integer percentage coupon reservations/limits and safe unpaid-order expiry. External payment/media services remain off.
- Added native Amharic translations with English fallback, a fixed root language path, localized common validation/media/status messages and mobile language selection. Independent review found remaining authoring/consent/marketing fragments; these were wired and regression-tested. Real course/user content and legal placeholders remain unchanged; fluent-speaker editorial review remains necessary. Framework back-office/browser dialogs retain their own localization boundaries.
- Independent assessment review found missing Alpine runtime on controller-rendered authoring pages and a certificate/curriculum race. Global Livewire assets load once; certificate issuance and requirement changes now synchronize on course/enrollment locks. A final publication regression reproduced stale-model dirty checking that could leave instructor edits published; draft fields are now forced in the database. Source/target ordering normalization was likewise reproduced and fixed.
- Full CSRF-enabled browser journey passed on temporary creative_test fixtures: author curriculum/quiz/assignment, publish, enroll, complete a lesson/quiz, upload/review assignment, issue certificate, announce/notify, submit/moderate review, open/reply to support, and inspect mobile workspaces.
- Automated WCAG A/AA and reflow checks: 40 English page/viewport cases passed after correcting small-text contrast, choice-control sizing and disabled pagination semantics. Eight additional Amharic mobile page cases passed, including language switching, English return, keyboard skip/menu navigation and console checks. These are automated/current-browser checks, not a universal accessibility certification or real-video device test.
- A temporary 101-enrollment roster remained paginated to 20 rows. Twenty local requests measured p95 TTFB 33 ms (maximum 36 ms). This does not establish production or concurrent-load capacity.
- Verified actual database-queue dispatch and bounded worker delivery without outbound mail. Rehearsed consistent MySQL backup, authenticated encryption/decryption, restore into a disposable database and Amharic row verification. Backup artifacts and the restore database were removed.
- Removed isolated QA users/courses/submissions/private upload files and stopped the QA web container. Development academy data was not replaced with sample learners/courses.
- Final complete SQLite suite: 131 passed / 709 assertions. Final isolated MySQL suite: 131 passed / 709 assertions. PHP formatting, strict Composer validation and frontend build passed. npm audit reported zero advisories. PHP advisory audit could not reach packagist.org under the network policy; its result is unverified, not reported clean. Hosted CI remains separately unverified.
- Updated checklist, defaults and operator runbooks. Remaining prerequisites include accessible original final-logo files, approved real academy content, legal policies and fluent translation review. External SMTP/R2/payment/hosting/off-host backup activation and deployment are intentionally skipped; optional portfolio/blog work is not part of this baseline.
