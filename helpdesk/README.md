# Dabaw Helpdesk

Dabaw Helpdesk uses Laravel 12 with Inertia.js and React for its login, staff, and admin interfaces. The sidebar and topbar are shared React components. Existing account and ticket data remain in the `helpdesk_users` and `helpdesk_tickets` MySQL tables. Laravel migrations create missing application tables, including the staff notice board, without replacing existing tables. The previous PHP controllers and views have been removed.

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

The root URL `http://localhost/` redirects to `http://localhost/helpdesk/`. Staff sign in with their unique username; administrators sign in with email. Email addresses are not unique. Authenticated administrators can manage accounts, support requests, resolution charts, database tables and SQL import/export, staff notices, chat, and the SP ID Generator.

Authenticated users can open the shared task boards to search active accounts by name or username and view their boards. Administrators can add, edit, update, and delete tasks on any board. Staff can add tasks to staff boards, but only a board's owner (or an administrator) can change task status. Apply migration `2026_10_19_000006_create_task_board_table` to enable the task boards.

Chat is a direct-message system for active staff and admin accounts, available as a docked widget in the lower-right corner of authenticated dashboard pages. Messages are stored as readable text in the configured database and remain available in conversation history. No browser device registration, encryption keys, or online-presence setup is required. Since messages are not end-to-end encrypted, database access exposes their contents. Migration `2026_10_19_000005_simplify_chat_for_plain_messages` permanently removes the previous encrypted chat history and its device/key/archive tables; this migration cannot be rolled back.

The SQL tools back up the configured Laravel database, including its migration history, as portable MySQL SQL. Restores are limited to 25 MB, require explicit replacement confirmation, and apply any pending Laravel migrations after import. Imports accept table definitions and data only; database-level commands, routines, and triggers are not imported. Export files contain password hashes and other sensitive data; keep them private. Notice image attachments are stored privately under `storage/app/private/notice-attachments`; include that directory in file backups, because SQL exports contain only database data.

The ID Generator retains its existing templates, fonts, and GD-based generation code under `public/tools/sp-id-generator`. PHP GD must be enabled to generate cards.
