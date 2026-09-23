# MC Kit

[![Continuous integration](https://github.com/Rizzello/ctc-mckit/actions/workflows/ci.yml/badge.svg)](https://github.com/Rizzello/ctc-mckit/actions/workflows/ci.yml)
[![Dependabot Updates](https://github.com/Rizzello/ctc-mckit/actions/workflows/dependabot/dependabot-updates/badge.svg)](https://github.com/Rizzello/ctc-mckit/actions/workflows/dependabot/dependabot-updates)
[![Publish images](https://github.com/Rizzello/ctc-mckit/actions/workflows/publish.yml/badge.svg)](https://github.com/Rizzello/ctc-mckit/actions/workflows/publish.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**Everything an MC needs to run the room.**

MC Kit gives conference teams a shared agenda, session preparation, live
delivery support, and administration tools. It is a mobile-first, offline-first
web application: the conference snapshot remains readable without a connection,
while all changes continue to be authorized and persisted by Laravel when
online.

## Highlights

- Complete agenda, session catalogue, preparation material, and personal Live view.
- Passwordless sign-in with a magic link and six-digit one-time code.
- Sessionize schedule synchronization with protected administrative controls.
- Read-only offline access backed by an IndexedDB conference snapshot.
- Quasar PWA frontend, Laravel JSON API, MySQL persistence, and database-backed
  sessions, cache, and queues.

## Architecture

Laravel is the authoritative backend for authentication, authorization, domain
actions, Sessionize synchronization, queues, and persistence. The Quasar PWA
is the single application interface and consumes the versioned API at
`/api/v1` using Laravel's first-party session and CSRF protection.

```text
Quasar PWA → Laravel JSON API → domain actions → MySQL
```

The frontend stores its last valid conference snapshot in IndexedDB. It never
stores authentication credentials and never queues offline writes.

## Documentation

- [Local development with Sail](docs/development-with-sail.md)
- [Laravel and Quasar integration](docs/laravel-quasar-integration.md)
- [API contract](docs/api.md)
- [Production deployment and security](docs/production-deployment.md)

## Quick start

Copy the environment file, then start the local stack:

```bash
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail composer setup
```

Open the Quasar development application at
[`http://localhost:9000/login`](http://localhost:9000/login). Mailpit is
available at [`http://localhost:8025`](http://localhost:8025).

See the [Sail guide](docs/development-with-sail.md) for passwordless-login
testing, queues, frontend checks, and the complete development command list.

## Quality and delivery

Continuous integration validates the Laravel backend, the Quasar frontend, and
the production Docker images. The publish workflow builds the application and
nginx images for `main` and version tags. Dependabot keeps dependency updates
visible for review.

The frontend's generated API types are checked against
[`docs/openapi.yaml`](docs/openapi.yaml) in CI. Run the documented Sail and
frontend checks before opening a pull request.

## License

MC Kit is released under the [MIT License](LICENSE).

## Credits

The MC Kit logo is based on the microphone icon from [Dinkie Icons](https://github.com/atelier-anchor/dinkie-icons), created by [atelierAnchor](https://github.com/atelier-anchor), a graphic and typeface design studio in Shanghai.
