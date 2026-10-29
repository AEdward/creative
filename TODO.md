# Creative Lab Academy — Master TODO

Updated: 29 October 2026 (Africa/Nairobi).

Scope: the complete roadmap from the supplied [build specification](docs/BUILD_PLAN.md), the supplied screen references, and the subsequently confirmed official logo. This checklist records work; it does not authorize deployment, spending, or live payments.

Status: `[x]` means completed with evidence recorded in the session/log; `[ ]` means unfinished. **Blocked** identifies an external prerequisite; **Decision** needs a business choice; **Optional** is outside the required baseline. Later phases are required for the full specification unless marked optional. Reference-screen names, statistics, ratings, tax rates, prices, payment methods, and social sign-in buttons are illustrative, not approved business facts.

## Current baseline and next action

- [x] Inspect the initially empty checkout and verify GitHub repository access.
- [x] Read and retain the supplied build specification.
- [x] Review supplied homepage, sign-in, payment, admin, catalog, instructor, and student-profile references.
- [x] Confirm the later supplied graduation-cap/C/play icon and full wordmark as the official logo direction.
- [x] Verify compatible Laravel 13, Livewire 4, Filament 5, and PHP 8.4 package requirements.
- [x] Build development-only PHP/Composer tooling outside the checkout.
- [x] Verify PHP 8.4.27 and required PHP extensions.
- [x] Start private MySQL 8.4.13 and verify PHP connectivity, utf8mb4, InnoDB, and transaction rollback.
- [x] Create README.md, DECISIONS.md, IMPLEMENTATION_LOG.md, and this checklist.
- [x] Save runtime installation/start instructions and the api.github.com allowance in the environment draft.
- [x] Remove the package-download blocker using immutable official codeload.github.com archives in Composer’s cache; api.github.com access is no longer required for the current lockfile.
- [x] Initialize Laravel, install compatible UI dependencies, migrate MySQL, and verify baseline tests, assets, and real HTTP responses. Public-site and LMS features remain unbuilt.

## Decisions and external prerequisites

Resolve each before implementing the behavior it controls; unrelated development can continue with fakes or disabled integrations.

- [ ] **Decision:** agree the first release scope and which later-phase features can follow launch.
- [ ] **Decision:** define course prices, free-course rules, currency presentation, and discounts; ETB is the specification's default currency.
- [ ] **Decision:** define lifetime versus time-limited access, expiry, renewal, and course removal behavior.
- [ ] **Decision:** approve refunds, cancellations, and resulting access revocation; confirm any applicable taxes with qualified advice. Do not assume the reference's 15% VAT.
- [ ] **Decision:** choose telebirr provider/gateway, verify merchant eligibility, and obtain official integration documentation.
- [ ] **Blocked for real payments:** securely configure verified merchant credentials, webhook requirements, and sandbox access.
- [ ] **Decision:** authorize any manual/offline payment review workflow separately.
- [ ] **Decision:** choose invite-only instructor onboarding or public applications and define approval/suspension rules.
- [ ] **Decision:** define instructor payouts or explicitly exclude payouts from the platform.
- [ ] **Decision:** define course-completion, quiz-passing, assignment, review-eligibility, and certificate rules.
- [ ] **Decision:** define active-student and revenue reporting formulas and time boundaries.
- [ ] **Decision:** approve privacy, data retention, account deletion, terms, refund policy, and contact information.
- [ ] **Blocked for staging/production:** choose hosting and securely supply required access; verify shared-hosting compatibility or select a VPS alternative.
- [ ] **Blocked for external mail:** choose domain, SMTP provider, verified sender, and secure credential bindings.
- [ ] **Blocked for real media:** configure private R2 bucket, endpoint, credentials, allowed origins, and usage alert recipients.
- [ ] Supply real courses, authorized videos/resources, instructor identities, photos, biographies, and approved marketing copy.
- [ ] Retain original official logo files in the project asset library; verify transparency, export quality, and appropriate background usage.
- [ ] Confirm font licensing, photography/illustration rights, and permission for third-party product marks.
- [ ] **Optional decision:** Google/Apple sign-in, blog/news, portfolios, public instructor directory, and video watermarking.

