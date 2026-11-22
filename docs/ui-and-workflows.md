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
