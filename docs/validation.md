# Validation

## Projects column filters checked on 22 November 2026

- Replaced the separate Projects filter form with the shared table column menus, search, sorting and pagination.
- 27 Projects and shared column-filter tests passed with 281 assertions in the isolated `statssa_role_tests` database with preview configuration caching bypassed.
- Covers filtering records beyond page one, combining column conditions, numeric conditions, invalid fields, company scoping and restricted employee counts.
- Browser checks covered value selection, empty results, reset, persistence after reload and mobile column selection. No script errors were reported in the final preview.

## Local role selector checked on 19 November 2026

- Restored the optional localhost role selector and its existing regression coverage.
- 18 authentication feature tests passed with 96 assertions in an isolated MySQL test database.
- Covers all seven demo roles, invalid/missing roles, unavailable/inactive accounts, unchanged permissions, restricted-role access, non-local URL rejection and credential sign-in when demo mode is off.
- Controller syntax and PHP formatting passed. The local role dropdown was inspected in the browser.
- Fresh developer Docker builds, hosted authentication and Dima's own machine remain to be checked on their receiving environments.


## Historical credential checks on 8 November 2026

- Credential sign-in: 12 feature tests, 49 assertions passed against a separate MySQL test database.
- Covers all seven reference account roles, missing credentials, inactive users, incorrect passwords, removal of the passwordless bypass and restricted asset access for User Manager.
- Table filters and calendar permission/company scoping: combined run of 31 tests, 200 assertions passed, including the 12 sign-in tests.
- Developer Compose configuration parsed successfully; the environment initializer created a fresh local file and refused to overwrite it.
- PHP formatting passed for the changed authentication files.
- The updated sign-in screen was rendered and inspected in Chrome.

This is targeted validation, not a claim that the entire upstream test suite or every identity-provider integration has been verified. Fresh setup instructions and Docker configuration require validation on the receiving developer's machine.

## Test isolation

Copy `.env.testing.example` to `.env.testing` and configure a disposable test database. Match the other local environment settings required by the application. Never point tests at the normal application database.

If process-level production environment variables or a cached production config exist, use a separate, non-existing config-cache path:

```bash
APP_ENV=testing APP_CONFIG_CACHE=/tmp/statssa-testing-config.php \
SESSION_DRIVER=array CACHE_DRIVER=array \
php artisan test tests/Feature/Auth/RoleLoginTest.php
```

The database name must come from your isolated testing environment. Set it explicitly if container environment variables would override `.env.testing`.

Stats SA branding is covered by `tests/Feature/Branding/BrandingTest.php`: application and mail sender names, rendered email content, authenticated guide access, and public licence attribution. Keep original licences and internal compatibility identifiers intact when adjusting visible branding.
