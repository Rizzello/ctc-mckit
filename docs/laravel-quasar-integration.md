# Laravel and Quasar integration

MC Kit is one application deployed on one domain. Laravel owns the backend;
the Quasar PWA is the only application interface.

## Responsibilities

Laravel remains authoritative for authentication, authorization, validation,
domain actions, Sessionize synchronization, queues, and MySQL persistence.
It exposes the versioned JSON API under `/api/v1`.

Quasar renders the user interface and consumes that API through the typed client
in `frontend/src/services/api`. It never reimplements backend authorization or
stores authentication tokens.

## Authentication and requests

The frontend uses Laravel's first-party session cookie and CSRF protection.
Before a state-changing API request, the client obtains the CSRF cookie from
`/csrf-cookie`, then sends the `X-XSRF-TOKEN` header with the request.

Passwordless sign-in remains server-owned:

1. Quasar asks Laravel to send a challenge.
2. The user submits the received OTP through the API.
3. A magic link is verified by Laravel, which creates the session and redirects
   to the SPA without retaining the one-time credential in the address bar.
4. Logout is an online API request followed by removal of local conference data.

No JWT, bearer token, localStorage token, or IndexedDB credential is used.

## Data and offline behavior

`GET /api/v1/snapshot` is the read model for the conference interface. The
conference Pinia store hydrates from Dexie/IndexedDB first, then refreshes from
the API when a connection is available.

The saved snapshot has an owner identifier. A login by a different user clears
the previous local snapshot before rendering the new user's data. A failed
refresh leaves the last valid snapshot untouched.

Offline use is read-only. The PWA shell is provided by Workbox, while conference
data lives in IndexedDB. Mutations are disabled offline and are never queued or
replayed later.

## API typing

The frontend's generated types live in `frontend/src/types/generated/api.ts`.
They are derived from `docs/openapi.yaml`:

```bash
vendor/bin/sail exec frontend npm run generate:api
vendor/bin/sail exec frontend npm run check:api
```

Use `check:api` in validation because it fails when the generated file has
drifted from the API contract.

## Local development

Sail starts Laravel, MySQL, Mailpit, and the Quasar Vite server. Open the SPA
at `http://localhost:9000/login`. The development server proxies `/api` and
`/csrf-cookie` to Laravel, preserving same-origin session and CSRF behavior in
the browser.

The main local commands are:

```bash
vendor/bin/sail up -d
vendor/bin/sail exec frontend npm run lint:check
vendor/bin/sail exec frontend npm run typecheck
vendor/bin/sail exec frontend npm run test
vendor/bin/sail exec frontend npm run build:pwa
```

## Production build and routing

The Docker build uses a Node build stage to run `npm ci` and
`npm run build:pwa`. The resulting Quasar assets, manifest, and Workbox service
worker are copied into Laravel's `public` directory. Node is not present in the
runtime image.

nginx serves static assets and falls back to `index.html` for client routes such
as `/agenda`, `/sessions`, `/live`, and `/admin/*`. It forwards API, CSRF,
logout, magic-link, Sanctum, and health requests to Laravel/PHP-FPM instead.
This keeps client-side history routing separate from backend endpoints.

The generated Workbox service worker owns the application shell. During its
activation it removes only obsolete MC Kit cache namespaces from the earlier
offline implementation; it does not remove unrelated origin caches.
