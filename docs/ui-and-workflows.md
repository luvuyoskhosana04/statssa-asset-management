# UI and workflows

## Shared styling

| File | Responsibility |
| --- | --- |
| `public/css/dist/all.css` | Base compiled theme |
| `public/css/interface-refresh.css` | Shared layout, cards, navigation, tables and form fitment |
| `public/css/stats-sa-theme.css` | Stats SA tones, semantic colours and component overrides |
| `public/css/icon-states.css` | Outline and filled icon states |
| `public/css/welcome.css` | Public sign-in layout and intro animation |

Inspect stylesheet order in `resources/views/layouts/default.blade.php` and `layouts/welcome.blade.php` before adding overrides. CSS changes in public files do not require a Mix build. Changes in compiled source assets do.

## Form page layout

Forms use the full available page width. The shared `x-container` and `x-page-column` components expose `ui-page-layout` and `ui-page-column` so shared styles remove outer column limits for form pages without changing dashboard or record-view grids. Form rows, guidance and checkbox rows share a centred field column capped at 800 pixels. On smaller screens they fill the available width. Parent cards and supporting disclosures stay full width.

Place supporting cards, current assignments and scanner results after the main form using `x-form.support-panel`. Its native disclosure has the shared trailing chevron and keyboard controls. Keep record IDs and Livewire roots inside the disclosure so existing updates continue to work. Scanner result disclosures remain hidden until their original result panel becomes visible. Do not restore side-by-side form and support columns on new workflow pages.

## Shared interactions

| File | Responsibility |
| --- | --- |
| `public/js/interface-presentation.js` | Page presentation, grouped controls, common component behaviour |
| `public/js/interface-motion.js` | Reveal and interaction motion with reduced-motion handling |
| `public/js/form-stepper.js` | Long-form stages and review |
| `public/js/settings-tabs.js` | Settings section tabs |
| `public/js/table-column-filters.js` | Column filtering controls |
| `public/js/table-pagination.js` | Table pagination presentation |
| `public/js/status-overview.js` | Status chart presentation |
| `app/Helpers/TableColumnFilters.php` | Server-side table filter logic |

## Table toolbars

Shared Bootstrap Table toolbars keep search and the primary create action visible. Group saved views, columns and status filters under **View & filters**. Group refresh, export, print, fullscreen and other secondary utilities under **Table tools**. Preserve the original controls and permission checks when grouping them, including export format choices and column checkboxes. Active status filters show a count beside the view menu.

Menus support Escape and outside-click dismissal. Only one toolbar menu opens at a time, nested dropdowns reset on close, and panels stay within the viewport on narrow screens. Keep this pattern in the shared presentation script rather than adding page-specific button rows.

## Workflows to preserve

- Normal credentials sign-in: `auth/login.blade.php`, `Auth/LoginController.php`, `auth/partials/` and the welcome layout. Existing account permissions control access.
- Asset, license and user creation: feature edit views and `form-stepper.js`. Review final values before submission and preserve values on Back.
- Import: `app/Livewire/Importer.php` and `resources/views/livewire/importer.blade.php`. Keep type, mapping and preview separate. Cancellation must reflect what has actually completed.
- Inventory: feature index view, API controller and transformer. Preserve identifier columns, explicit assignment context and checkin/checkout actions.
- Settings: `resources/views/settings/`, category landing page and section tabs.
- Profile: `resources/views/account/profile.blade.php`, existing profile controller and upload validation.

## UI review

Check desktop and narrow mobile widths, keyboard navigation, visible labels and errors, reduced motion, light/dark themes, empty/loading states and long values. Preserve bronze primary actions, blue record links and red destructive/error states. Do not use colour alone for status.

The optional local demo uses `auth/role-login.blade.php` and `auth.demo_role_login`. It is disabled by default and rejected unless the configured application URL is localhost. It selects existing demo accounts; it does not create accounts or change permissions.

## Shared field experience

