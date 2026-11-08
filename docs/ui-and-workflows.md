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

## Workflows to preserve

- Credentials sign-in: `auth/login.blade.php`, `Auth/LoginController.php`, `auth/partials/` and the welcome layout. Existing account permissions control access.
- Asset, license and user creation: feature edit views and `form-stepper.js`. Review final values before submission and preserve values on Back.
- Import: `app/Livewire/Importer.php` and `resources/views/livewire/importer.blade.php`. Keep type, mapping and preview separate. Cancellation must reflect what has actually completed.
- Inventory: feature index view, API controller and transformer. Preserve identifier columns, explicit assignment context and checkin/checkout actions.
- Settings: `resources/views/settings/`, category landing page and section tabs.
- Profile: `resources/views/account/profile.blade.php`, existing profile controller and upload validation.

## UI review

Check desktop and narrow mobile widths, keyboard navigation, visible labels and errors, reduced motion, light/dark themes, empty/loading states and long values. Preserve bronze primary actions, blue record links and red destructive/error states. Do not use colour alone for status.
