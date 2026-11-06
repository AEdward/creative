# Creative Lab Academy — Codex Master Build Specification
Version: 1.0 | 29 October 2026

## 0. Codex operating instruction
You are the lead full-stack engineer implementing Creative Lab Academy, a production-oriented online course LMS. Inspect the repository and environment before writing code. Implement in coherent, verified increments, not just plans. If the repository is empty, initialize a supported stable Laravel application and compatible Livewire, Filament, Tailwind and MySQL stack. Check current package compatibility and PHP requirements; do not hardcode an unsupported version. Maintain a running IMPLEMENTATION_LOG.md, DECISIONS.md, and TODO.md. Do not fabricate gateway APIs, successful transactions, deployment credentials, enrollment metrics, reviews, or real course content. Ask only for blockers that cannot be safely deferred. Use sensible, documented defaults for everything else. Do not deploy, spend money, or enable live payments without authorization.

## 1. Product and audience
Brand: Creative Lab Academy. A premium, minimal, modern online academy for video editing, graphic design, motion design, 3D/Blender, and digital marketing. Initial scale: ~100 active students/month; course video library initially <=5 GB. English interface first, architecture prepared for Amharic localization. Three roles: student, instructor, administrator. Public marketing website plus student LMS, instructor workspace, and administrative back office in ONE Laravel monolith.

## 2. Approved stack
- Laravel + Livewire + Blade + Alpine.js + Tailwind CSS; Filament for admin (check compatible stable versions).
- MySQL 8+ with InnoDB, foreign keys, indexes, transactional integrity, utf8mb4.
- Laravel native session auth, password reset, email verification, CSRF, policies/gates, rate limiting. Secure cookies, HTTPS in production.
- Cloudflare R2 private S3-compatible bucket for course MP4s and other protected media. Store only object keys and metadata in MySQL. Browser plays through native HTML5 video or Plyr; support byte-range seeking and short-lived signed GET links. Verify R2 endpoint, CORS, signature compatibility, and current provider terms in staging. No promise of DRM or download prevention.
- Use Laravel database queues and scheduler initially; do not require Redis, Docker, or long-running workers for initial shared-hosting deployment. If using shared hosting, use cron-bounded queue processing. Confirm host's PHP, Composer, Node build, cron, SSL, and filesystem support; if incompatible, document VPS alternative.
- Payments: telebirr-first via documented gateway/provider, but NEVER invent contracts. Build gateway adapter, fake sandbox driver for tests, and manual/offline payment review if authorized. Keep real checkout disabled until verified credentials, merchant eligibility, webhook signatures, and gateway docs are provided.
- Use environment configuration for all secrets and service settings. Never commit secrets.

## 3. Brand/UI reference
Premium minimal direction: midnight #0B0F1A, electric blue #3882F6, violet #885CF6, off-white #F1F5F9. Restrained blue-violet gradient, generous whitespace, rounded cards, crisp sans-serif typography. Match approved design references when available; otherwise create consistent original assets without pretending pixel-perfect fidelity. Responsive mobile/tablet/desktop. Accessibility: semantic HTML, visible focus, keyboard navigation, alt text, WCAG AA color contrast, reduced motion. Do not embed fictitious student counts, ratings, prices, names, or testimonials as production facts. Seed clearly marked demo data only in local/development.

## 4. Public site
- Homepage: hero, category navigation, featured courses, learning benefits, instructor spotlights, FAQ, CTA.
- Courses catalog: category, level, language, free/paid, keyword search, sorting, pagination, course cards.
- Course details: title, description, instructor, outcomes, requirements, curriculum, duration, preview lessons, price/access rules, purchase/enroll CTA.
- About, contact form (spam protection), privacy, terms, refund policy placeholders requiring legal review, FAQ, blog/news optional after MVP.
- SEO-friendly slugs, metadata, sitemap, Open Graph, canonical URLs, structured data where appropriate.

## 5. Student LMS
- Registration, login/logout, password reset, email verification; profile settings.
- Dashboard: enrolled courses, progress, continue learning, recently viewed, certificates, payments.
- Lesson player: sidebar curriculum, video playback, previous/next, completed lessons, notes/bookmarks, downloadable resources only when authorized, resume progress. Avoid marking completed solely from opening the page; track meaningful progress or explicit completion.
- Free enrollments, paid enrollment gated on verified payment; no double enrollment; access revocation rules.
- Wishlist and notifications; quizzes, assignment uploads/reviews, certificates in later phase.
- Course review allowed only for eligible enrolled learners; moderation.