`public/js/form-field-experience.js` and the shared theme provide inset labels, muted examples, `(Optional)` labels and filled green validation ticks. Native validation and the configured jQuery rules run during editing. Project employee and asset inputs use `data-field-verification` with `pending`, `valid` and `invalid` states; their lookup handlers emit `field:verified` after success. Dynamic and Select2 controls use the same presentation.

Estimated completion time appears at the start of data entry forms. General estimates use the field count and type, excluding permission matrices. Issue and receiving flows have explicit estimates per employee or asset. A form may supply `data-form-estimate` to override the general estimate. Keep estimates approximate and update them when a workflow changes.

Use `data-no-field-ui` for scanner capture sinks and other controls whose presentation is managed by the workflow. Header searches, table filtering and permission grids keep their specialised controls. Field enhancement preserves field names, values and form submission routes.

## Project portfolio analytics

`/projects?tab=analytics` shows the All Project Analytics tab beside the directory. `BuildProjectAnalyticsAction` uses the current project and asset company scopes, keeps currencies separate, and uses the latest valid purchase-order line before the asset purchase cost, matching the dashboard basis. Awaiting acceptance is shown separately from issued assets. Pending returns are currently issued assets, and the risk value includes those still outstanding after their project end date.

Project accessories, loss, damage and transit do not have allocation tracking in this local schema. The analytics view labels these as not tracked, excludes them from calculated totals, and reports missing asset costs without estimating them. Counts cover all visible allocated assets while monetary values use the selected currency. No remote reference records are imported.

## Stats SA product branding

Runtime display names, page titles, setup, translated UI copy, notification sender names, mail templates, PDF metadata and backup filenames use Stats SA Asset Management. The existing Stats SA crest supplies the SVG favicon, touch icon and legacy fallback logo assets. Fresh demo settings also use the Stats SA name. Private environment values and the current installation settings are configured separately from Git.

Help links use the authenticated `/user-guide` page, and support links use the configured Stats SA support contact. Original source licences and attribution remain available at `/about/open-source` and `public/legal/LICENSE.txt`. Internal JavaScript names, custom-field keys, database/storage identifiers and CLI command names retain their established values for compatibility.

## Dashboard presentation

Dashboard-specific styles live in `public/css/dashboard-modern.css`. The desktop canvas uses an unequal 12-column grid: status spans eight columns, utilisation four, and the distribution panels four each. Section anchors provide Overview, Distribution and Purchase costs navigation. The active anchor tracks viewport position. Mobile uses one column. Upcoming attention, inventory and purchase intelligence form separate visual groups. Status uses one proportional composition with exact values in a legend; categories use a ring, locations use inspectable rounded columns, utilisation uses an SVG gauge with the native progress element retained for accessibility, and department costs use value tiles. Currency, expansion controls and underlying API scopes stay unchanged.

`dashboard-motion.js` reveals cards once as they enter the viewport. Chart entry animation runs only with no reduced-motion preference. Reduced-motion mode and print show content immediately. Keep actual values stable while animating visual marks. Do not invent trends or sample data to create decorative sparklines.

The dashboard presentation is scoped to `.dashboard-studio`. Surfaces, ink, focus states, bronze actions and sage/sand/stone chart series inherit the shared Stats SA `--ui-*` tokens. The dashboard does not override shell theme variables or force a mode. SVG marks, columns and readouts change immediately when the existing theme control updates `data-theme`. Location and monthly purchase columns support pointer inspection, button focus, arrow keys, Home and End; exact values appear in a tooltip anchored to the hovered, focused or tapped column. Mobile purchase columns keep 44-pixel targets inside a horizontally scrolling plot. The active column is brought into view without scrolling the document.

Monthly purchase bins use the selected year, or the most recent recorded year when All years is selected. Months without dated purchases show zero; undated acquisitions stay in the separate ledger and overall total. Negative adjustments retain their sign and extend below zero. Keep this distinction when extending the chart. The all-years acquisition value is not presented as the selected year's total.

## Navigation glass material

