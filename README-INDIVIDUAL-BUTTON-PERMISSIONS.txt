AGRIQR - INDIVIDUAL BUTTON AND ACTION PERMISSIONS

Role setup preserved:
- System Admin (database role: system_admin)
- Admin (database role: admin)
- Technician (database role: technician)

WHAT THIS CHANGE ADDS
1. System Admin can select an Admin or Technician account.
2. System Admin can enable/disable individual buttons and actions for that account.
3. Individual overrides affect only the selected account, not other users with the same role.
4. Existing role-level default permissions remain available.
5. A selected account can be reset to inherit its role defaults.
6. Individual permission changes are written to the audit log.
7. Route middleware continues enforcing permissions on the server.
8. Dashboard and sign-out are inherited from role defaults and are not disabled by an individual button-only setup.
9. An import-only farmer account gets an Import farmers navigation link even when the Farmers directory action is disabled.

FILES IN THIS PATCH
- app/Models/User.php
- app/Models/UserButtonPermission.php (new)
- app/Services/ButtonAccess.php
- app/Http/Controllers/ButtonAccessController.php
- database/migrations/2026_11_08_150000_create_user_button_permissions_table.php (new)
- resources/views/button-access/index.blade.php
- resources/views/layouts/app.blade.php
- routes/web.php
- tests/Feature/ButtonAccessTest.php

INSTALLATION
1. Back up your current project and database.
2. Extract this patch into the root of your Laravel project, preserving folders, and overwrite the matching files.
3. Open PowerShell in E:\xampp\htdocs\agriqr-laravel.
4. Run:

   php artisan migrate
   php artisan optimize:clear
   php artisan test --filter=ButtonAccessTest

5. Sign in using your System Admin account.
6. Open System Admin > Button access.
7. Under Individual user permissions, select a Technician account.
8. Enable only Farmers > Import farmers from CSV, then save.
9. Test with that Technician account. The account should retain Dashboard and Sign out, see an Import farmers navigation link, and be denied access to the Farmers directory and other disabled actions.
10. Select another Technician account to confirm it remains unchanged.

IMPORTANT
- The user must still have the Farmers module enabled in their account's module access. Individual button access cannot grant a module that the role/account is not allowed to access.
- Admin accounts remain subject to the same individual button overrides, but Admin module access behavior remains as currently implemented.
- System Admin remains unrestricted and cannot be configured as a restricted target account.
- The automated tests were added but could not be executed in the patch-building environment because its PHP CLI is missing DOM, mbstring, and XML extensions. Run the commands above in your XAMPP PHP environment, where your previous test runs were successful.
