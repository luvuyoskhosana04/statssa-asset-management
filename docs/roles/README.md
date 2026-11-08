# Roles and access

One shared application serves seven reference roles. These guides explain where each role's features live; they do not create new authorization rules.

| Role | Factory state | Main working area |
| --- | --- | --- |
| [Superuser](superuser.md) | `firstAdmin` | All modules and settings |
| [Asset Manager](asset-manager.md) | `assetManager` | Assets, models, assignments, audits and maintenance |
| [License Manager](license-manager.md) | `licenseManager` | Licenses, seats, assignments and keys |
| [Accessory Manager](accessory-manager.md) | `accessoryManager` | Accessories and assignments |
| [Consumable Manager](consumable-manager.md) | `consumableManager` | Consumable stock and issues |
| [Component Manager](component-manager.md) | `componentManager` | Components and asset assignments |
| [User Manager](user-manager.md) | `userManager` | People, profiles and user records |

## How permissions work

Credentials identify the account. Policies evaluate user permissions, group permissions and resource context. Role names in the header describe access; they do not grant it. Company scope may further restrict visible records.

Source references: [User model](../../app/Models/User.php), [policies](../../app/Policies), [capability definitions](../../config/permissions.php), [reference factory states](../../database/factories/UserFactory.php).

Do not duplicate source pages by role. Keep shared changes in the feature folder and test authorization for every affected role.