The shared theme defines `--ui-glass-nav`, `--ui-glass-panel` and `--ui-glass-header` from the existing surface, sage and theme tokens. Side navigation, the top bar and desktop flyouts use a restrained matte tint, 24-pixel backdrop blur and fine edges. Text and interactive states retain their normal contrast and semantic colours in both modes.

The sidebar applies blur to a background pseudo-element. Do not put a backdrop filter on the sidebar itself: that creates a backdrop root which prevents nested collapsed flyouts from blurring the page behind them. Body-mounted child flyouts filter their own backdrop. Mobile nested groups keep their inline presentation.

Unsupported backdrop filters, reduced transparency and forced colours use solid surfaces. Browser checks covered light/dark themes, expanded/collapsed navigation, keyboard child-panel access, the mobile drawer and the reduced-transparency fallback. No navigation timing, links or permission checks changed.

Dashboard column tooltips start hidden, follow the active bar and clear on pointer exit, blur, scrolling or Escape. Keyboard scrolling repositions the focused tooltip within the chart. Each column keeps its exact value in its accessible label. Do not restore a persistent readout above the plot. Category legends sit beneath the larger ring; summary counts lead their labels/context, footer arrows align at the right edge, and shared dashboard gutters are 10 pixels (8 on narrow screens). The selected bronze section link uses white text explicitly rather than inheriting the regular link colour.


## Shared actions and search controls

Header Quick actions groups the six catalogue viewing links and the existing permitted creation links. Pointer movement across the full flyout width and diagonal movement into either column retain the open panel. Escape, focus leaving and outside clicks dismiss it. Creation links retain the existing admin and per-type creation gates; viewing links use the catalogue gates.

The header asset lookup and standalone searches reveal from an icon. Opening or dismissing search preserves its query and table page. Search controls with an active query start expanded; a collapsed active query has a small indicator. Select2 combobox inputs retain their form-field interaction. Table clear-search actions are hidden and disabled for empty queries and clear only their own table state. Shared table toolbars place Create/Add after View & filters and Table tools in DOM and visual order.

`--ui-action-radius` defines the fully rounded action shape. Its dedicated cascade layer keeps buttons consistent despite older component radius overrides, including form actions, table tools, header controls and sidebar count chips. Field and card shapes remain governed by their own tokens.

## Dashboard operational coverage

The dashboard includes permission-filtered stock alerts, recent activity with notes, and recorded acquisition values for assets due for return or approaching warranty expiry. Currency totals stay separate and missing asset costs are reported. Stock alerts follow the existing site threshold. Pending offboarding follows the inventory charts and includes visible outstanding assets, accessories and directly assigned licence seats for ended, disabled or archived users. Its monetary value explicitly covers active assets only.

Recent Activity uses a dedicated feed backed by the existing scoped activity API. It supports search, refresh, incremental loading, readable notes and record links only when the API marks each record as viewable. No audit records are merged or removed for display.

### Admin settings cards

The settings directory now uses compact icon badges, three columns on wide screens, two on medium screens and one on mobile. Category and individual-setting cards share the same rounded surfaces, wrapped descriptions, outlined Open actions and keyboard focus treatment. Settings counts remain factual. Surfaces follow the existing theme tokens, with sage and sand accents, subtle hover feedback and reduced-motion support. Existing category navigation, keyword filtering and destinations are preserved.

### Shared tab selection

Settings, project pages, receiving, Bootstrap detail tabs, role-based tab lists and dashboard section links now use the Branding settings selection style: neutral labels, a semibold selected label, a thin bronze bottom border and fully rounded corners. The shared cascade layer removes competing filled pills, inset shadows and top accents. Hover, visible keyboard focus, reduced motion, existing tab switching and mobile wrapping or scrolling are preserved. Dropdown actions and sidebar navigation remain independent controls.

### Stock quantity adjustment

