# Pages and modules

Use this map to find a feature, then its exact endpoint in [web routes](web-routes.md) or [API routes](api-routes.md). The [view inventory](views.md) includes every Blade file, including partials and shared components.

Example paths identify feature entry points. Individual routes and permissions vary. Reports have several specific destinations rather than a single guaranteed `/reports` index.

| Area | Example path | Views | Web controller area | Purpose and role context |
| --- | --- | --- | --- | --- |
| Dashboard | `/` | [resources/views/dashboard.blade.php](../../resources/views/dashboard.blade.php) | [app/Http/Controllers/DashboardController.php](../../app/Http/Controllers/DashboardController.php) | Shared landing page; global summaries depend on permissions |
| Sign-in | `/login` | [resources/views/auth](../../resources/views/auth) | [app/Http/Controllers/Auth](../../app/Http/Controllers/Auth) | Public credentials form; password recovery and optional configured identity providers |
| Assets | `/hardware` | [resources/views/hardware](../../resources/views/hardware) | [app/Http/Controllers/Assets](../../app/Http/Controllers/Assets) | Asset Manager; inventory, record, create/edit, checkout/checkin, audit and files |
| Licenses | `/licenses` | [resources/views/licenses](../../resources/views/licenses) | [app/Http/Controllers/Licenses](../../app/Http/Controllers/Licenses) | License Manager; product records, seats, assignments and files |
| Accessories | `/accessories` | [resources/views/accessories](../../resources/views/accessories) | [app/Http/Controllers/Accessories](../../app/Http/Controllers/Accessories) | Accessory Manager; stock, assignments and files |
| Consumables | `/consumables` | [resources/views/consumables](../../resources/views/consumables) | [app/Http/Controllers/Consumables](../../app/Http/Controllers/Consumables) | Consumable Manager; stock and issue transactions |
| Components | `/components` | [resources/views/components](../../resources/views/components) | [app/Http/Controllers/Components](../../app/Http/Controllers/Components) | Component Manager; stock and asset assignments |
| People | `/users` | [resources/views/users](../../resources/views/users) | [app/Http/Controllers/Users](../../app/Http/Controllers/Users) | User Manager; directory, profiles and account permissions |
| Groups | `/groups` | [resources/views/groups](../../resources/views/groups) | [app/Http/Controllers/GroupsController.php](../../app/Http/Controllers/GroupsController.php) | Superuser or explicit group-management permission; shared permission matrix |
| Kits | `/kits` | [resources/views/kits](../../resources/views/kits) | [app/Http/Controllers/Kits](../../app/Http/Controllers/Kits) | Bundle definitions and checkout subject to applicable permissions |
| Maintenance | `/maintenances` | [resources/views/maintenances](../../resources/views/maintenances) | See route catalogue | Asset-related work queue and maintenance records |
| Calendar | `/calendar` | [resources/views/calendar](../../resources/views/calendar) | [app/Http/Controllers/CalendarEventsController.php](../../app/Http/Controllers/CalendarEventsController.php) | Permission-filtered events and agenda |
| Import | `/import` | [resources/views/livewire/importer.blade.php](../../resources/views/livewire/importer.blade.php) | See route catalogue | Staged upload, type, field mapping and preview; explicit permissions apply |
| Settings | `/admin` | [resources/views/settings](../../resources/views/settings) | See route catalogue | Superuser categories and section tabs |
| Reports | `/reports` | [resources/views/reports](../../resources/views/reports) | [app/Http/Controllers/Reports](../../app/Http/Controllers/Reports) | Reports vary by permission and route; see catalogue |
| My account | `/account/profile` | [resources/views/account](../../resources/views/account) | [app/Http/Controllers/Account](../../app/Http/Controllers/Account) | Own profile, assigned items, requests and acceptance |
| Models | `/models` | [resources/views/models](../../resources/views/models) | [app/Http/Controllers/AssetModelsController.php](../../app/Http/Controllers/AssetModelsController.php) | Asset model definitions and files |
| Locations | `/locations` | [resources/views/locations](../../resources/views/locations) | [app/Http/Controllers/LocationsController.php](../../app/Http/Controllers/LocationsController.php) | Location hierarchy and holdings |
| Categories | `/categories` | [resources/views/categories](../../resources/views/categories) | [app/Http/Controllers/CategoriesController.php](../../app/Http/Controllers/CategoriesController.php) | Inventory classification |
| Suppliers | `/suppliers` | [resources/views/suppliers](../../resources/views/suppliers) | [app/Http/Controllers/SuppliersController.php](../../app/Http/Controllers/SuppliersController.php) | Supplier directory and records |
| Manufacturers | `/manufacturers` | [resources/views/manufacturers](../../resources/views/manufacturers) | [app/Http/Controllers/ManufacturersController.php](../../app/Http/Controllers/ManufacturersController.php) | Manufacturer directory |
| Departments | `/departments` | [resources/views/departments](../../resources/views/departments) | [app/Http/Controllers/DepartmentsController.php](../../app/Http/Controllers/DepartmentsController.php) | Organisation structure |
| Companies | `/companies` | [resources/views/companies](../../resources/views/companies) | [app/Http/Controllers/CompaniesController.php](../../app/Http/Controllers/CompaniesController.php) | Company records and optional company scoping |
| Custom fields | `/fields` | [resources/views/custom_fields](../../resources/views/custom_fields) | [app/Http/Controllers/CustomFieldsController.php](../../app/Http/Controllers/CustomFieldsController.php) | Fieldsets and field definitions |

## Shared interface

- [Application shell](../../resources/views/layouts/default.blade.php): header, navigation and shared assets.
- [Welcome shell](../../resources/views/layouts/welcome.blade.php): public sign-in layout.
- [Sign-in](../../resources/views/auth/login.blade.php): username and password form.
- [Reusable Blade fragments](../../resources/views/blade): form controls and dashboard components.
- [Livewire views](../../resources/views/livewire): interactive widgets, including import and permissions.

Page access is enforced on the server. Hiding a menu item is not authorization.
