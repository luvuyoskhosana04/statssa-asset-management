# User Manager

[All roles](README.md) | [Page map](../pages/README.md)

## Working area

People, profiles and user records. All authenticated accounts also use shared account features where permitted.

- [users pages](../../resources/views/users)

## Reference permissions

The factory state `userManager` in [UserFactory](../../database/factories/UserFactory.php) provides this reference role. Its sample username is `usermgr`. These are test/demo fixtures, not accounts automatically created by this repository. No passwords or live records are exported.

- `users.view`
- `users.create`
- `users.edit`
- `users.delete`
- `users.files`

## Implementation

- [Policies](../../app/Policies) enforce access to resources and actions.
- [Permission definitions](../../config/permissions.php) name the capabilities.
- [Users](../../app/Models/User.php) resolve account and group permissions.
- [Web routes](../pages/web-routes.md) locate the entry point and controller.
- [API routes](../pages/api-routes.md) locate the listing and transaction data endpoints.

Production accounts may have different group membership, explicit overrides and company scopes. Check effective permissions rather than trusting the display role name. Username/password sign-in authenticates the account without changing its permissions.

## Review when changing this area

Confirm permitted pages and actions work, unauthorised direct URLs and API requests are rejected, and company restrictions still apply. Check the same shared page with a permitted manager and a superuser. Never expose records by removing a policy check to make a navigation item work.