## 6. Instructor portal
- Role approval by admin; instructors can only manage their own courses and students in their courses.
- Course builder: draft/published states, title, slug, description, category, tags, pricing, thumbnail, modules, ordered lessons, preview flags, video attachment, resource attachment, course outcomes.
- R2 upload workflow: server-authorized direct browser upload if supported; avoid routing multi-GB media through PHP. Validate file types, sizes, ownership, status; persist upload metadata only after successful confirmation. Handle incomplete/orphaned uploads.
- Enrollment analytics, assignment review, student announcements; no access to unrelated students' personal/payment data.

## 7. Admin panel
- Dashboard metrics calculated from real DB: active students, courses, lessons, paid revenue, recent enrollments; date filters.
- Manage students, instructors/approvals, courses/publication, categories, lessons, enrollments, orders, payments, refunds, coupons, certificates, banners, settings, support, media usage, audit log.
- Fine-grained policies and activity logs for sensitive actions. Superadmin bootstrap only via secure CLI or seeded local environment; never hardcode admin password.
- Settings for site content, contact, currency ETB, payment activation, storage usage thresholds, enrollment rules, localization.

## 8. Database schema (initial tables)
users (id, name, email unique, email_verified_at, password, status, timestamps)
roles / permissions or equivalent robust role model; instructor_profiles
categories (id, name, slug unique, description, sort_order, active)
courses (id, instructor_id FK, category_id FK, slug unique, title, summary, description, level, language, price_minor, currency, is_free, status, published_at, thumbnail_key, timestamps)
course_outcomes; course_requirements; course_tags (optional)
modules (id, course_id FK, title, sort_order)
lessons (id, module_id FK, title, type, video_asset_id nullable, duration_seconds, is_preview, sort_order, published)
video_assets (id, storage_provider, bucket, object_key unique, mime_type, bytes, duration_seconds, status, uploaded_by, timestamps)
resources (id, lesson_id FK, object_key, title, mime_type, bytes)
enrollments (id, user_id FK, course_id FK, source_order_item_id nullable, status, enrolled_at, expires_at nullable, unique(user_id,course_id))
lesson_progress (id, enrollment_id FK, lesson_id FK, position_seconds, watched_seconds, completed_at nullable, unique(enrollment_id,lesson_id))
orders (id, user_id FK, reference unique, currency, subtotal_minor, discount_minor, total_minor, status, timestamps)
order_items (id, order_id FK, course_id FK, unit_price_minor)
payments (id, order_id FK, provider, provider_reference nullable, idempotency_key unique, amount_minor, currency, status, verified_at nullable, raw_event_reference nullable)
payment_events (id, provider, external_event_id unique nullable, payment_id nullable, event_type, payload_json restricted, processed_at nullable)
coupons; coupon_redemptions; wishlists; notifications; audit_logs; settings
Phase 2: quizzes, questions, attempts, assignments, submissions, reviews, certificates, support_tickets.
Use database migrations, constraints, proper cascading/restrict behavior, and index common filters. Money in integer minor units, not float. Avoid storing raw sensitive payment payloads or credentials.

## 9. Course purchase/payment state machine
- order: pending -> awaiting_payment -> paid OR failed/cancelled/expired; refund transitions explicitly documented.
- payment: initiated -> pending -> verified_success OR verified_failure; no success based only on frontend redirect, screenshots, or user-entered transaction IDs.
- Verify server-to-server through official provider docs and authenticated webhooks/callbacks, check amount/currency/reference/merchant, idempotency, replay protection.
- Atomic transaction: mark order paid and create enrollments exactly once; duplicate webhook cannot create duplicates.
- If gateway contract unavailable, implement interfaces, mock tests, admin-visible pending status, and disabled production checkout; document integration checklist.

## 10. Video playback security and limits
- Private R2 bucket, least-privilege credentials, no public bucket listing.
- Authorize each lesson view through policy: authenticated, enrolled/eligible or preview; issue expiring signed object GET URL. Do not store signed URLs in DB or expose permanent R2 credentials.
- HTML5/Plyr player, responsive, captions support, byte-range requests, playback resume.
- Optional dynamic student identifier watermark overlay as deterrence; no claims that it prevents downloading/screen recording. Do not rely on disabling right-click as security.
- Test video seek, signed URL expiry during long sessions, mobile browsers, slow Ethiopian mobile connections, CORS and R2 request counts.
- R2 free allowance is not guaranteed forever; monitor 10 GB storage, Class A and B request counts and usage alerts. Initial video library <=5 GB.