The shared accessory, consumable and component adjustment dialog now shows available stock before and after the proposed change. Add stock, Remove stock and Audit only choices preserve the existing signed amount payload and zero-change audit behavior. The reason stays in the main flow; optional order, supplier, date, cost, currency and attachments are grouped in a disclosure. The header and footer remain visible while expanded details scroll. The calendar is positioned within the modal surface, outside the scrolling content, and closes when that content scrolls. Reopening for another item resets the form and disclosure. Balance previews reject fractional quantities and removals beyond available stock without posting changes. Browser checks covered all three inventory types, resets, disclosure, calendar, dark mode and mobile sizing. No stock adjustment was submitted during verification.


## Bulk Checkout legacy alignment

Bulk Checkout now uses separate User, Location and Project session flows with scans, mixed asset/accessory queues, source filters, batch defaults and explicit review. See [the comparison and implementation limits](bulk-checkout-audit.md).


## Three-step project issuing and cancellation

Project Asset Issue now has Employee, Scan asset and Acceptance steps. The scan step contains the return date, optional issue notes and issuer review. Submitting it sends the acceptance request and redirects to the informational Acceptance step. That screen uses the persisted notification status: sent, demo mail log, pending or failed. It explains that delivery resumes after employee acceptance. Physical handover remains a separate action in the issue audit after acceptance, rather than a fourth wizard step.

Cancel issuing is available from the employee/scan form and acceptance screen, with an inline confirmation. The issuer can also cancel an unfinished issue from its audit, including a previously closed session. Cancellation closes the session, stops outstanding acceptance links, reverses only matching assignments that have not been handed over, restores the recorded original location, and preserves pre-existing project reservations. Newly created issue reservations are released. Completed handovers are unchanged. Cancellation and response handling lock the same issue record so a simultaneous employee response cannot bypass cancellation. Audit labels distinguish issuer cancellation from employee rejection.

Validation: 51 project and acceptance UI tests passed with 343 assertions; the four cancellation tests passed again after the final idempotence adjustment. Browser verification checked three step markers, scan review, cancellation disclosure/keep action, full-width Acceptance and accurate demo mail status. Browser verification did not submit any new employee request or cancel existing allocations.


## Bulk Checkout workspace layout

Bulk Checkout alone uses a two-column layout when at least 900px of content width is available. Recipient and scanning controls stay on the left; a sticky summary on the right contains the destination, counts, selected items, quantity edits, current assignments and persistent review/confirmation controls. Narrow screens stack the same sections in reading order. Other form layouts remain unchanged.

Purpose captions explain User, Location and Project. Optional project allocation, stock source and batch defaults use disclosures; required stock source controls open for Location and Project. The stock picker groups availability, existing allocations and remaining-after-session figures into four columns. The summary gives a contextual next-action hint and explains missing selections before confirmation becomes available. Serial details and item overrides remain accessible inside each item's disclosure.

Validation: the existing 28 checkout feature tests passed, with 117 assertions. Browser checks covered recipient selection, mixed scans/accessory quantities, review gating, separate mode queues, automatic Project source disclosure, sticky summary/footer, dark theme, mobile stacking and no page overflow. Verification items were cleared without saving a checkout.


### Bulk Checkout: single-page workspace

Recipient selection and the item picker appear together on the left, with the checkout summary on the right on wide screens. Narrow screens stack the cards. There is no stepper, Continue or Back process. The required Checkout to dropdown selects User, Location or Project, with Project permission gated. Switching type updates instructions and stock rules while preserving separate queues. Final confirmation still requires a recipient, valid stock source, items and review confirmation. Browser verification confirmed all sections are visible and the dropdown changes recipient type.

Bulk Checkout polish: recipient type and target share a compact row on wide content areas and stack on smaller screens. The summary separates recipient context from unit counts, explains the empty state, and shows Draft, Review items or Ready to confirm. Guidance sits beside the final confirmation. Open picker buttons and selected stock rows have visible states; asset rows already staged say Selected. Scanner lookups announce progress and suppress overlapping scan requests. Switching pickers cancels a pending search debounce to prevent a late refresh from replacing quantity input.

