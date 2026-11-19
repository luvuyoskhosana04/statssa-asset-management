# Join the Stats SA project

The full project lives in [statssa-asset-management](https://github.com/luvuyoskhosana04/statssa-asset-management). The earlier `statssa-sso` repository is a separate sign-in handover. Use this full repository for new application work.

## Access and ownership

The repository stays under `luvuyoskhosana04`. Accept the GitHub collaborator invitation while signed in to your own GitHub account. Use your own GitHub and Codex accounts; do not share passwords or tokens. Collaborator access lets you push feature branches. The repository owner reviews changes before they enter the shared branches.

Public visibility allows reading and cloning immediately. Accepting the invitation is required for pushing branches directly to this repository. The invitation may expire, in which case the owner must resend it.

## First setup

1. Accept the GitHub invitation and install Git, Python 3, Docker Desktop and Codex.
2. Clone the full repository, select development, and create your own branch:

```bash
git clone https://github.com/luvuyoskhosana04/statssa-asset-management.git
cd statssa-asset-management
git switch development
git pull --ff-only origin development
git switch -c feature/dima-first-task
```

3. Open this cloned folder as a project in Codex. Sign into GitHub with your own account when Git or GitHub Desktop requests authentication.
4. Ask Codex to read `AGENTS.md`, this guide, `docs/getting-started.md`, the page map and the relevant role guide.
5. Start the application using the [Docker setup](getting-started.md). The initializer creates private local keys and database passwords. Complete the setup wizard to create your own local administrator.

A fresh clone contains code and artwork, not the owner's records, uploads, local environment, installed dependencies or current chat history. Each person runs their own local application and database. Rebuilding Docker is required after editing files copied into the image.

## Local role selector

Normal fresh installs use username and password. The design demo has an optional role dropdown. To use it, set `DEMO_ROLE_LOGIN=true` in your untracked local `.env`, keep `APP_URL` set to localhost, and recreate the application container. It uses these existing account usernames:

| Role | Demo username |
| --- | --- |
| Superuser | admin |
| Asset Manager | assetmgr |
| License Manager | licensemgr |
| Accessory Manager | accessorymgr |
| Consumable Manager | consumablemgr |
| Component Manager | componentmgr |
| User Manager | usermgr |

These accounts are not automatically created. Configure their real permissions through the application before using the demo. Missing or inactive accounts show an error. The server rejects this password-free mode for non-local application URLs. Keep `DEMO_ROLE_LOGIN=false` on shared hosts and production.

## Daily work in Codex

Start each separate change from current development with a clean working tree. If you have uncommitted work, ask Codex to preserve it before switching branches. Use a branch such as `feature/dima-import-validation` or `fix/profile-layout`.

Paste this prompt into a new task:

> Read AGENTS.md, CONTRIBUTING.md and docs/team-onboarding.md. Check for existing work, fetch origin, and create a feature branch from origin/development. Implement the task I describe, follow the existing Stats SA UI and role permissions, and run the relevant checks using a separate test database. Review the diff for credentials and runtime files. Commit and push only this task's files to the feature branch. Open a pull request into development with validation results and screenshots for UI changes. Do not merge or deploy.

Finish the pull request with a short description of what changed, pages and roles affected, checks run and anything still unverified. Request `luvuyoskhosana04` as reviewer. GitHub permissions and protected-branch rules enforce the workflow; instructions alone are not an access control.

## Review and receive each other's changes

The owner reviews the pull request, asks for corrections if necessary, and merges approved work into development. Pull the merged result into a clean checkout:

```bash
git switch development
git pull --ff-only origin development
```

If your running design preview is in a separate checkout, pulling this repository does not update that preview. Ask Codex to compare the checkouts and synchronise reviewed source changes while preserving local environment files, runtime overrides and uncommitted work.

Rebuild the local application when source changed. Run migrations only when the reviewed change requires them. Pulling code does not synchronise databases or uploaded files and does not deploy the AWS environment.

To inspect an unmerged branch, fetch it and check it out separately. Do not merge it into your own task branch just to preview it. Split tasks by feature to reduce editing conflicts, and resolve conflicts against current development before merging.

## Shared context

Use the checked-in page map, role guides and UI workflow guide as common context. Update those files when a task changes the structure or expected behaviour. Keep task discussion and decisions with the issue or pull request so both Codex sessions can read the same record.

Each person has separate Codex chats. A shared chat snapshot supplies background only; it is not a live, jointly editable project or access to another person's machine. The repository is the shared source of truth.

## Before your first pull request

- Confirm you can push your own feature branch after accepting the invitation.
- Run the local setup and open the relevant page.
- Follow `.ai/rules/index.md` and the [validation guide](validation.md).
- Keep `.env`, credentials, database dumps, uploads and generated dependencies out of commits.
- Include only the intended changes. Do not force-push shared branches or delete shared data.