## 11. Routes and boundaries
Public: /, /courses, /courses/{slug}, /categories/{slug}, /about, /contact, /login, /register.
Student: /dashboard, /my-learning, /learn/{course:slug}/{lesson}, /wishlist, /certificates, /payments, /profile.
Instructor: /instructor/*; Admin: /admin/* via Filament.
Use named routes, policies and middleware; never trust a role ID or course ID from the client without ownership checks. Prefer Livewire actions for authenticated workflows, avoid unnecessary separate API until mobile apps require one.

## 12. Phased execution and acceptance tests
Phase 0 — Repository/environment audit, baseline, CI, env.example, README, migrations, tests. Verify fresh install and test suite.
Phase 1 — Brand design system, public responsive site, catalog, course details, search/filter; tests for slugs, pagination, accessibility basics.
Phase 2 — Authentication, role permissions, student/instructor/admin shells; tests for unauthorized and cross-tenant access.
Phase 3 — Course builder, modules/lessons, thumbnails, media metadata, R2 integration behind fake adapter; tests for upload validation, ownership, failures.
Phase 4 — Free enrollment, learning player, progress, bookmarks; tests for preview vs paid access, lesson authorization, progress consistency.
Phase 5 — Cart/checkout (single course first), order/payment state machine, payment adapter and mock provider, idempotent callbacks; production payments OFF until docs/credentials verified.
Phase 6 — Quizzes, assignments, certificates with public verification ID, notifications, student portfolios optional.
Phase 7 — Admin reporting, coupons, CMS, support, audits, SEO, bilingual scaffolding, backups and restore rehearsal.
Phase 8 — Security/performance/accessibility QA, staging deploy, production checklist and handoff.
After EACH phase: run tests, lint/format, migrations on test DB, build assets, inspect responsive UI, log results and blockers, commit coherent changes when authorized.

## 13. Required test matrix
- Auth: registration, login, email verification, reset, session invalidation, CSRF/rate limiting.
- Authorization: anonymous, student, instructor, admin; instructors cannot edit other instructors' courses; unenrolled student cannot play paid lesson.
- Commerce: free enroll, duplicate checkout, failed payment, wrong amount/currency, webhook retry/replay, refund/access handling.
- Video: missing object, unauthorized access, expiring signed URL, range seeking, upload cancellation, oversized file, wrong MIME.
- Learning: progress persistence, concurrent updates, completion, lesson ordering, deleted/unpublished content.
- Operational: database backup/restore, storage usage thresholds, cron queue processing, failed job retries, logs without secrets.

## 14. Deployment plan
Build frontend assets in CI/local build; deploy compiled assets and PHP app. Configure APP_ENV=production, APP_DEBUG=false, HTTPS, proper webroot/public directory, storage permissions, DB credentials, SMTP, R2 keys, cron scheduler, queue processing, backups, error logging and health check. Never serve repository root or .env. Test upload and signed playback on real hosting before launch. Prefer VPS if shared host cannot support secure Laravel runtime, queues/cron, PHP version, or file upload workflow.

## 15. Explicit pending decisions
- Repository URL and access; hosting choice and credentials.
- Course pricing and whether access is lifetime or time-limited; refund policy.
- Confirm exact payment provider and official integration documentation.
- Instructor onboarding: invite-only vs public applications; payout policy if any.
- Whether Google/Apple sign-in is required (not included by default).
- Final brand/logo files and exact approved visual references.
- Course content, instructor identities, policies, domain, SMTP sender.
Document defaults but do not silently decide business-critical rules.

## 16. Definition of done
The product is done only when: students can register, discover courses, enroll in free courses, purchase paid courses through a verified enabled provider, access authorized lessons, watch R2 videos inside the website, resume progress, and see their dashboard; instructors can create/manage their courses; admins can manage and audit platform data; permissions and payments are tested; production deployment and restore procedures are documented. If real payment credentials/docs are missing, mark paid checkout as blocked rather than complete.

## 17. First instruction to execute now
Inspect the current repository and runtime. Report a concise implementation baseline, then immediately initialize or adapt the Laravel/Livewire/MySQL project and implement Phase 0 and Phase 1. Do not stop at architecture diagrams. Record any external dependencies, make safe progress without them, and show actual test/build results. Do not claim visual parity if reference image files are unavailable.