## Phase 0 — Application foundation and repeatable development

- [x] Recheck runtime and Git state before making changes; preserve existing files and use the existing isolated checkout without creating a worktree.
- [x] Initialize a supported Laravel application without overwriting project documentation or Git metadata.
- [x] Install compatible Livewire, Blade/Alpine integration, Tailwind, and Filament; avoid loading Alpine twice.
- [x] Resolve and retain Composer/npm lockfiles; document exact installed versions and PHP/Node requirements.
- [x] Create a safe .env.example documenting database, mail, queue, storage, and payment settings without secrets.
- [x] Configure ignored local environment files and generate the local application key securely.
- [ ] Configure MySQL 8+, utf8mb4, InnoDB, and separate test database access.
- [x] Configure database sessions, cache as appropriate, database queues, and scheduler support without requiring Redis.
- [ ] Configure timezone handling and English translation files with a path for Amharic localization.
- [ ] Configure named routes and public/student/instructor/admin boundaries.
- [ ] Establish factories and clearly labeled development-only seed data; prevent demo seeding in production.
- [x] Establish existing-framework test runner, formatter, and any appropriate static checks.
- [x] Add CI for frozen dependency installation, migrations on a test database, tests, formatting checks, and asset build. Hosted execution is pending.
- [x] Document installation, local startup, database setup, tests, build, and troubleshooting.
- [x] Update saved environment install/start instructions using commands actually verified.
- [ ] Verify clean installation, migrations, baseline tests, asset build, and a representative HTTP response.
- [x] Verify repeatable startup after stopping only project-owned services; document retained files versus restarted processes.

## Database foundation — introduce migrations with their owning phase

- [ ] Users: unique email, verification, password hashing, status, and timestamps.
- [ ] Roles/permissions and instructor profiles with explicit approval state.
- [ ] Categories: unique slug, description, order, and active state.
- [ ] Courses: instructor/category foreign keys, unique slug, content, language, level, price_minor, currency, free flag, publication state/date, and thumbnail key.
- [ ] Outcomes and requirements; **optional** tags and course-tag relations.
- [ ] Modules and lessons: parent foreign keys, stable ordering, lesson type, preview/publication flags, duration, and nullable video relation.
- [ ] Video assets: provider, bucket, unique object key, MIME, bytes, duration, upload owner, and lifecycle status.
- [ ] Lesson resources: private object key, title, MIME, bytes, and lesson relation.
- [ ] Enrollments: user/course uniqueness, status, source order item, enrollment time, and nullable expiry.
- [ ] Progress: enrollment/lesson uniqueness, playback position, watched time, and nullable completion time.
- [ ] Notes/bookmarks and recently viewed records with user ownership and appropriate uniqueness.
- [ ] Orders and items: unique reference, ownership, price snapshots, currency, integer minor-unit totals/discounts, and state.
- [ ] Payments: provider/reference, unique idempotency key, amount, currency, state, and verification time.
- [ ] Payment events: provider/event identity, nullable payment link, event type, restricted minimal payload/reference, and processed time.
- [ ] Coupons/redemptions, wishlists, notifications, audit logs, and settings.
- [ ] Later-phase quizzes/questions/attempts, assignments/submissions, reviews, certificates, and support tickets.
- [ ] Define foreign keys, deliberate cascade/restrict behavior, nullable relationships, and retention rules.
- [ ] Index ownership, published status, catalog filters, ordering, event lookup, and reporting queries.
- [ ] Protect financial history from destructive deletes; keep money as integers rather than floating point.
- [ ] Verify constraints, migration order, transaction behavior, and rollback on the MySQL test database.

## Phase 1 — Brand system and public website

