# Local development with Sail

MC Kit currently serves two user interfaces during the frontend migration:

| Interface | Address | Purpose |
| --- | --- | --- |
| Laravel / Livewire | `http://localhost` | Existing application |
| Quasar SPA | `http://localhost:9000/app/login` | New application under development |
| Mailpit | `http://localhost:8025` | Local passwordless-login email inbox |

The Quasar application is mounted under `/app` to keep it separate from the
existing Laravel interface. It proxies `/api` and `/login` requests to Laravel,
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

In that case the SPA is available at `http://localhost:9001/app/login`.

## Testing passwordless login

Use a pre-created enabled user at the Quasar login page. The development email
is delivered to Mailpit, where the six-digit code can be copied into the SPA.
The email magic link still opens Laravel directly; the OTP is the simplest way
to continue testing the SPA during local development.

## Laravel commands

Run PHP, Artisan, Composer, and root Node commands through Sail. For example:

```bash
vendor/bin/sail artisan migrate
vendor/bin/sail artisan test --compact
vendor/bin/sail composer analyse
vendor/bin/sail bin pint --format agent
vendor/bin/sail npm run build
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
installs PHP and root frontend dependencies, creates `.env` when needed,
generates the application key, migrates the database, and builds the legacy
Laravel frontend assets.

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

## Development-only differences

The `frontend` Compose service is a local Vite development server. It is not a
second production deployment and it does not replace Laravel, nginx, or the
existing Livewire interface. The production-image integration for the Quasar
build is a separate migration step.

The frontend container installs dependencies with `npm ci` when it starts. The
application source remains in the repository; generated dependency, build, and
Quasar working directories are ignored.
