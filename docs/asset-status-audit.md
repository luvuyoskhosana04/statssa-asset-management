# Asset status audit

Compared the authenticated legacy Status Labels catalogue at https://statssa-dev.aws.sandbox.invokesolutions.co.za/statuslabels on 22 November 2026 with the local database. Local data had eight general labels and lacked all ten operational labels shown below.

| Restored operational label | Verified legacy type |
| --- | --- |
| Awaiting Receipt | Pending |
| Awaiting Inspection | Pending |
| Preparing for Deployment | Pending |
| Available | Deployable |
| In Transit | Deployable |
| Awaiting User Handover | Pending |
| Assigned / In Use | Deployable |
| On Hold | Pending |
| Unavailable - Damaged | Undeployable |
| Reserved - Project | Deployable |

Pending and Archived were already represented. The legacy placeholder label named test was excluded from the operational catalogue. Local general labels, including Ready to Deploy, repair conditions and Pending Validation, were preserved. The legacy default is Awaiting Receipt; the local default was deliberately preserved because this task restores missing choices rather than changing receiving defaults or reclassifying existing inventory.

OperationalAssetStatusSeeder restores missing labels and exposes them in the existing All Assets navigation group. It does not overwrite existing label types, default choices or asset records. It can be run repeatedly without duplicates. The local catalogue now contains 18 labels; Deployed remains an assignment-derived state, so the status filter also includes that choice.

The inventory table and column filter previously flattened every assigned asset to Deployed. Recognised operational names now remain visible and filter by their actual names. General existing labels retain the previous Deployed behavior. Status names wrap inside narrow columns. Filter menus include unused labels so operators can discover the complete catalogue, even when a status has zero matching assets.

Bulk location dispatch reuses the restored In Transit label instead of trying to create another label with conflicting flags. Assignment to the destination still prevents another checkout, and physical location stays unchanged until receipt. Project-specific reservation workflows and historical assignments were not rewritten.

Validation: 30 status, table filter and bulk checkout tests passed (214 assertions). Coverage includes repeatable restoration, preservation of existing asset status/defaults, catalogue search, unused labels, assigned In Transit filtering, destination assignment and dispatch eligibility. Browser verification confirmed all 19 filter choices. No asset assignments or inventory records were changed during the audit.
