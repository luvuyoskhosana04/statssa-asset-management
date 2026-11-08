#!/usr/bin/env python3
"""Create an untracked local environment with fresh keys and passwords."""
from pathlib import Path
import base64
import secrets

root = Path(__file__).resolve().parents[1]
target = root / '.env'
if target.exists():
    raise SystemExit('.env already exists; leave it in place and edit it manually.')
settings = {
    'APP_ENV': 'local', 'APP_DEBUG': 'false',
    'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
    'APP_URL': 'http://localhost:8000', 'APP_TIMEZONE': 'Africa/Johannesburg',
    'DB_CONNECTION': 'mysql', 'DB_HOST': 'db', 'DB_PORT': '3306',
    'DB_DATABASE': 'statssa', 'DB_USERNAME': 'statssa',
    'DB_PASSWORD': secrets.token_hex(24), 'MYSQL_ROOT_PASSWORD': secrets.token_hex(24),
    'MAIL_MAILER': 'log', 'MAIL_DRIVER': 'log', 'SESSION_SECURE_COOKIE': 'false',
}
lines = []
for line in (root / '.env.example').read_text().splitlines():
    key = line.split('=', 1)[0] if '=' in line and not line.startswith('#') else None
    if key in settings:
        line = key + '=' + settings.pop(key)
    lines.append(line)
lines.extend(key + '=' + value for key, value in settings.items())
with target.open('x') as stream:
    stream.write('\n'.join(lines) + '\n')
target.chmod(0o600)
print('Created local .env. Keep it out of Git. No application user has been created.')
