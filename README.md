# Critari 

A multi-tenant academic assessment engine built on Laravel 11, Inertia.js, Vue 3, and Tailwind CSS.

## Architecture
- **Multi-Tenancy**: `stancl/tenancy` v3 using a PostgreSQL "Schema-per-tenant" model.
- **Databases**:
  - `landlord_db`: The central database handling domains, global tenants, and superadmin users.
  - `tenant_db`: The container database holding an isolated Postgres schema (e.g., `tenant_app`) for every tenant.
- **Frontend**: Vue 3 SFCs using Inertia.js to render Laravel responses.
- **Docker/Environment**: Laravel Sail sets up PostgreSQL and pgAdmin automatically.

## Initial Local Setup

1. **Clone the repository and install Composer dependencies**
   Before you can use Sail, you must install the vendor dependencies using a local PHP environment or a small Docker container.
   ```bash
   composer install
   npm install
   ```

2. **Configure Environment Variables**
   Make a copy of the example env file:
   ```bash
   cp .env.example .env
   ```
   **Ensure the following keys are set:**
   ```env
   APP_URL=http://localhost
   APP_DOMAIN=localhost.com

   DB_CONNECTION=landlord
   DB_HOST=pgsql
   DB_PORT=5432
   DB_DATABASE=landlord_db
   DB_USERNAME=sail
   DB_PASSWORD=password
   TENANT_DB=tenant_db
   ```

3. **Start Docker Containers (Laravel Sail)**
   ```bash
   ./vendor/bin/sail up -d
   ```

4. **Initialize Databases, Multi-Tenancy & Migrations**
   Laravel Sail will automatically provision the databases. Run our custom command to copy required tables (like users, sessions, cache) into the tenant environment, then migrate:
   ```bash
   # Copy central base migrations to the tenant schema directory
   ./vendor/bin/sail artisan tenants:setup-migrations

   # Migrate the central database (landlord_db) and seed the SuperAdmin
   ./vendor/bin/sail artisan migrate
   ./vendor/bin/sail artisan db:seed

   # Migrate the tenant schemas
   ./vendor/bin/sail artisan tenants:migrate
   ```

5. **Modify Your Hosts File**
   You need to route local tenant requests to `localhost`. Add this to your `/etc/hosts` file (requires `sudo`):
   ```
   127.0.0.1 localhost.com
   127.0.0.1 app.localhost.com
   ```

6. **Start the Frontend Assets Server**
   To compile the Vue/Inertia components, run the Vite development server (always use your local npm for frontend builds):
   ```bash
   npm run dev
   ```

## Development Workflow

- **Superadmin Console**: Accessible at `http://localhost`
- **Tenant Application**: Example tenant accessible at `http://app.localhost.com` (If caching/sessions throw errors switching between domains locally, try an Incognito window to clear session cookies).
- **Format PHP Code**: `./vendor/bin/pint`
- **Format UI/JS Code**: `npm run format` & `npm run lint`

## pgAdmin Database Management

Docker automatically spins up pgAdmin alongside PostgreSQL for simple GUI database management.
- **URL**: [http://localhost:5050](http://localhost:5050)
- **Login Email**: `admin@example.com` (or whatever is set to `PGADMIN_EMAIL` in `.env`)
- **Login Password**: `admin`
- Under the "Servers" sidebar, `Critari (local)` will be automatically pre-configured.
