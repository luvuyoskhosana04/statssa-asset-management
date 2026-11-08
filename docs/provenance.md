# Provenance and contents

The application is based on upstream Snipe-IT commit `8d10063af98aff0e3d5bb40aa56e6c5d732b5181` (v8.8.0). The upstream licence and source structure are retained. `README.upstream.md` retains the original project overview.

Stats SA changes include the branded welcome/sign-in screen, shared theme and icon states, dashboard presentation, form steppers, settings categories/tabs, profile presentation, table controls and related backend filtering/presentation work. This is a source snapshot; upstream Git history is not imported into this new repository.

Three local runtime overrides are included as source: `public/css/dist/all.css`, `public/mix-manifest.json`, and `config/version.php`. This avoids relying on external local bind-mounted reference files.

Upstream GitHub workflows, funding links, ownership assignments and bot configuration were not copied into the new repository because they target upstream's maintainers and release infrastructure. Add project-specific CI and CODEOWNERS once the development team and deployment environment are agreed.

Database records, machine-specific compose credentials, uploaded files, logs, vendor, node_modules and local VM scripts are excluded. Existing upstream sample/test environment files are templates, not the current installation's secrets.

Brand logos and illustration/font/icon assets remain subject to their respective owners and included notices. The code licence does not imply permission to reuse institutional identity for unrelated products.
