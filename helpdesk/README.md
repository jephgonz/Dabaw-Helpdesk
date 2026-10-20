# Jephthah Helpdesk

Jephthah Helpdesk uses Laravel 12 with Inertia.js and React. The public landing page accepts support reports and displays the latest notices and support totals. Only administrator accounts can sign in to the management dashboard. Existing account and ticket data remain in the `helpdesk_users` and `helpdesk_tickets` MySQL tables. Laravel migrations create missing application tables without replacing existing tables.

## Local setup

1. Copy `.env.example` to `.env` only when there is no existing environment file. Preserve the current `.env` and database settings when upgrading.
2. Set the MySQL credentials and CA certificate path. For the current XAMPP URL, use `APP_URL=http://localhost/helpdesk` and `ASSET_URL=/helpdesk/public`.
3. Install dependencies and build the React UI:

   ```powershell
   composer install
   npm install
   npm run build
   ```

4. When upgrading an existing installation, run `php bin/migrate.php` once to complete the username, role, and ticket-resolution schema updates. It is idempotent and preserves existing rows. Generate an application key once if `APP_KEY` is empty, then run the Laravel migration:

   ```powershell
   php artisan key:generate
   php artisan migrate --force
   ```

   Do not run `migrate:fresh` or `db:wipe`; those commands remove application data.
5. For a new installation only, set `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL`, and a 12-character-or-longer `INITIAL_ADMIN_PASSWORD`, then run `php bin/setup.php`. The one-time credentials are removed from `.env` after setup.

The root URL `http://localhost/` is the public landing page. Anyone can submit a support report, view published notices and support totals, and open attached memo images. Reports are rate limited and stored without a linked account; contact name and email are required. Administrators sign in at `/admin/login` with email and password. Email addresses are not unique. Authenticated administrators can manage administrator accounts, support requests, resolution charts, database tables and SQL import/export, public notices, and the SP ID Generator. Apply migration `2026_10_19_000007_allow_public_helpdesk_reports` to allow anonymous reports on an existing installation.

Only administrator accounts are supported. Migration `2026_10_20_000000_remove_chat_and_disable_staff_accounts` disables existing staff accounts but preserves their records; they cannot be reactivated or promoted through the application. That migration also permanently deletes chat conversations, messages, and related chat tables, and cannot be rolled back. Deleted chat history cannot be recovered.

Migration `2026_10_20_000001_remove_task_board_feature` permanently removes the task board and deletes all stored task records. It cannot be rolled back.

Laravel uses the file cache by default, including for request throttling. The database cache and lock tables are not needed. If `.env` explicitly sets `CACHE_STORE=database`, change it to `CACHE_STORE=file` before migrating. Migration `2026_10_20_000002_remove_database_cache_tables` permanently drops `cache` and `cache_locks`; existing cached values are discarded.

The SQL tools back up the configured Laravel database, including its migration history, as portable MySQL SQL. Restores are limited to 25 MB, require explicit replacement confirmation, and apply any pending Laravel migrations after import. Imports accept table definitions and data only; database-level commands, routines, and triggers are not imported. Export files contain password hashes and other sensitive data; keep them private. Notice image attachments are stored privately under `storage/app/private/notice-attachments` and are served through the public helpdesk route; include that directory in file backups, because SQL exports contain only database data.

The ID Generator retains its existing templates, fonts, and GD-based generation code under `public/tools/sp-id-generator`. PHP GD must be enabled to generate cards.

The Monthly CSV tool downloads one CSV row per weekday in the selected month, matching the `number,datetime,value,refnumber,ads` columns in the supplied example. Datetimes use the formatted `MMM dd, yyyy h:mm AM` text without a leading apostrophe. The admin can set a positive whole-peso monthly target and base weekday amount; both are saved when downloading and restored on later visits. Weekday allocations use the selected base amount, with the remaining total assigned to the final two weekdays using the month-end rule. If the selected target is too low to sustain the base, the base is reduced as needed. The total always matches the target. The generator uses the configured number, random weekday times from 8:00–11:59 AM, random references from 100,000,000 to 199,999,999, and randomly selected PNG paths from `MONTHLY_PLAN_AD_DIRECTORY`. Set `MONTHLY_PLAN_NUMBER` and `MONTHLY_PLAN_AD_DIRECTORY` in `.env` to customize the number and local ad directory. Migration `2026_10_20_000003_create_helpdesk_settings_table` stores the remembered settings. PSD files, Photoshop, and image generation are not used.