Validation covered one scanned asset, accessory quantity 2, quantity edits, review reset after adding items, confirmation enabled only after a valid recipient and review, selected asset feedback, clear-session recovery, and mobile without page overflow. All staged verification items were cleared; no checkout was submitted. Styling uses existing light/dark theme tokens and reduced-motion handling. The backend workflow is unchanged. Contextual guidance follows [NN/G recognition and recall](https://www.nngroup.com/articles/recognition-and-recall/).

### Project issue confirmation receipt

The recorded issue now opens a completion receipt instead of the final wizard panel. A sage check confirms the issue record was saved, with a separate acceptance badge to avoid implying employee acceptance or physical handover. Employee, asset tag/model, project, return date, issuer and timestamp are drawn from the saved issue and snapshot. Notification text continues to distinguish sent, demo-logged and failed or pending states; recorded demo mail is explicitly not inbox delivery.

Next actions include Issue Another Asset within the same project, View issue record, View project and the existing cancellation confirmation. Accepted issues promote Continue to handover, leading to the audit record rather than marking handover complete. A quiet timeline describes the remaining work. Light/dark tokens, narrow stacking and reduced-motion preferences are supported. Browser checks covered both themes, narrow layout without overflow and opening/dismissing cancellation; no issue or inventory state was changed. Existing ProjectWorkflowTest passed: 23 tests, 227 assertions.

### Manual inventory creation disabled

Manual create and clone abilities for Asset, Accessory and License are denied centrally before the superuser override. Existing policy-driven table buttons, Quick actions, model shortcuts and row actions consequently hide those entry points. UI form URLs and submissions, clone URLs and direct inventory API POSTs are forbidden, including for administrators and superusers. The legacy accessory clone POST has an explicit permission middleware. Asset multi-create requests authorize before field validation.

As explicitly selected by the user, Scan to Receive and imports remain available. The Asset receive ability retains the former assets.create permission requirement and existing admin behavior; receiving views, requests, barcode checks, controller and action now use it independently of manual creation. Viewing, editing, quantity adjustments and checkouts are unaffected. Deleted asset navigation retains its former permission requirement through the receive alias.

Validation: 42 manual-creation and receiving tests passed (338 assertions), 3 import permission tests passed (3 assertions), and 3 actual asset/accessory/license import tests passed (96 assertions). Browser verification confirmed removed toolbar buttons on all three lists, the updated Quick actions Create group, and the retained Scan to Receive entry. No preview inventory was changed.

### Demo project directory

DemoProjectSeeder adds 20 fictional projects with names prefixed Demo: and stable codes STATSSA-DEMO-001 through STATSSA-DEMO-020. It is an explicit opt-in seeder, not part of DatabaseSeeder, and firstOrCreate preserves existing rows on reruns. Records share the existing eNumerate company when present and rotate through existing compatible locations. Dates are based on the seed date: 12 active, 4 planned, 2 suspended and 2 ended. It creates no employee memberships, reservations or inventory. Executed on the local demo on 22 November 2026; the Projects table shows 21 total rows including the existing project.

The All Assets navigation total now uses a neutral frosted pill rather than orange: a mostly opaque white or charcoal fill, 12px backdrop blur, desaturation, soft rim and theme text. Rounded corners remain. Reduced transparency, forced colours and unsupported-backdrop browsers use a solid surface. Browser verification confirmed neutral computed fills and blur in both light and dark themes.

Table search disclosures no longer inherit the old 140px minimum width from expanded search fields. The collapsed parent fits the 44px icon and shares the standard 8px toolbar gap with View & filters. Expansion and collapse were verified in the browser. The shared rule applies to table search disclosures throughout the application.

### Operational asset status parity

The ten missing legacy operational statuses have been restored with their verified status types. Status filtering now includes unused catalogue labels and preserves operational names for assigned assets rather than flattening them to Deployed. Existing inventory and default statuses were preserved. See [the status audit](asset-status-audit.md) for the mapping and validation.

Dashboard quick-link icons have been replaced with a greeting using the signed-in user’s first name and an asset analytics overview sentence. Existing section navigation remains alongside the introduction and stacks on mobile. Inventory shortcuts remain available in Quick actions. Browser verification confirmed the greeting and removal of the icon row.
