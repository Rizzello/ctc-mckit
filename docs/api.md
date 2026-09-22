# MC Kit API

The application exposes a versioned JSON API at `/api/v1`. It is intended for a
first-party browser client served from the same origin as Laravel.

## Authentication and CSRF

The API uses the normal Laravel session cookie. It does not use bearer tokens,
JSON Web Tokens, or browser-stored credentials.

State-changing requests require the Laravel CSRF token. A browser client must
send the `X-XSRF-TOKEN` header using the value of the `XSRF-TOKEN` cookie. The
session cookie and CSRF cookie are issued by the normal `web` middleware.

All authenticated endpoints require an enabled user. Administrators are enabled
users with `is_admin: true`.

## Response conventions

Single resources and collections returned by Laravel JSON Resources are wrapped
in a `data` member. The snapshot endpoint is intentionally unwrapped.

| Status | Meaning |
| --- | --- |
| `200` | Successful read or update |
| `201` | Resource created or synchronization queued |
| `204` | Successful deletion or logout |
| `401` | No authenticated session |
| `403` | Authenticated user lacks permission or is disabled |
| `404` | Resource does not exist |
| `422` | Request validation or domain validation failed |
| `429` | Request has been rate limited |

Validation errors use Laravel's JSON error structure. Clients must not infer
account existence from passwordless-authentication responses.

All datetimes are ISO-8601 strings with timezone information.

## Resources

### User

```json
{
  "id": 1,
  "name": "Alex Morgan",
  "email": "alex@example.test",
  "is_admin": false,
  "enabled": true
}
```

`email` is included for the current user and administrator user-management
responses. Session assignments use a smaller MC representation containing only
`id` and `name`.

### Room

```json
{
  "id": 1,
  "sessionize_id": "room-1",
  "name": "Main hall"
}
```

### Speaker

```json
{
  "id": 1,
  "sessionize_id": "speaker-1",
  "name": "Alex Morgan",
  "tagline": "Developer advocate",
  "bio": "...",
  "photo_url": "https://...",
  "links": []
}
```

### Conference session

```json
{
  "id": 1,
  "sessionize_id": "session-1",
  "title": "Building reliable systems",
  "description": "...",
  "room_id": 1,
  "starts_at": "2026-10-14T09:00:00+02:00",
  "ends_at": "2026-10-14T09:45:00+02:00",
  "status": "Accepted",
  "is_confirmed": true,
  "is_service_session": false,
  "is_plenum_session": false,
  "categories": ["Engineering"],
  "mc_description": "...",
  "mc_script": "...",
  "room": {},
  "speakers": [],
  "mcs": [],
  "notes": []
}
```

`mcs` contains `{ "id", "name" }`. Notes expose their body and timestamps,
but not their author identity. Imported Sessionize fields are read-only; only
`mc_description` and `mc_script` are mutable through the API.

## Passwordless authentication

| Method | Path | Authentication | Description |
| --- | --- | --- | --- |
| `POST` | `/auth/challenge` | Guest | Send a magic link and six-digit code if an enabled account exists |
| `POST` | `/auth/verify-otp` | Guest | Verify the code associated with the server-side challenge context |
| `POST` | `/logout` | Enabled user | Invalidate the current session |

`POST /auth/challenge` accepts:

```json
{ "email": "mc@example.test" }
```

It always returns the same success message for syntactically valid addresses.
Requests are limited by normalized email plus IP address and by IP address.

`POST /auth/verify-otp` accepts:

```json
{ "otp": "004921" }
```

The challenge is held in the server-side session. A code is single-use, expires
after ten minutes, and is invalidated after five failed code attempts. Successful
verification returns the authenticated user resource and regenerates the Laravel
session.

The magic link remains a browser route because it is opened from email. It
creates the same Laravel session and redirects to the authenticated application.

## Read endpoints

| Method | Path | Permission | Notes |
| --- | --- | --- | --- |
| `GET` | `/me` | Enabled user | Current user resource |
| `GET` | `/snapshot` | Enabled user | Complete active conference read model |
| `GET` | `/sessions` | Enabled user | Active sessions, ordered chronologically |
| `GET` | `/sessions/{session}` | Enabled user | One session and its relationships |
| `GET` | `/users` | Administrator | User management list |
| `GET` | `/sessionize` | Administrator | Configuration flag and safe sync status |

Administrators may pass `show_removed=true` to `GET /sessions` to inspect
removed sessions. Removed session details are administrator-only. They never
appear in the snapshot.

## Snapshot

`GET /snapshot` is the offline-client read model. It returns all active
conference information required to render the agenda, catalog, session detail,
and the current user's assigned Live view in one response.

```json
{
  "version": "...",
  "generated_at": "2026-10-14T08:30:00+02:00",
  "current_user": {},
  "rooms": [],
  "speakers": [],
  "sessions": []
}
```

`version` is stable while client-relevant conference data is unchanged. It
changes when sessions, rooms, speakers, notes, or MC assignments change.
`generated_at` records the response generation time and is not a revision.

The snapshot includes active sessions only. It contains no authentication
credentials, login challenges, hash values, or removed sessions.

## Mutations

| Method | Path | Permission | Body |
| --- | --- | --- | --- |
| `PATCH` | `/sessions/{session}/mc-content` | Enabled user | `mc_description`, `mc_script` |
| `POST` | `/sessions/{session}/notes` | Administrator | `body` |
| `PATCH` | `/notes/{note}` | Administrator | `body` |
| `DELETE` | `/notes/{note}` | Administrator | None |
| `POST` | `/sessions/{session}/mcs` | Administrator | `user_id` |
| `DELETE` | `/sessions/{session}/mcs/{user}` | Administrator | None |
| `POST` | `/users` | Administrator | `name`, `email`, `is_admin`, `enabled` |
| `PATCH` | `/users/{user}` | Administrator | `name`, `email`, `is_admin`, `enabled` |
| `POST` | `/sessionize/sync` | Administrator | None |

Notes always retain their server-side author. Clients cannot set or replace a
note author. An assignment may target any enabled user; there is no separate MC
role. User updates preserve the invariant that at least one enabled
administrator remains.

The Sessionize status response exposes only whether configuration is present and
safe synchronization run status. It never returns the configured endpoint.

## Client integration guidance

Use `/snapshot` to initialize an offline client model, keyed to the current
user ID and snapshot version. On reconnect, fetch a fresh snapshot before
merging or replacing local read-only data. All writes must be sent online using
the endpoints above and treated as authoritative only after a successful server
response.

Do not store session cookies, CSRF tokens, magic-link values, OTPs, or any
authentication token in local storage or IndexedDB.