- [ ] Register official icon/full-logo assets; preserve proportions and colors and verify legibility on light/dark backgrounds.
- [ ] Use the confirmed logo instead of alternate marks from earlier reference screens.
- [ ] Define midnight #0B0F1A, blue #3882F6, violet #885CF6, off-white #F1F5F9, restrained gradients, and accessible text colors.
- [ ] Define typography, spacing, radii, icons, breakpoints, focus styles, and reusable page components.
- [ ] Build accessible buttons, forms, cards, alerts, pagination, loading/empty/error states, dialogs, and responsive navigation.
- [ ] Build public header/footer and mobile navigation with correct logged-in/logged-out actions.
- [ ] Build homepage hero, category navigation, featured courses, benefits, instructor spotlights, FAQ, and calls to action.
- [ ] Use real content or honest empty states; do not publish sample social proof or invented metrics.
- [ ] Build /courses and /categories/{slug} with category, level, language, free/paid, and keyword filters.
- [ ] Add sorting, query-string persistence, pagination, no-results states, and course cards.
- [ ] Build /courses/{slug}: instructor, summary, description, outcomes, requirements, curriculum, duration, previews, and access/pricing information.
- [ ] Show correct enroll/purchase/continue actions for the current access state; unavailable payments must be clearly disabled.
- [ ] Keep draft/unpublished content out of public results and detail pages.
- [ ] Build About, FAQ, Contact, and privacy/terms/refund placeholders clearly marked for legal review.
- [ ] Add contact validation, rate limiting/spam protection, delivery feedback, and server-side handling.
- [ ] Add SEO slugs, titles/descriptions, canonical URLs, Open Graph, sitemap, robots rules, and valid structured data where applicable.
- [ ] Test slug uniqueness, filtering, sorting, pagination, publication visibility, contact handling, and accessibility basics.
- [ ] Inspect mobile/tablet/desktop layouts against the supplied references; record differences without claiming pixel-perfect parity.

## Phase 2 — Authentication, permissions, and role workspaces

