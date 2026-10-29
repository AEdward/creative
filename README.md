# Creative Lab Academy

Project setup follows [the supplied build specification](docs/BUILD_PLAN.md).

## Current baseline

The repository started empty. The managed cloud runtime now has a tested PHP 8.4 development image, Composer, and a private MySQL 8.4 service. Laravel scaffolding is **not installed yet**: Composer's GitHub archive downloads need `api.github.com` enabled in environment settings. The requested addition is saved in the environment draft.

Cloud tooling lives in `/workspace/setup/creative`, outside the application checkout. Use the existing isolated checkout; do not create a Git worktree for ordinary cloud tasks.

```sh
/workspace/setup/creative/start-db
/workspace/setup/creative/php php /workspace/setup/creative/check-db.php
/workspace/setup/creative/php composer --version
```

Docker is a development-environment fallback because this machine has neither PHP nor permission to install system packages. It is not a production deployment requirement. Database credentials are generated locally outside this checkout and must never be committed or printed. MySQL has no published host port.

## Resume after network access is applied

Complete the Laravel 13 skeleton in a temporary directory, copy its files into this checkout without overwriting the existing documentation or Git metadata, and use Composer to resolve compatible Livewire 4 / Filament 5 dependencies. Commit lockfiles after application initialization. Configure a local `.env` without committing secrets, run MySQL migrations, execute the generated tests, install frontend dependencies, build assets, and verify an HTTP response before declaring the project ready.

No LMS features, authentication flows, media integration, or payment processing have been implemented or verified. See [TODO.md](TODO.md).
