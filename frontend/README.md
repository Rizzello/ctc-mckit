# MC Kit frontend

The Quasar PWA is the production application interface. Laravel provides its
same-origin JSON API and session authentication.

For local development, start Sail from the repository root and open
`http://localhost:9000/login`:

```bash
vendor/bin/sail up -d
```

Run frontend checks through the Sail frontend service:

```bash
vendor/bin/sail exec frontend npm run check:api
vendor/bin/sail exec frontend npm run lint:check
vendor/bin/sail exec frontend npm run typecheck
vendor/bin/sail exec frontend npm run test
vendor/bin/sail exec frontend npm run build:pwa
```

`npm run generate:api` updates the generated API types from
`../docs/openapi.yaml`. The production Docker build runs the PWA build and
serves its output from the application domain.