- [ ] Implement registration, login/logout, password reset, verification/resend, and account profile settings.
- [ ] Add validation, password confirmation where needed, secure session handling, and session invalidation on logout.
- [ ] Apply login/reset/verification throttles, CSRF protection, secure production cookies, and appropriate middleware.
- [ ] Enforce account suspension/status and verification rules consistently.
- [ ] Prevent public clients from assigning themselves instructor/admin privileges.
- [ ] Define policies/gates for every protected record and enforce them in Livewire actions as well as page routes.
- [ ] Build student shell and /dashboard, /my-learning, /wishlist, /certificates, /payments, and /profile boundaries.
- [ ] Build responsive instructor shell under /instructor/* and Filament admin shell under /admin/*.
- [ ] Add secure CLI administrator bootstrap without a hardcoded password or public admin registration.
- [ ] Add instructor approval management and scoped access to owned courses/students only.
- [ ] Build profile editing, avatar validation, and notification preferences as needed.
- [ ] Keep Google/Apple buttons absent unless those optional integrations are approved and implemented.
- [ ] Test anonymous/student/instructor/admin access, expired sessions, suspended accounts, and unverified-account restrictions.
- [ ] Test instructor cross-owner access and tampered user/course/role identifiers, including direct Livewire calls.

## Phase 3 — Course builder and protected media

- [ ] Build instructor-owned course creation/editing with title, slug, summary, rich content, category, level, language, pricing, and thumbnail.
- [ ] Add outcomes, requirements, and optional tags; sanitize user-authored content.
- [ ] Build modules/lessons creation, editing, ordering, preview flags, durations, and resource attachments.
- [ ] Add draft/publication workflow, validation before publishing, and administrator moderation controls.
- [ ] Define media storage interfaces and a fake adapter so development/testing does not require live R2.
- [ ] Configure private S3-compatible R2 storage using least-privilege credentials and metadata/object keys in MySQL.
- [ ] Authorize direct browser uploads before issuing short-lived upload permissions.
- [ ] Implement supported multipart/direct upload handling without routing multi-GB video files through PHP.
- [ ] Validate MIME/type, allowed extensions, size limits, ownership, object keys, and upload states.
- [ ] Confirm successful uploads server-side before assets become ready/attachable; do not treat an upload intent as a completed video.
- [ ] Handle progress feedback, cancellation, retry, incomplete multipart uploads, and orphan cleanup.
- [ ] Protect downloadable resources and prevent arbitrary object access or path traversal.
- [ ] Validate R2 endpoint, signing, CORS, expiry, and provider terms in staging.
- [ ] Add storage totals/threshold alerts and monitor Class A/B requests; treat the initial <=5 GB library and 10 GB reference threshold as planning inputs, not guaranteed free service.
- [ ] Test upload authorization, cross-owner attachment, invalid MIME, oversized files, failed/cancelled uploads, missing objects, and cleanup.

## Phase 4 — Enrollment and learning

- [ ] Implement transactional free enrollment with unique membership and duplicate prevention.
- [ ] Enforce expiry, suspension, revocation, unpublished-course, and refund-related access rules.
- [ ] Build learning dashboard with real enrolled courses, progress, continue learning, and recently viewed items.
- [ ] Build /learn/{course:slug}/{lesson} with authorized curriculum sidebar and previous/next navigation.
- [ ] Verify lesson belongs to the requested course; enforce published lesson and preview rules.
- [ ] Issue short-lived signed video GET links only after policy checks; never store signed URLs or expose storage credentials.
- [ ] Build responsive HTML5/Plyr playback, captions, loading/error feedback, seeking, and resume behavior.
- [ ] Handle signed-link expiry during long sessions with reauthorization and position preservation.
- [ ] Track validated playback position and watched time with bounded values and concurrent-update protection.
- [ ] Implement meaningful-progress or explicit completion rules; opening a lesson must not automatically complete it.
- [ ] Calculate course progress consistently and handle reordered, removed, or unpublished lessons.
- [ ] Add owned notes, bookmarks, wishlist, and authorized resource downloads.
- [ ] Test duplicate/free enrollment, preview versus paid access, expiry/revocation, direct media access, and lesson/course mismatch.
- [ ] Test persistence, concurrent progress updates, completion rules, ordering, and deleted/unpublished content.
- [ ] Verify mobile seeking, byte-range requests, captions, long-session expiry, and slow-connection behavior.
- [ ] **Optional:** add a privacy-conscious dynamic learner watermark as a deterrent without DRM/download-prevention claims.

## Phase 5 — Orders, checkout, and payments

- [ ] Start with single-course checkout; calculate all prices and eligibility server-side.
- [ ] Create pending orders/items with immutable amount/currency snapshots and ownership checks.
- [ ] Define order transitions: pending -> awaiting_payment -> paid/failed/cancelled/expired, plus explicit refund transitions.
- [ ] Define payment transitions: initiated -> pending -> verified_success/verified_failure.
- [ ] Build gateway interface, fake test driver, disabled-production default, and administrator-visible pending status.
- [ ] Build checkout/payment-history UI consistent with references without enabling unverified providers.
- [ ] Handle duplicate checkout, already-enrolled learners, abandoned orders, expiry, and safe retry.
- [ ] **Blocked:** implement the actual provider adapter only from verified official documentation and credentials.
- [ ] Verify authenticated callbacks/webhooks and server-to-server status; validate merchant, reference, amount, and currency.
- [ ] Add idempotency, replay protection, event deduplication, and safe out-of-order event handling.
- [ ] Atomically mark verified orders paid and create enrollments exactly once, including concurrent callbacks.
- [ ] Never grant paid access from a browser redirect, screenshot, or user-entered transaction ID alone.
- [ ] Minimize/redact payment event data, protect audit records, and avoid logging secrets or sensitive raw payloads.
- [ ] Implement reconciliation, support-visible failures, refunds, and access effects under approved rules.
- [ ] **Conditional:** implement manual/offline review only if authorized, with verified evidence and an audit trail.
- [ ] Test success/failure/pending, wrong amount/currency/reference/merchant, invalid signature, replay, duplicate events, and transaction rollback.
- [ ] Test ownership, repeated checkout, free-versus-paid paths, refunds/revocation, and disabled production checkout.
- [ ] Enable live checkout only after explicit authorization and successful provider integration checks.

## Phase 6 — Assessments, certificates, and engagement

- [ ] Build quizzes, questions, answer validation, attempts, scoring, pass rules, and retry limits.
- [ ] Keep answer keys protected and enforce learner eligibility server-side.
- [ ] Build assignments, authorized submission uploads, instructor reviews, and learner feedback.
- [ ] Enforce upload safety, file limits, ownership, and private access for submissions.
- [ ] Issue certificates only when approved completion requirements are met; prevent duplicate issuance.
- [ ] Add branded certificate downloads and public verification IDs with appropriate privacy controls.
- [ ] Define certificate revocation and verification behavior.
- [ ] Add queued notifications for enrollment, learning, assignments, and certificates; respect preferences.
- [ ] Add instructor announcements scoped to their enrolled learners.
- [ ] Build eligible-enrollee course reviews, duplicate prevention, moderation, and real aggregate ratings.
- [ ] Test scoring, retries, submission ownership, instructor boundaries, certificate eligibility/verification, and notification delivery.
- [ ] **Optional:** student portfolios.

## Phase 7 — Administration, content, and operations

- [ ] Build admin resources for students, instructor approvals, courses, categories, modules, lessons, and enrollments.
- [ ] Add order/payment/refund management without unsafe status overrides or exposure of credentials.
- [ ] Add coupon creation, expiry, usage limits, redemptions, and atomic concurrency checks.
- [ ] Add certificate, review-moderation, support-ticket, and notification administration.
- [ ] Build banners/site-content management and settings for contact, ETB currency, localization, payment activation, enrollment rules, and storage thresholds.
- [ ] Build admin dashboard with real active students, courses, lessons, verified revenue, and recent enrollments.
- [ ] Add date filters, real growth/enrollment charts, documented formulas, and honest zero/empty states.
- [ ] Add scoped instructor enrollment analytics without unrelated student personal/payment data.
- [ ] Add media library, storage accounting, threshold alerts, and orphan cleanup controls.
- [ ] Record sensitive admin/instructor actions in access-controlled audit logs.
- [ ] Add fine-grained permission checks to resources, exports, bulk actions, and settings changes.
- [ ] Configure database queue retries/backoff, failed-job handling, and bounded cron queue processing for shared hosting.
- [ ] Schedule cleanup, order expiry, usage checks, and other required maintenance without overlapping runs.
- [ ] Add operational logs with redaction, retention, health checks, and error alerting.
- [ ] Prepare Amharic translation scaffolding, Unicode-safe content, locale selection, and fallback behavior.
- [ ] **Optional:** full Amharic translation/localized content after scope confirmation; blog/news; public instructor directory/profile pages based on supplied references.
- [ ] Configure encrypted/off-host backups as appropriate, retention, restore documentation, and a real restore rehearsal.
- [ ] Test reporting formulas/date filters, coupon limits, support permissions, audit access, cron processing, failed-job retry, and backup restoration.

## Phase 8 — Quality assurance and launch

### Security and reliability

- [ ] Run the complete auth/authorization/commerce/media/learning/operations test matrix from the build specification.
- [ ] Review session invalidation, CSRF, rate limits, password flows, account status, and ownership checks.
- [ ] Review rich-content escaping, uploads, signed links, callback verification, replay resistance, and secret handling.
- [ ] Verify dependencies with appropriate security audits and resolve or document material findings.
- [ ] Test database constraints, concurrent writes, error recovery, and financial/enrollment consistency.
- [ ] Verify sensitive pages/storage are private and production does not expose debug information.

### Accessibility, browsers, and performance

- [ ] Check semantic headings, labels, alt text, focus visibility/order, keyboard navigation, dialogs, and error announcements.
- [ ] Check WCAG AA contrast, touch targets, zoom/reflow, reduced motion, and video captions.
- [ ] Inspect public, auth, student, instructor, admin, checkout, and player screens at mobile/tablet/desktop sizes.
- [ ] Test representative current desktop/mobile browsers, including long video sessions and slow Ethiopian mobile connections.
- [ ] Optimize images/assets, lazy loading, database query counts/indexes, and pagination; investigate N+1 queries.
- [ ] Validate performance at the initial ~100-active-student scale and document hosting/resource limits.
- [ ] Check SEO output, canonical URLs, sitemap, robots behavior, social previews, and broken links.

### Staging and production

- [ ] Validate hosting PHP/extensions, Composer, MySQL, filesystem permissions, cron, SSL, and build-artifact support; document VPS fallback if needed.
- [ ] Configure staging securely and deploy only with authorization.
- [ ] Build compiled frontend assets in CI/local build and deploy the Laravel public webroot correctly.
- [ ] Set production APP_ENV, APP_DEBUG=false, application key, URL, HTTPS/cookies, DB, SMTP, storage, and logging securely.
- [ ] Ensure repository root, .env, storage credentials, backups, and logs cannot be served publicly.
- [ ] Configure scheduler and cron-bounded queues; verify scheduled tasks, mail, and failed-job recovery.
- [ ] Verify real R2 upload, CORS, signed playback, range seeking, expiry, and storage monitoring on the selected host.
- [ ] Perform provider-approved payment sandbox checks and reconciliation; keep live checkout off until authorized.
- [ ] Replace demo data and placeholders with approved content; complete legal review before launch.
- [ ] Verify database/storage backup and restore procedure, release rollback, and application health monitoring.
- [ ] Document deployment, migrations, administrator bootstrap, secrets rotation, incident response, and routine maintenance.
- [ ] Obtain launch authorization before production deployment, spending, or enabling live payments.
- [ ] Run post-deployment smoke checks for registration, verification mail, discovery, enrollment, authorized playback, progress, and role access.
- [ ] Record unresolved limitations and hand off operator documentation and secure access procedures.

## Acceptance gate after every phase

- [ ] Run relevant tests and confirm real test counts; distinguish passed, failed, skipped, and unrun checks.
- [ ] Run formatting/static checks appropriate to the change, migrations against the test database, and the frontend build.
- [ ] Verify meaningful HTTP behavior; inspect responsive UI when the phase changes screens.
- [ ] Test denied access and failure paths alongside successful behavior.
- [ ] Update IMPLEMENTATION_LOG.md, DECISIONS.md, this checklist, and setup/start documentation as needed.
- [ ] Record external blockers and confirmed repository defects separately; do not mark blocked work complete.
- [ ] Commit coherent changes only when authorized; do not push or deploy implicitly.

Reuse this gate for each phase; a previous phase's result does not validate later changes.

## Full-product completion gate

- [ ] Students can register, verify accounts, discover courses, and manage their profiles.
- [ ] Students can enroll in free courses and purchase paid courses through a verified, authorized provider.
- [ ] Only eligible users can access private lessons/resources; real R2 videos play and resume correctly.
- [ ] Progress, dashboards, and completed learning records reflect real database state.
- [ ] Instructors can manage their own courses and eligible learners without cross-owner access.
- [ ] Administrators can manage and audit platform data under enforced permissions.
- [ ] Required assessments/certificates and administration features for the agreed release are verified.
- [ ] Security, payment, permission, accessibility, build, migration, and operational checks have evidence.
- [ ] Production deployment, backups, restore rehearsal, monitoring, and handoff are documented and authorized.
- [ ] Missing real-payment documentation/credentials remain explicitly blocked, never reported as a completed paid-learning workflow.
