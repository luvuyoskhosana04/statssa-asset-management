# Validation

## Checked on 8 November 2026

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
