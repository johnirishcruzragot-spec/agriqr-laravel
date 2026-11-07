# AgriQR

AgriQR is a Laravel application for farmer registration, RSBSA verification, agricultural supply inventory, subsidy programs, and QR-assisted distribution records. The legacy PHP application is retained in `AgriQR_old` as a reference; the active application uses Laravel routes, Eloquent models, migrations, Blade views, and Vite assets.

## Requirements

- PHP 8.3 or later and Composer
- Node.js 20.19+ or 22.12+ and npm
- MySQL or MariaDB (the default local database); SQLite can be configured for tests or lightweight local development

## Local setup

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan agriqr:create-admin
php artisan serve
```

For XAMPP, start Apache and MySQL, then create an empty `agriqr_laravel` database in phpMyAdmin before running `php artisan migrate`. Configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` for your MySQL installation. Keep the legacy `agriqr` database separate; Laravel's migrations target `agriqr_laravel` and do not import, alter, or erase legacy records.

Open the URL printed by `php artisan serve` and sign in with the Super Admin account created by the command. Do not use a shared or default password.

On Windows, if PHP and Composer were installed with `php.new`, restart the terminal once so the updated PATH is available.
For XAMPP, run Artisan with its PHP executable (`E:\xampp\php\php.exe artisan ...`) so command-line PHP uses the same extensions as XAMPP. This project’s XAMPP PHP includes `intl`, `pdo_mysql`, and `pdo_sqlite`; the separate Herd Lite CLI may need `intl` enabled before `db:show` and `db:table` will work.

## Main workflows

- **Farmers:** create and maintain farmer profiles, farm details, RSBSA registration and verification.
- **Inventory:** register supply items, receive stock, monitor minimum stock, and review stock movements. Import supplies from the Inventory page using the downloadable CSV template; existing supply codes update item details and add the CSV quantity to current stock.
- **Programs:** define assistance periods and supply limits for each farmer.
- **Distributions:** scan a farmer's QR code or enter their AgriQR ID, verify active and RSBSA-verified status, select a current program, and record supplies. Inventory deductions and movement records are committed together.
- **Team access and audit log:** manage Admin and Staff accounts, reset passwords, change roles, activate or deactivate accounts, and assign module access to each individual account. The selected role remains the maximum access level. Review record changes and sign-in/sign-out history.
- **Reports:** Super Admins can view and export daily/monthly distributions, farmers who received or have not received a selected program, remaining stock, inventory movements, staff activity, and failed QR/RSBSA checks.
- **System configuration:** Super Admins can maintain supply categories, add approved farmer profile fields and dropdown choices, and configure distribution eligibility and quantity rules.

QR generation and scanning run in the browser. Camera access requires localhost or HTTPS. Distribution processing still supports entering a farmer ID manually.

## Roles

| Role | Access |
| --- | --- |
| Super administrator | All modules and role assignments |
| Administrator | Farmers, inventory, programs, distributions and team access |
| Staff | Farmers, inventory and distributions |

Supply Officer and Custodian remain retired roles; existing accounts were migrated to Staff. Completed distribution records have no edit or delete route. Configuration and permission changes require confirmation in the interface and are written to the audit log.

Face verification is not currently integrated into the Laravel application. The failed-verification report therefore covers recorded QR lookup and RSBSA eligibility failures, not biometric results. The application also records distributions only when they complete successfully; it does not yet maintain pending or failed distribution transaction records.

## Verification

```powershell
php artisan test
npm run build
```

The original SQL dump and standalone face-recognition service are not automatically imported or started by Laravel. Migrate legacy records separately after reviewing and mapping the old data to the new schema.
