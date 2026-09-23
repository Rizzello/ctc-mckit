# Production deployment and security

MC Kit is designed to run behind a reverse proxy that terminates TLS. Do not
publish PHP-FPM directly to the internet.

The supplied deployment example defines four application processes:

| Process | Responsibility |
| --- | --- |
| `web` | nginx, static Quasar PWA assets, and requests forwarded to PHP-FPM |
| `app` | Laravel and PHP-FPM |
| `worker` | Database-backed queue jobs, including Sessionize synchronization |
| `scheduler` | Laravel scheduled tasks |

Use [`deploy/compose.example.yml`](../deploy/compose.example.yml) as the
starting point for a deployment stack. It intentionally exposes only nginx;
the app, worker, and scheduler share the application image and remain private
to the Docker network.

For an immutable release, set one image identifier for the entire stack. Do not
mix `main`, release tags, or commit images between services:

```dotenv
MC_KIT_IMAGE_TAG=v1.0.0
```

The app, web, worker, and scheduler must all use that same tag (or the same
commit SHA/digest). The publishing workflow publishes both images for semver
Git tags such as `v1.0.0` and also preserves SHA-based tags.

## Reverse proxy and HTTPS

The outer reverse proxy is responsible for TLS termination and must forward the
original request metadata to nginx. nginx passes these headers unchanged to
Laravel:

- `X-Forwarded-For`
- `X-Forwarded-Host`
- `X-Forwarded-Port`
- `X-Forwarded-Proto`

Laravel uses these forwarded headers to generate secure URLs and correctly
detect an HTTPS request after TLS has been terminated upstream. This is why the
application must only be reachable through a trusted reverse proxy in
production.

The proxy must overwrite, rather than trust, client-supplied forwarding headers.
Allowing a client to inject `X-Forwarded-*` values would make scheme and host
detection unreliable. Keep PHP-FPM inaccessible from public networks.

For a TLS deployment, set at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mckit.example.com
SESSION_SECURE_COOKIE=true
LOG_CHANNEL=stderr
DB_QUEUE_RETRY_AFTER=180
```

For an intentionally plain-HTTP environment, use an `http://` `APP_URL` and
set `SESSION_SECURE_COOKIE=false`. MC Kit does not force HTTPS in application
code; the effective scheme comes from the direct request or the trusted proxy
headers.

## Environment and secrets

Create the production `.env` outside the repository and restrict its file
permissions. Generate a unique `APP_KEY` for each installation. Do not commit
or expose any of the following:

- database credentials;
- AWS credentials used by the SES mail transport;
- the Sessionize endpoint URL;
- mail credentials;
- application keys or session cookies.

Set `MAIL_MAILER=ses` and the required AWS environment variables only in the
production environment. The example environment is deliberately configured for
local Mailpit instead.

The application uses MySQL for state shared by the app, worker, and scheduler:

```dotenv
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

Do not replace these with filesystem state or mount a shared application
storage volume. Runtime cache, compiled views, sessions, and logs are ephemeral
container directories; durable operational data belongs in MySQL.

## Database, workers, and backups

Before or during a controlled deployment, run migrations once from the
application image:

```bash
php artisan migrate --force
```

Keep the `worker` and `scheduler` processes running after deployment. Without
the worker, queued Sessionize synchronization cannot run. Monitor their logs
and restart policy through the platform running the Compose stack.

The worker intentionally uses the database queue:

```text
php artisan queue:work --sleep=1 --tries=3 --timeout=120
```

Keep `DB_QUEUE_RETRY_AFTER` greater than the worker timeout. The production
default is `180` seconds, leaving a 60-second safety margin before a running
job can become available for retry. Keep `LOG_CHANNEL=stderr` in production so
Laravel and queue failures are visible directly in Dockploy/container logs;
the local `.env.example` may continue using file-backed development logging.

Back up MySQL regularly and test restoration. The database contains the
conference schedule, user accounts, session preparation, notes, assignments,
queue jobs, sessions, and cache entries required by the application.

## Application and browser security

- Serve the application only over HTTPS in public production environments.
- Keep `APP_DEBUG=false`; exception details must not reach end users.
- Use a secure, HTTP-only session cookie and Laravel CSRF protection.
- Leave passwordless rate limits and enabled-user checks in place.
- Keep the administrative Sessionize endpoint private; the UI intentionally
  reports configuration state without revealing its value.
- Apply dependency updates through review and the existing CI checks.
- Run the published image instead of building source code at container startup.

The Quasar PWA stores an offline conference snapshot in IndexedDB. It stores no
authentication token. Logout clears local conference data, and a login by a
different user removes a mismatched snapshot before it can be displayed.

## Release checks

The continuous-integration workflow validates backend quality checks, the
frontend API-type contract, frontend linting/type tests/PWA build, and Docker
image smoke tests. The image smoke test verifies writable Laravel runtime
directories and confirms that the application can boot without a database
connection.

Before publishing a release, verify the production proxy passes the forwarding
headers above, the application URL and cookie settings match the public scheme,
database migrations have run, and the queue worker and scheduler are healthy.
