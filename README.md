# Stats SA Asset Management and Verification

Full application source for the Stats SA asset management solution, built on Snipe-IT v8.8.0.

## Start here

| I need to... | Open |
| --- | --- |
| Run the project | [Local setup](docs/getting-started.md) |
| Find a screen or form | [Page and module map](docs/pages/README.md) |
| Understand a role | [Role guides](docs/roles/README.md) |
| Find every registered route | [Web routes](docs/pages/web-routes.md) and [API routes](docs/pages/api-routes.md) |
| Change the design or interactions | [UI and workflow guide](docs/ui-and-workflows.md) |
| Understand the backend | [Architecture](docs/architecture.md) |
| Push changes or review work | [Contributing](CONTRIBUTING.md) |
| See what has been checked | [Validation](docs/validation.md) |

## Repository layout

```text
app/                 Controllers, models, policies, transformers and helpers
resources/views/     Pages and shared Blade components, grouped by feature
resources/lang/      Labels, messages and translations
routes/              Web and API routes
public/css/          Compiled styles plus Stats SA design layers
public/js/           Shared interactions, forms, motion and table filters
public/img/          Branding and interface artwork
config/              Application settings and permission definitions
database/            Migrations, factories and seeders
tests/               Feature and unit tests
docs/pages/          Page map, route catalogue and complete view inventory
docs/roles/          Seven role guides and permission references
```

Roles share feature pages. Authorization controls which pages and actions a user can access. Do not copy the same page into separate role folders.

Sign-in uses username and password. The account's existing permissions determine access; users cannot choose their own role at sign-in.

This repository contains source, not a running database. Local credentials, records, uploaded documents, logs and dependencies are excluded. Set up a fresh local database or arrange an authorised data transfer separately.

## Related project

The earlier [sign-in-only repository](https://github.com/luvuyoskhosana04/statssa-sso) is a separate handover. This full repository is the source of truth for ongoing full-application work.

## Origin and licence

Based on upstream Snipe-IT commit `8d10063af98aff0e3d5bb40aa56e6c5d732b5181`. See [upstream README](README.upstream.md), [licence](LICENSE) and [provenance](docs/provenance.md). Stats SA identity assets remain subject to their owners' rights.
