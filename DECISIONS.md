# Setup decisions

- Use the supplied Laravel monolith specification as project requirements; its implementation directives do not expand the current setup request into the full LMS build.
- Compatible versions verified against Composer metadata: Laravel framework 13.39.0 / application skeleton 13.11.0, Livewire 4.4.8, Filament 5.11.0. Composer must resolve and lock exact versions during initialization.
- PHP 8.4.27 and MySQL 8.4.13 are validated in development containers. Native host Node 24.19.0 and npm 11.9.0 are available.
- Keep cloud-only tools outside the checkout. Containers are a local tooling fallback, not a hosting decision.
- Keep R2, SMTP, and real payments unconfigured until their feature work. No production credentials or business rules are invented.
