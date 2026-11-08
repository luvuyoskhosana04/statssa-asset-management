# Contributing

## Work from this repository

```bash
git clone https://github.com/luvuyoskhosana04/statssa-asset-management.git
cd statssa-asset-management
git switch -c feature/short-description
# Make and check the change.
git add path/to/changed-file
git commit -m "Describe the change"
git push -u origin feature/short-description
```

Open a pull request into `main`. Describe the user-visible result, affected roles, checks run and known limitations. Include UI screenshots for visual changes. Use a separate branch for each change.

After a reviewed merge:

```bash
git switch main
git pull --ff-only
```

Public visibility allows anyone to read and clone. Direct pushing still requires collaborator access. Ask the repository owner to invite each developer by GitHub username, or fork and open a pull request.

## Before changing code

Read [AGENTS.md](AGENTS.md), `.ai/rules/index.md`, the [page map](docs/pages/README.md) and relevant [role guide](docs/roles/README.md). Keep existing paths stable. Do not split shared pages into role copies.

## Checks

Run the narrowest relevant tests against a separate test database, then review the changed page at desktop/mobile widths and with an appropriate restricted role. Format changed PHP with Pint. Update page/route documentation when destinations change.

Never commit `.env`, credentials, private keys, database dumps, uploaded files, vendor, node_modules or runtime logs. Do not use production data for tests.
