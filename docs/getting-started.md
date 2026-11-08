# Local setup

## Docker route

Install Git, Python 3 and Docker Desktop (or a compatible Docker Compose engine). Start Docker, then:

```bash
git clone https://github.com/luvuyoskhosana04/statssa-asset-management.git
cd statssa-asset-management
python3 scripts/init-local.py
docker compose -f compose.dev.yaml up -d --build
```

Open `http://localhost:8000` and complete the setup wizard to create your own administrator. The image startup runs migrations. The first build needs internet access and can take several minutes. If port 8000 is occupied, add `APP_PORT=8001` and change `APP_URL=http://localhost:8001` in `.env` before starting.

Use `compose.dev.yaml`. The upstream `docker-compose.yml` runs the released upstream image and will not include these custom source changes.

```bash
# Logs and cache reset
docker compose -f compose.dev.yaml logs --tail=100 app
docker compose -f compose.dev.yaml exec app php artisan optimize:clear
# Rebuild after changing source copied into the image
docker compose -f compose.dev.yaml up -d --build
# Stop while preserving records
docker compose -f compose.dev.yaml down
```

The app and database use persistent volumes. Do not add `-v` to the stop command unless you intend to erase local data. This development configuration binds the application to localhost and does not expose the database port.

## Native PHP route

Use PHP 8.2 or a compatible newer version, Composer, MySQL/MariaDB, and the PHP extensions required by `composer.json`. For asset compilation also install a Node version compatible with the locked Mix dependencies.

1. Copy `.env.example` to `.env`, set `APP_URL`, database credentials, local storage and mail settings.
2. Run `composer install`, then `php artisan key:generate`.
3. Create an empty local database, then run `php artisan migrate`.
4. Point your local PHP web server to `public/`. Complete the setup wizard.
5. For changes in compiled frontend source, run `npm ci` and `npm run dev` (or `npm run prod`). Public custom CSS/JS files can be edited directly.

Do not use the Python initializer unchanged for native PHP: it sets `DB_HOST=db` for Compose networking.

## Users and roles

Create user accounts and permission groups through the administrator UI. Reference role guides live in [roles](roles/README.md). Do not depend on factory/demo usernames or a shared password. The passwordless role picker has been removed.

## What is not included

The current local database, uploaded files, live credentials and installed dependencies are not in Git. A fresh install will not show the same records as the design machine. Arrange a separate approved data transfer if needed.

See [test isolation](validation.md) before running feature tests.
