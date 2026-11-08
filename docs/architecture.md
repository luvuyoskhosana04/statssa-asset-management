# Architecture

## Request to screen

Web route -> web controller -> model and policy -> Blade view. Interactive tables call API routes -> API controller -> transformer -> JSON.

- `routes/web.php` and `routes/web/` define UI endpoints.
- `routes/api.php` defines API endpoints.
- `app/Http/Controllers/` and `app/Http/Controllers/Api/` mirror the feature areas.
- `app/Http/Transformers/` shapes API responses.
- `app/Policies/` enforces authorization. Preserve checks in both web and API paths.
- `resources/views/` contains AdminLTE 2 / Bootstrap 3 pages.
- `app/Livewire/` provides individual reactive widgets, not the entire application shell.

## Stack

PHP 8.2 or newer compatible with composer.lock, Laravel 12, Blade, Livewire 4, Laravel Mix/webpack, Bootstrap 3, Chart.js 2.9.4 and FullCalendar 6. Use `npm run dev` or `npm run prod`; this project has no `npm run build` script.

## Authentication and authorization

`Auth/LoginController.php` uses the existing username/password flow, active-account checks and login throttling. Configured SAML, LDAP, Google or remote-user integrations require their own setup and testing. The old passwordless role-selection branch has been removed.

Role factory states are test fixtures. Actual access comes from saved user/group permissions. Account display names, navigation visibility and route names are not substitutes for policy checks.

## Data and environment

Database migrations define schema; factories and seeders provide explicit test/sample data. The source export does not include the local database. `.env` belongs on the developer's machine and must stay out of Git. File uploads and OAuth keys belong in runtime storage.

## Working rules

Read [AGENTS.md](../AGENTS.md) and `.ai/rules/index.md` before modifying an area. Keep the upstream directory layout, use translations for UI text, and avoid dependency upgrades as part of a styling change.
