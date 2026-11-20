# Asset Manager

[All roles](README.md) | [Page map](../pages/README.md)

## Working area

Assets, models, assignments, audits and maintenance. All authenticated accounts also use shared account features where permitted.

- [hardware pages](../../resources/views/hardware)
- [models pages](../../resources/views/models)
- [maintenances pages](../../resources/views/maintenances)

## Reference permissions

The factory state `assetManager` in [UserFactory](../../database/factories/UserFactory.php) provides this reference role. Its sample username is `assetmgr`. These are test/demo fixtures, not accounts automatically created by this repository. No passwords or live records are exported.

- `assets.view`
- `assets.create`
- `assets.edit`
- `assets.delete`
- `assets.checkout`
- `assets.checkin`
- `assets.audit`
- `assets.view.requestable`
- `assets.view.encrypted_custom_fields`
- `assets.files`
- `models.view`
- `models.files`

## Implementation

- [Policies](../../app/Policies) enforce access to resources and actions.
- [Permission definitions](../../config/permissions.php) name the capabilities.
- [Users](../../app/Models/User.php) resolve account and group permissions.
- [Web routes](../pages/web-routes.md) locate the entry point and controller.
- [API routes](../pages/api-routes.md) locate the listing and transaction data endpoints.

Production accounts may have different group membership, explicit overrides and company scopes. Check effective permissions rather than trusting the display role name. Username/password sign-in authenticates the account without changing its permissions.

## Review when changing this area

Confirm permitted pages and actions work, unauthorised direct URLs and API requests are rejected, and company restrictions still apply. Check the same shared page with a permitted manager and a superuser. Never expose records by removing a policy check to make a navigation item work.

## Projects

The sidebar includes Projects for accounts with `assets.view`. Project creation requires `assets.create`; editing and reservations require `assets.edit`. Employee membership also requires `users.view`. Project Asset Issue requires `assets.view`, `assets.edit`, `assets.checkout` and `users.view`. The reference Asset Manager does not automatically gain directory access. Grant that capability explicitly when the role should manage project employees and issue equipment.

Projects are maintained locally. Reserved assets cannot be checked out through ordinary checkout routes until released. Issue sessions use the existing checkout and employee acceptance workflow, followed by explicit physical handover. Checkin remains the standard asset checkin flow. Run the project migration before using this module. Sessions belong to the issuing official; project history retains reservation and handover records.

## Barcode receiving

Receive new items requires `assets.view` and `assets.create`. A keyboard-wedge barcode scanner adds unregistered tags to a browser draft without server lookup. Barcodes become asset tags, preserving leading zeroes. Choose an existing model for the batch, with per-item overrides for mixed equipment. Save creates assets and receipt history together; retries use a request key to prevent duplicate batches. The operator remains signed in. No serial number or condition is inferred from a barcode. Receipt conditions are recorded as not assessed.

Source: [intake action](../../app/Actions/Assets/CreateReceivedAssetsAction.php), [request](../../app/Http/Requests/CreateReceivedAssetsRequest.php), [browser behaviour](../../public/js/asset-intake.js). Existing registered-asset receiving endpoints remain available for compatibility, while the receiving creation screen uses the new intake endpoint.
