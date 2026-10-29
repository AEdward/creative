# Public Site and Accounts Implementation Plan

> For agentic workers: execute inline using superpowers:executing-plans with test-driven development. The user has requested continuous execution.

**Goal:** Ship a real database-backed public catalog and secure account/role foundations.
**Architecture:** Laravel monolith with Blade-rendered public pages, Eloquent catalog queries, native session authentication, and Filament administration. Livewire remains available for authenticated workflows.
**Tech Stack:** Installed Laravel 13, Livewire 4, Filament 5, Tailwind 4, PHP 8.4, MySQL 8.4.
**Spec:** docs/BUILD_PLAN.md and TODO.md; user-supplied screen and logo references.

## Global constraints

Keep payments disabled, do not invent social proof or real course content, protect draft courses, use integer minor units, and enforce ownership server-side. Do not deploy or spend money. Use existing isolated checkout. The user has authorized continuous development and pushes in this session.

## Review focus

- Draft/inactive-category content must never leak through details or search.
- Malformed filter input must produce validation feedback rather than a server error.
- Registration must not accept client-supplied privileged roles.
- Suspended users and cross-owner instructors must not reach privileged actions.
- Public empty states must be useful without manufactured courses, prices, or ratings.

## Task 1 — Catalog data and public behavior

Files: Category/Course/Module/Lesson models, migrations/factories, CatalogController, routes/web.php, tests/Feature/CatalogTest.php.
Interfaces: Course::published() scoped query; category/course slug route binding; GET /courses with q/category/level/language/access/sort query parameters.
- [ ] Write and run failing public-route, filtering, pagination, visibility, and validation tests.
- [ ] Implement schema, relations, published scope, controller validation/querying and detail routes.
- [ ] Verify focused tests and migrations, then commit the independently usable catalog.

## Task 2 — Branded public experience

Files: resources/views/components/layout.blade.php, home/catalog/course/static views, resources/css/app.css, public brand assets when original files are available.
Interfaces: named home/courses.index/courses.show/categories.show/about/contact/faq/legal routes; server-rendered GET forms preserve query state.
- [ ] Test home/catalog/detail copy, escaped content, legal placeholders, empty states, and public link targets.
- [ ] Build reference-led dark hero/light catalog, responsive navigation, category paths, course cards, curriculum, FAQ and legal-review notices. Use text brand lockup if the confirmed original logo file cannot be read locally; do not substitute earlier logos.
- [ ] Verify full tests, assets, responsive browser checks if browser tooling is available, and HTTP responses. Record unavailable checks honestly.

## Task 3 — Secure accounts and role foundations

Files: auth controllers/views/routes, User model/role-status migration, middleware/policies, Filament provider, tests/Feature/AuthenticationTest.php and AuthorizationTest.php.
Interfaces: native session login/register/reset/verification; student default role; verified/active middleware; instructor/admin gates; CoursePolicy ownership rules.
- [ ] Write and run failing registration, login/logout, reset/verification, throttling, role-injection, and unauthorized/cross-owner tests.
- [ ] Implement native Laravel flows, role boundaries and minimal workspaces; no default administrator credentials.
- [ ] Verify full tests on isolated databases, formatter, build, HTTP behavior; update logs/TODO and commit/push verified work.

After tasks: fresh whole-change security/correctness review, fix material findings with regression tests, record remaining external dependencies, and proceed to the next coherent increment as context/resources permit.
