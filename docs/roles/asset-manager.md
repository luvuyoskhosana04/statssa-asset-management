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

The sidebar includes Projects for accounts with `assets.view`. Projects and employee membership are read-only in this application. The source integration is not connected yet, so the directory uses local demo records. Project Asset Issue requires `assets.view`, `assets.edit`, `assets.checkout` and `users.view`. The reference Asset Manager does not automatically gain directory access.

Select a project, then identify an employee using the combined barcode and searchable ID/name dropdown. Scan an inventory asset, set its return date, review the details and declare physical handover. Final confirmation records the issue, its audit snapshot and the employee acceptance request. Pending acceptance blocks another issue of the same asset. Rejection releases it; acceptance keeps it assigned. Employee acceptance uses the recipient's own account. Mail delivery requires configured transport; the local demo records notices in the mail log.

Run the project migrations before using the module. Sessions belong to the issuing official, while authorised officials can investigate issue history. Checkin uses the standard asset checkin flow.

## Barcode receiving

Receive new items requires `assets.view` and `assets.create`. Scan the original asset barcode first, then scan a different printed Stats SA barcode in the awaiting-tag row. The first code becomes the previous identity/serial and the second becomes the new Stats SA asset tag. Leading zeroes are preserved. Existing or duplicate codes show a red alert. After both scans, the item moves to Received Assets. Manual entry supports the same two identities. No asset model selection is required; newly received assets use internal unclassified metadata until enriched later.

Save and continue later stores both unfinished and tagged items in the signed-in account. Receipt history and Drafts are tabs in the table header. Drafts can be resumed or deleted. Cancelling unsaved edits preserves an earlier saved draft. Older browser drafts can be recovered into the account. End session creates the tagged assets and receipt history atomically; retries do not duplicate the batch. The operator remains signed in. Receipt conditions are recorded as not assessed.

Source: [intake action](../../app/Actions/Assets/CreateReceivedAssetsAction.php), [request](../../app/Http/Requests/CreateReceivedAssetsRequest.php), [browser behaviour](../../public/js/asset-intake.js). Existing registered-asset receiving endpoints remain available for compatibility, while the receiving creation screen uses the new intake endpoint.

Scanner status uses browser-authorised WebHID barcode devices when available. Recognised scanners show their reported product name, with Ready, Paused or Disconnected states. Identify scanner opens the browser device chooser only after a user click. Keyboard-mode scanners show Scanner ready when input capture is ready. Missing browser device identification is not a scanner error. A confirmed disconnect shows red; incoming barcode input restores readiness. Device names are shown only when reported by the browser.
