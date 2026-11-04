# Jephthah's Workbench

Jephthah's Workbench uses Laravel 12 with Inertia.js and React. Sign in at `/` to access the administrator workspace, which includes task management, administrator accounts, database tools, the SP ID Generator, and monthly CSV allocation. The app uses a local SQLite database at `database/database.sqlite`; this file is gitignored and must be kept private. Existing records were copied from the previously configured MySQL database without changing the source database.

## Local setup

1. Copy `.env.example` to `.env` only when there is no existing environment file. Preserve the current `.env` and database settings when upgrading.
2. The default configuration uses the project-local SQLite database. For a new installation, create the database file before running migrations:

   ```powershell
   if (!(Test-Path database\database.sqlite)) {
       New-Item -ItemType File -Path database\database.sqlite | Out-Null
   }
   ```

   Keep `DB_CONNECTION=sqlite` so local SQLite remains the application's primary database. For the current XAMPP URL, use `APP_URL=http://localhost/helpdesk` and `ASSET_URL=/helpdesk/public`. Configure the Aiven MySQL connection variables (`MYSQL_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_SSL_CA`) for the database-panel backup and restore actions.
3. Install dependencies and build the React UI:

   ```powershell
   composer install
   npm install
   npm run build
   ```

4. When upgrading an existing installation, run `php bin/migrate.php` once to complete the username and role schema updates. It is idempotent and preserves existing administrator rows. Back up the database before applying Laravel migrations. Generate an application key once if `APP_KEY` is empty, then run:

   ```powershell
   php artisan key:generate
   php artisan migrate --force
   ```

   Do not run `migrate:fresh` or `db:wipe`; those commands remove application data.
5. For a new installation only, set `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL`, and a 12-character-or-longer `INITIAL_ADMIN_PASSWORD`, then run `php bin/setup.php`. The one-time credentials are removed from `.env` after setup.

The root URL is the workbench sign-in page; successful sign-in opens the administrator dashboard. Email addresses are not unique. The workbench includes task reminders, administrator account management, database tables and SQL import/export, the SP ID Generator, and monthly CSV allocation.

Migration `2026_11_04_000000_remove_tickets_and_notices` permanently deletes the support-ticket and notice tables, their stored notice attachments, and the support-request notification preference. It preserves administrator accounts, tasks, settings, and other tables. This migration cannot be rolled back, and deleted data cannot be recovered; make a backup before applying it.

Only administrator accounts are supported. Migration `2026_10_20_000000_remove_chat_and_disable_staff_accounts` disables existing staff accounts but preserves their records; they cannot be reactivated or promoted through the application. That migration also permanently deletes chat conversations, messages, and related chat tables, and cannot be rolled back. Deleted chat history cannot be recovered.

Migration `2026_10_20_000001_remove_task_board_feature` permanently removes the task board and deletes all stored task records. It cannot be rolled back.

Laravel uses the file cache by default, including for request throttling. The database cache and lock tables are not needed. If `.env` explicitly sets `CACHE_STORE=database`, change it to `CACHE_STORE=file` before migrating. Migration `2026_10_20_000002_remove_database_cache_tables` permanently drops `cache` and `cache_locks`; existing cached values are discarded.

Local SQLite is the application's primary database; Aiven MySQL is used as a backup copy, not for normal app traffic. In the database panel, **Back up local to Aiven** replaces Aiven's managed table data with local data, and **Restore Aiven to local** replaces local managed data with Aiven's copy. Both operations require reviewing a preview and confirming; the destination is snapshotted first. The panel compares the copies and indicates whether Aiven is current, outdated, or needs attention. Cloning covers users, settings, notification settings, accomplishments, and Laravel migration history; both databases must have matching schemas, and MySQL tables must use InnoDB. Each managed table is capped at 20,000 rows per database. Private pre-clone snapshots are stored under `storage/app/private/database-backups`; snapshots and database exports may contain password hashes and other sensitive data, so keep them private. The database tools also export the local SQLite database as a `.sqlite` file. Restore a standalone SQLite export by stopping the app and replacing `database/database.sqlite`.

The SP ID Generator is part of the Laravel admin application. Its React page is in `resources/js/Pages/Admin/IdGenerator.jsx`, and its GD-based card composition is in `app/Services/IdCardGenerator.php`. The card template and required fonts remain under `public/tools/sp-id-generator/img`; PHP GD with FreeType support must be enabled to generate cards. The old `/tools/sp-id-generator/` URL redirects to the admin page.

The Monthly CSV tool downloads one CSV row per weekday in the selected month, matching the `number,datetime,value,refnumber,ads` columns in the supplied example. Datetimes use the formatted `MMM dd, yyyy h:mm AM` text without a leading apostrophe. The admin can set a positive whole-peso monthly target and base weekday amount; both are saved when downloading and restored on later visits. Weekday allocations use the selected base amount, with the remaining total assigned to the final two weekdays using the month-end rule. If the selected target is too low to sustain the base, the base is reduced as needed. The total always matches the target. The generator uses the configured number, random weekday times from 8:00–11:59 AM, random references from 100,000,000 to 199,999,999, and randomly selected PNG paths from `MONTHLY_PLAN_AD_DIRECTORY`. Set `MONTHLY_PLAN_NUMBER` and `MONTHLY_PLAN_AD_DIRECTORY` in `.env` to customize the number and local ad directory. Migration `2026_10_20_000003_create_helpdesk_settings_table` stores the remembered settings. PSD files, Photoshop, and image generation are not used.
