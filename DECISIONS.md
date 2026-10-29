# Setup decisions

- Use the supplied Laravel monolith specification as project requirements; its implementation directives do not expand the current setup request into the full LMS build.
- Compatible versions verified against Composer metadata: Laravel framework 13.39.0 / application skeleton 13.11.0, Livewire 4.4.8, Filament 5.11.0. Composer must resolve and lock exact versions during initialization.
- PHP 8.4.27 and MySQL 8.4.13 are validated in development containers. Native host Node 24.19.0 and npm 11.9.0 are available.
- Keep cloud-only tools outside the checkout. Containers are a local tooling fallback, not a hosting decision.
- Keep R2, SMTP, and real payments unconfigured until their feature work. No production credentials or business rules are invented.

## Working dependency-download alternative

- Download the exact Laravel skeleton commit from the official GitHub codeload endpoint rather than cloning its entire history.
- Resolve packages using standard Composer metadata/lockfiles. Populate Composer's local archive cache from the official immutable GitHub release endpoints when the API archive host is unavailable. Keep TLS and declared checksum validation enabled; do not rewrite the lockfile or disable security checks.
- Retain the cache helper outside the checkout as cloud-specific tooling. Ordinary developer machines can use standard Composer installation.
- Use system fonts for the baseline to avoid a build-time dependency on fonts.bunny.net. Final branded typography remains a later design task.
- Install Laravel Boost as requested by the upstream scaffold and retain its generated framework guidance.

## Incremental learning, commerce and media

- User authorization subsequently expanded setup into building the academy and pushing changes to main. Deployment, spending and live payments remain outside this authorization.
- Store money as integer minor units with immutable order item snapshots. Require a real adapter's verified evidence before fulfillment; do not invent telebirr or bank callback contracts. Different pending checkout keys are rejected rather than silently accepted without persistence.
- Serialize checkout and payment fulfillment with the same account-row lock, then recheck eligibility. Terminal failures require operator reconciliation before a contradictory success is applied.
- Use LONGTEXT for course descriptions and lesson bodies so validated Amharic content fits MySQL. The widening migration intentionally does not narrow columns on rollback, avoiding truncation of valid saved content.
- Use separate staging and permanent video object keys so a reusable PUT URL cannot overwrite a finalized lesson asset. Confirm byte count, MIME and MP4 header; preserve staging until expiry grace to permit recovery from application transaction failures, then prune it. No upload-time size restriction is claimed from the SDK signature.
- Upload intent URLs expire in 15 minutes; completion may be confirmed for two more hours. Ready unattached videos have a 24-hour recovery window before pruning. These are initial technical defaults; operator retention and real-provider behavior need staging verification.
- Add the official Flysystem S3 adapter for the build plan's approved R2 integration. Real R2 configuration remains absent; no bucket was created or written to.
