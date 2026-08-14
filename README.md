# Tak Tak backend — CodeIgniter 4

The Tak Tak distribution back office as a CodeIgniter 4 + MySQL 8 REST API: users and roles with
per-module permissions, geography (states, regions, distributors), the product catalogue with dated
MRP history, and CSV imports with per-row error reporting.

This is a port of the Node/Express service in `../taktak-backend`; the routes, response envelope,
validation rules and business rules match it endpoint for endpoint.

- **API reference:** [`docs/API.md`](docs/API.md)
- **Swagger UI:** `GET /api/v1/docs`
- **OpenAPI 3.0 spec:** `GET /api/v1/docs/openapi.json`

---

## Requirements

- PHP 8.1+ with `intl`, `mbstring`, `json`, `mysqlnd`
- MySQL 8.0+ (or MariaDB 10.5+)
- Composer 2

---

## Setup

```bash
composer install

# 1. Configure. The committed .env points at 127.0.0.1/taktak with the root user.
#    Change the database credentials and BOTH jwt secrets before deploying.

# 2. Create the database (or create it yourself in MySQL)
php spark db:create taktak

# 3. Tables, view, and the seed data
php spark migrate
php spark db:seed InitialSeeder

# 4. Run
php spark serve --port 8080
```

Then open <http://localhost:8080/api/v1/docs> and sign in with the seeded account:

```
superadmin@taktak.com / SuperAdmin@123
```

**Change that password after the first login.** Both values come from `.env`
(`taktak.superAdminEmail`, `taktak.superAdminPassword`), and the seeder never touches the password of
an account that already exists.

### Smoke test

```bash
curl -s localhost:8080/api/v1/health

TOKEN=$(curl -s -X POST localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"superadmin@taktak.com","password":"SuperAdmin@123"}' \
  | php -r 'echo json_decode(stream_get_contents(STDIN), true)["data"]["access_token"];')

curl -s localhost:8080/api/v1/auth/me -H "Authorization: Bearer $TOKEN"
```

---

## Configuration

Everything lives in `.env`. The application-specific settings sit under the `taktak.` prefix and are
typed in [`app/Config/Taktak.php`](app/Config/Taktak.php):

| Key | Default | Purpose |
|-----|---------|---------|
| `taktak.apiPrefix` | `api/v1` | URI prefix every route is mounted under. |
| `taktak.jwtAccessSecret` | dev value | **Change this.** Signs access tokens. |
| `taktak.jwtRefreshSecret` | dev value | **Change this.** Signs refresh tokens. |
| `taktak.jwtAccessTtl` | `15m` | Access token lifetime (`30s`, `15m`, `12h`, `7d`). |
| `taktak.jwtRefreshTtl` | `7d` | Refresh token lifetime. |
| `taktak.superAdminEmail` / `Password` / `Name` | see `.env` | Seeded first account. |
| `taktak.maxUploadSizeMb` | `10` | CSV upload ceiling. |
| `taktak.maxImportRows` | `50000` | Rows per import file. |
| `taktak.rateLimitGeneral` | `2000` | Requests per window per IP (use ~300 in production). |
| `taktak.rateLimitAuth` | `20` | Login / refresh attempts per window per IP. |
| `taktak.rateLimitWindow` | `900` | Window length in seconds. |

CORS lives in [`app/Config/Cors.php`](app/Config/Cors.php) — it allows every origin out of the box,
which you should narrow to your front-end hosts before going to production.

For production also set `CI_ENVIRONMENT = production` (which hides internal error messages) and point
your web server at `public/`, never at the project root.

---

## Layout

```
app/
  Config/
    Routes.php         every endpoint, with its permission filter
    Taktak.php         application settings (env-backed)
    Filters.php        auth / permission / throttle / CORS wiring
    Exceptions.php     routes all errors to the JSON handler
  Controllers/         thin: validate input, call a service, shape the response
    BaseApiController  response envelope, body/query parsing, validation helpers
  Services/            the business rules
    AuthService        login, refresh rotation, logout, password change
    UserService        with the "last Super Admin" guards
    RoleService        permission assignment, built-in role protection
    RegionService, DistributorService, BrandService, ProductService
    MrpService         the dated price-history rules
    ImportService      CSV -> staging -> validate -> promote
    PermissionService  role -> permission slugs
  Models/              one per table, thin query helpers
  Filters/             AuthFilter, PermissionFilter, ThrottleFilter
  Libraries/           JwtService, Auth (per-request identity)
  Support/             Permissions master list, Indian states, pagination, transactions
  Database/
    Migrations/        the whole schema as raw DDL, plus the price-list view
    Seeds/             permissions, roles, role permissions, states, super admin
  Docs/openapi.php     the OpenAPI description served at /api/v1/docs
docs/API.md            the written API reference
```

### How a request flows

```
route  ->  cors  ->  throttle  ->  auth (verify JWT, publish identity)
       ->  permission:module,action  ->  controller  ->  service  ->  model
```

- **`auth`** reads `Authorization: Bearer <token>`, verifies it and publishes the caller on the
  `auth` service for the rest of the request.
- **`permission`** compares the route's slug against the permissions baked into the token at login.
  `SUPER_ADMIN` passes everything.
- Anything thrown — `ApiException`, a framework 404, a MySQL constraint violation — is rendered by
  `App\Handlers\ApiExceptionHandler` as `{ success: false, message, errors? }`.

---

## Commands

| Command | What it does |
|---------|--------------|
| `php spark serve --port 8080` | Development server. |
| `php spark routes` | Every route with its filters — the fastest way to check permissions. |
| `php spark migrate` | Create the schema. |
| `php spark migrate:rollback` | Drop it again. |
| `php spark db:seed InitialSeeder` | Idempotent seed: permissions, roles, states, super admin. |
| `php spark db:create taktak` | Create the database itself. |

The seeder is safe to re-run after adding a permission to
[`app/Support/Permissions.php`](app/Support/Permissions.php): it inserts what is missing and never
resets a role whose permissions an administrator has since changed.

---

## Notes on the design

- **Nothing is hard deleted.** `DELETE` flips `status` to `inactive`, so `created_by` / `updated_by`
  on old records always resolve to a name.
- **Prices are append-only.** A new MRP closes the current row the day before it starts; the history
  is what lets a report for any past month pick the right price.
- **Imports stage first.** Every CSV line is written to `product_import_staging` as text before any
  check runs, and each good row is promoted in its own transaction — one bad line never rolls back
  the file, and every failure keeps its reason.
- **Refresh tokens rotate and are stored hashed.** Presenting one burns it; only its SHA-256 hash is
  in the database.
- **Permissions ride in the access token.** Per-request checks cost nothing; a role change takes
  effect on the next refresh, which is why access tokens are short-lived.
