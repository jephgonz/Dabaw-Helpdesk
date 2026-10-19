# Dabaw Helpdesk

The Helpdesk app is separated from the existing ID Generator and organized as a small PHP application.

## Local URLs

- Helpdesk login page: `http://localhost/helpdesk/`
- Existing ID Generator: `http://localhost/helpdesk/public/tools/sp-id-generator/`

## Structure

- `app/Controllers` — request handling
- `app/Views` — page templates
- `config` — application configuration
- `database` — schema, migrations, and seed data
- `public` — web facing entry point and assets
- `routes` — route definitions
- `storage` — private uploads and logs

Staff sign in with a username and password. Administrators sign in with their email and password. No public registration route exists; only authenticated admins can create accounts in the dashboard.

The local `.env` has the Aiven connection metadata and CA path. Add the rotated database password and initial admin name, email, and password there. Use an admin password of at least 12 characters. Run `php bin/setup.php` once to create the first admin. The one-time admin password values are removed from `.env` after success.

The admin dashboard includes the SP ID Generator at `/admin/tools/id-generator`; its existing app remains under `/helpdesk/public/tools/sp-id-generator/` and requires the active Helpdesk admin session.

After deploying this login update, run `php bin/migrate.php` from the Laragon terminal once. It adds a unique staff username column and creates usernames for any existing staff accounts.

The admin MySQL page allows authorized administrators to browse, add, edit, and delete rows in tables in the configured database. Password and secret columns are hidden; account passwords are hashed on create or reset. Tables without primary keys are read-only in the browser manager. It does not expose a raw SQL console or schema-changing commands.

The page at `http://localhost/` redirects to the Helpdesk login. Public registration is not available; an authenticated admin creates all later staff accounts from the admin dashboard.
