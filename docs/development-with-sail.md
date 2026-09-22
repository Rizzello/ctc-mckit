# Local development with Sail

MC Kit uses a Laravel API with a Quasar PWA frontend:

| Interface | Address | Purpose |
| --- | --- | --- |
| Laravel API | `http://localhost` | Backend, API, database-backed session, and Mailpit integration |
| Quasar SPA | `http://localhost:9000/login` | Application frontend under development |
| Mailpit | `http://localhost:8025` | Local passwordless-login email inbox |

The Quasar development server proxies API and CSRF-cookie requests to Laravel,
so browser requests use the normal Laravel session and CSRF cookies.

## Starting the environment

Start Laravel, MySQL, Mailpit, and the Quasar development server:

```bash
vendor/bin/sail up -d
```

The `frontend` service runs Quasar Vite on port `9000` with hot module
replacement. Source files are bind-mounted from `frontend/`, so changes under
`frontend/src` are reflected without rebuilding the container.

If only the frontend service needs starting:

```bash
vendor/bin/sail up -d frontend
```

Follow its output with:

```bash
vendor/bin/sail logs -f frontend
```

The port can be changed locally with `FORWARD_FRONTEND_PORT`:

```dotenv
FORWARD_FRONTEND_PORT=9001
```

In that case the SPA is available at `http://localhost:9001/login`.

## Testing passwordless login

Use a pre-created enabled user at the Quasar login page. The development email
is delivered to Mailpit, where the six-digit code can be copied into the SPA.
Magic links work locally too: Laravel completes the sign-in, then redirects to
the Quasar development server configured by `FRONTEND_URL` (port `9000` by
default). After changing `FORWARD_FRONTEND_PORT`, restart Sail so Laravel
receives the corresponding value.

## Laravel commands

Run PHP, Artisan, and Composer commands through Sail. For example:

```bash
vendor/bin/sail artisan migrate
vendor/bin/sail artisan test --compact
vendor/bin/sail composer analyse
vendor/bin/sail bin pint --format agent
```

The Composer scripts provide the usual project checks:

| Command | Purpose |
| --- | --- |
| `vendor/bin/sail composer test` | Clears configuration and runs the PHPUnit suite. |
| `vendor/bin/sail composer lint` | Checks PHP formatting with Pint without changing files. |
| `vendor/bin/sail composer analyse` | Runs Larastan/PHPStan. |
| `vendor/bin/sail composer quality` | Runs formatting check, static analysis, and tests. |
| `vendor/bin/sail composer ci` | Runs `quality` followed by Composer security audit. |
| `vendor/bin/sail composer demo:data` | Seeds the local demonstration schedule. |

`vendor/bin/sail composer setup` is intended for a fresh local checkout: it
installs PHP dependencies, creates `.env` when needed, generates the
application key, and migrates the database. Install and build the Quasar
workspace through the frontend commands below.

Use Sail to inspect or control the local services as well:

```bash
vendor/bin/sail ps
vendor/bin/sail logs -f laravel.test
vendor/bin/sail stop
```

The database queue is not started automatically by Compose. To process jobs
such as a Sessionize synchronization locally, run this in a separate terminal:

```bash
vendor/bin/sail artisan queue:work
```

## Frontend checks

Pint formats PHP only. The Quasar workspace owns its own Prettier and ESLint
checks, plus TypeScript validation and the PWA build:

```bash
vendor/bin/sail exec frontend npm run check:api
vendor/bin/sail exec frontend npm run lint:check
vendor/bin/sail exec frontend npm run typecheck
vendor/bin/sail exec frontend npm run test
vendor/bin/sail exec frontend npm run build:pwa
```

`npm run lint` is the fixing command; it writes Prettier and ESLint fixes.
`npm run lint:check` is read-only and is the command appropriate for CI.
`npm run generate:api` regenerates frontend API types from
`docs/openapi.yaml`; `npm run check:api` additionally fails if regeneration
would change the committed generated types.

For a full frontend validation pass, run the commands above in that order. The
production build command is `npm run build:pwa`; `npm run build` is available
for a non-PWA Quasar build but is not the deployment artifact.

## Development-only differences

The `frontend` Compose service is a local Vite development server. It is not a
second production deployment. Production builds the Quasar PWA during the
image build and serves it from the same application domain as Laravel.

The frontend container installs dependencies with `npm ci` when it starts. The
application source remains in the repository; generated dependency, build, and
Quasar working directories are ignored.

See [Laravel and Quasar integration](laravel-quasar-integration.md) for the
runtime architecture, authentication flow, API contract, offline data model,
and production build layout.
