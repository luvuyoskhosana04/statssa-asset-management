# Bulk Checkout legacy comparison

Inspected the previous system in Chrome on 22 November 2026 at `/hardware/bulkcheckout`. No legacy checkout was submitted and its existing drafts were preserved.

## Behaviour implemented

| Previous system | Current implementation |
| --- | --- |
| User, Location and Project destinations | Separate tabs with separate unsaved item queues and source selections |
| Scan tag or serial, Enter or Add | Exact identifier lookup with duplicate and unavailable stock checks |
| Mixed assets and accessory quantities | Shared queue, stock table, quantity controls and remaining-after-session counts |
| Any stock, order number or supplier | Source filters apply to scan, stock picker and final server checks; Project requires order or supplier |
| User checkout | Standard asset checkout and accessory allocation, with normal checkout events |
| Project allocation for a user | Optional project, membership/date validation, project reference in the batch record and checkout notes |
| Location dispatch | Required source office and different destination; assets become In Transit while their physical location is preserved; accessories are held for the destination |
| Project reservation | Assets use the existing project reservation pivot/status without physical movement or assignment to a person; accessories are held against the project |
| Batch and individual condition/status | Batch defaults and per-item overrides; dispatch/reservation statuses are automatic |
| Session summary and confirmation | Recipient, asset count, accessory units and explicit review checkbox before saving |
| Existing allocations | Collapsible current-assignment panel below the review, subject to view permissions |

On wide screens, Bulk Checkout uses recipient and item selection beside a persistent checkout summary. Narrow screens stack the panels. The screen uses the shared neutral, sage, sand and bronze theme, shared tabs and pill buttons. No additional icon library was introduced.

## Integrity and audit

New batches validate every item before writes, lock inventory rows, and run in a transaction. A UUID and request fingerprint make an unchanged retry return the original result instead of allocating twice. A changed payload cannot reuse an already committed session UUID. The batch record captures item identifiers, quantities, condition, source, original status, issuer and destination. Receipts are visible to their issuer and superusers with checkout access.

The existing POST bulk asset checkout endpoint remains available for backward compatibility. The new screen uses `/hardware/bulkcheckout/session`.

## Limits and differences to retain explicitly

- The local User model has no reliable employment-type field. Contractor-specific mandatory project allocation cannot be reproduced accurately yet. Active-account, end-date and selected-project membership checks are enforced.
- Accessory acquisition history does not identify the source lot of historical checkouts. Order/supplier availability therefore uses a conservative deduction; it does not claim exact historical lot tracking.
- Location dispatch does not confirm receipt. This change preserves physical asset locations and does not add a physical receiving workflow for dispatched accessories.
- Project accessory reservations use the existing polymorphic accessory allocation ledger. This change does not add a separate project accessory release interface.
- User project allocation is recorded on the batch and checkout notes. It does not create a separate Project Asset Issue acceptance/handover session.
- Item queues are held in the open page, with a warning before leaving unsaved items. This change does not introduce server-side draft persistence.
- The picker shows up to 60 matches and supports search/source refinement. The current-assignment disclosure shows up to 100 assets and 100 accessory types.

## Validation

28 feature tests passed, 117 assertions, including existing bulk checkout regressions. New coverage includes mixed batches, duplicate rows and retries, complete rejection on shortages, source restrictions, inactive/ended recipients, project membership, physical-location preservation, accessory project reservations and private receipt access. Tests ran against the isolated `statssa_role_tests` database.

Browser checks staged items only, without submitting checkouts. Checked scanner duplicates, quantity totals, separate tab queues, stock browsing, current assignments, review gating, inline clear confirmation, hidden required fields, tab selection, dark theme and narrow viewport layout.

Deployment requires the additive `2026_11_22_181143_create_bulk_checkout_batches_table.php` migration. It has been applied to the local preview database.
