# Tak Tak API reference

CodeIgniter 4 + MySQL 8. Base URL of every endpoint below:

```
http://localhost:8080/api/v1
```

An interactive copy of this document (Swagger UI) is served at **`GET /api/v1/docs`**, and the
machine-readable OpenAPI 3.0 description at **`GET /api/v1/docs/openapi.json`** — import that URL
straight into Postman or Insomnia.

---

## Contents

- [Conventions](#conventions)
- [Authentication](#authentication)
- [Permissions](#permissions)
- [Health](#health)
- [Auth](#auth-endpoints)
- [Users](#users)
- [Roles](#roles)
- [Permissions master list](#permissions-master-list)
- [States](#states)
- [Regions](#regions)
- [Distributors](#distributors)
- [Brands](#brands)
- [Products](#products)
- [Product MRP](#product-mrp)
- [CSV imports](#csv-imports)
- [Errors](#errors)

---

## Conventions

**Response envelope.** Every successful response looks like this:

```json
{ "success": true, "message": "Success", "data": { } }
```

List endpoints add `meta`:

```json
{
  "success": true,
  "message": "Success",
  "data": [ ],
  "meta": { "page": 1, "limit": 20, "total": 137, "total_pages": 7 }
}
```

**List parameters.** Every collection endpoint accepts the same query string:

| Parameter  | Type    | Default | Notes |
|------------|---------|---------|-------|
| `page`     | integer | `1`     | From 1. |
| `limit`    | integer | `20`    | Max 200 (states: default 100, max 500). |
| `search`   | string  | –       | Which columns it covers is listed per endpoint. |
| `status`   | enum    | –       | `active` / `inactive`. |
| `sort_by`  | string  | –       | Only the columns listed per endpoint; anything else falls back to the default. |
| `sort_dir` | enum    | `desc`  | `asc` / `desc`. |

**Deletes are soft.** `DELETE` never removes a row — it flips `status` to `inactive` and returns the
updated record, so old reports keep resolving the names behind `created_by` / `updated_by`.

**Partial updates.** `PUT` bodies carry only the fields you are changing. Sending an empty body is a
422 (`No fields to update`).

**Audit columns.** Every record carries `created_at`, `created_by`, `updated_at`, `updated_by`.
`created_by` / `updated_by` hold the id of the user who made the change (`null` for seeded rows).

---

## Authentication

`POST /auth/login` returns two tokens:

- **access token** — short-lived (15 minutes by default). Send it on every other call:
  `Authorization: Bearer <access_token>`. It carries the role's permission slugs, so the API never
  has to look them up per request. A role change therefore takes effect on the next refresh.
- **refresh token** — long-lived (7 days). Only used against `POST /auth/refresh`.

Refresh tokens **rotate**: the token you present is revoked the moment a new pair is issued, so a
stolen one is usable at most once. Only the SHA-256 hash of the token is stored, so a database leak
cannot be replayed. Logging out, changing a password, changing a user's role or deactivating a user
all revoke that user's tokens.

```
POST /auth/login                    -> access + refresh
Authorization: Bearer <access>      -> every other endpoint
POST /auth/refresh (access expired) -> a fresh pair
```

---

## Permissions

A permission slug is always `module:action`. Permissions belong to the **role**, not to the user.
`SUPER_ADMIN` passes every check, so a newly added permission can never lock the owner out.

| Module         | Actions |
|----------------|---------|
| `users`        | view, create, edit, delete |
| `roles`        | view, create, edit, delete, assign |
| `regions`      | view, create, edit, delete |
| `distributors` | view, create, edit, delete |
| `brands`       | view, create, edit, delete |
| `products`     | view, create, edit, delete, import |
| `product_mrp`  | view, create, import |
| `imports`      | view |

Built-in roles and their seeded access:

| Role            | Access |
|-----------------|--------|
| `SUPER_ADMIN`   | Everything, always. |
| `ADMIN`         | `users:view/create/edit`, plus everything on regions, distributors, brands, products, MRP and imports. |
| `SALES_PERSON`  | The `:view` action on regions, distributors, brands, products, MRP and imports. |

States carry no permission module — reference data is readable by any signed-in user, because the
region screen needs the dropdown.

---

## Health

### `GET /health`

No token required. Returns 200 while the database answers, 503 when it does not.

```json
{
  "success": true,
  "status": "ok",
  "database": "up",
  "uptime_seconds": 3,
  "timestamp": "2026-08-14T05:05:09+00:00"
}
```

---

## Auth endpoints

### `POST /auth/login`

Rate limited (20 attempts per 15 minutes per IP). No token required.

```json
{ "email": "superadmin@taktak.com", "password": "SuperAdmin@123" }
```

```json
{
  "success": true,
  "message": "Logged in successfully",
  "data": {
    "access_token": "eyJhbGciOi...",
    "refresh_token": "eyJhbGciOi...",
    "user": {
      "id": 1,
      "full_name": "Super Admin",
      "email": "superadmin@taktak.com",
      "role": "SUPER_ADMIN",
      "role_id": 1,
      "permissions": ["brands:create", "brands:delete", "..."]
    }
  }
}
```

An unknown email and a wrong password both return `401 Invalid email or password` — the API does not
confirm which addresses exist. A deactivated account, or one whose role has been deactivated, gets a
403 with an explanation.

### `POST /auth/refresh`

No token required (the refresh token *is* the credential).

```json
{ "refresh_token": "eyJhbGciOi..." }
```

Returns the same shape as login. The presented token is revoked in the process.

### `POST /auth/logout`  *(auth)*

With `{ "refresh_token": "..." }` only that session ends. With an empty body, **every** session for
the caller ends.

### `GET /auth/me`  *(auth)*

The signed-in user with their role and the full list of permission slugs.

### `POST /auth/change-password`  *(auth)*

```json
{ "old_password": "SuperAdmin@123", "new_password": "NewPass2026" }
```

New passwords need at least 8 characters, one letter and one number, and must differ from the old
one. Every other session is ended.

---

## Users

`search` covers full name and email. Sortable: `id`, `full_name`, `email`, `status`, `created_at`,
`last_login_at`. Default sort `created_at desc`.

| Method | Path | Permission |
|--------|------|-----------|
| GET    | `/users` | `users:view` |
| GET    | `/users/{id}` | `users:view` |
| POST   | `/users` | `users:create` |
| PUT    | `/users/{id}` | `users:edit` |
| PATCH  | `/users/{id}/activate` | `users:edit` |
| PATCH  | `/users/{id}/reset-password` | `users:edit` |
| DELETE | `/users/{id}` | `users:delete` |

Extra filter: `role_id`.

**Create**

```json
{
  "full_name": "Asha Rao",
  "email": "asha@taktak.com",
  "password": "Welcome2026",
  "role_id": 2,
  "status": "active"
}
```

**Record**

```json
{
  "id": 4,
  "full_name": "Asha Rao",
  "email": "asha@taktak.com",
  "role_id": 2,
  "role": { "id": 2, "name": "ADMIN", "description": "Manages masters, products and imports" },
  "status": "active",
  "last_login_at": null,
  "created_at": "2026-08-14 05:20:11",
  "created_by": 1,
  "updated_at": "2026-08-14 05:20:11",
  "updated_by": 1
}
```

`password_hash` is never returned by any endpoint.

**Rules worth knowing**

- Changing `role_id`, or deactivating the account, revokes that user's tokens immediately.
- You cannot deactivate your own account.
- The last active `SUPER_ADMIN` cannot be deactivated or moved to another role — that would lock
  everyone out of the roles screen.
- Reactivating a user fails while the role they sit on is inactive.

---

## Roles

`search` covers the name. Sortable: `id`, `name`, `status`, `created_at`. Default sort `id desc`.

| Method | Path | Permission |
|--------|------|-----------|
| GET    | `/roles` | `roles:view` |
| GET    | `/roles/{id}` | `roles:view` |
| POST   | `/roles` | `roles:create` |
| PUT    | `/roles/{id}` | `roles:edit` |
| PUT    | `/roles/{id}/permissions` | `roles:assign` |
| DELETE | `/roles/{id}` | `roles:delete` |

List rows carry `user_count` so the screen can warn before a role is deactivated. `GET /roles/{id}`
returns the role with its `permissions` array.

**Create**

```json
{
  "name": "REGIONAL_MANAGER",
  "description": "Reads everything, edits nothing",
  "status": "active",
  "permission_ids": [1, 5, 9]
}
```

Names allow letters, numbers, spaces, `_` and `-`.

**Replace the permission set**

```json
PUT /roles/5/permissions
{ "permission_ids": [1, 2, 3, 7] }
```

The screen sends the whole tick-box state, so the set is replaced rather than merged — send `[]` to
strip a role bare. Kept behind `roles:assign`, separate from `roles:edit`, so someone who can rename
a role cannot also hand it extra powers. `SUPER_ADMIN` cannot be edited.

**Rules worth knowing**

- Built-in roles (`is_system: true`) cannot be renamed, deactivated or deleted.
- A role with active users on it cannot be deactivated — move them first.

---

## Permissions master list

### `GET /permissions`  *(`roles:view`)*

The whole active list, grouped by module — the shape the role screen needs to draw its tick boxes.

```json
{
  "total": 30,
  "modules": [
    {
      "module": "users",
      "permissions": [
        { "id": 1, "slug": "users:view", "module": "users", "action": "view", "name": "View Users" }
      ]
    }
  ]
}
```

---

## States

### `GET /states`  *(any signed-in user)*

Reference data, seeded once — there is no create / update / delete endpoint. `search` covers name and
code, sorted by name. Default page size 100, max 500.

```json
{ "id": 11, "name": "Karnataka", "code": "KA", "status": "active" }
```

---

## Regions

`search` covers the name. Sortable: `id`, `name`, `status`, `created_at`. Default sort `name desc`.
Extra filter: `state_id` (regions containing that state).

| Method | Path | Permission |
|--------|------|-----------|
| GET    | `/regions` | `regions:view` |
| GET    | `/regions/{id}` | `regions:view` |
| POST   | `/regions` | `regions:create` |
| PUT    | `/regions/{id}` | `regions:edit` |
| DELETE | `/regions/{id}` | `regions:delete` |

**Create**

```json
{ "name": "South", "status": "active", "state_ids": [11, 12, 23, 24] }
```

At least one state is required. On update, sending `state_ids` replaces the whole set; leaving it out
keeps the current one.

**Record**

```json
{
  "id": 1,
  "name": "South",
  "status": "active",
  "states": [
    { "id": 11, "name": "Karnataka", "code": "KA" },
    { "id": 12, "name": "Kerala", "code": "KL" }
  ]
}
```

A region cannot be deactivated while active distributors still cover it.

---

## Distributors

`search` covers name and code. Sortable: `id`, `name`, `code`, `status`, `created_at`. Default sort
`name desc`. Extra filter: `region_id`.

| Method | Path | Permission |
|--------|------|-----------|
| GET    | `/distributors` | `distributors:view` |
| GET    | `/distributors/{id}` | `distributors:view` |
| POST   | `/distributors` | `distributors:create` |
| PUT    | `/distributors/{id}` | `distributors:edit` |
| DELETE | `/distributors/{id}` | `distributors:delete` |

**Create**

```json
{ "name": "Sunrise Traders", "code": "SUN-01", "status": "active", "region_ids": [1, 2] }
```

`code` is optional but unique when present. At least one region is required, and every region must be
active. Each record returns its regions with their states folded in:

```json
{
  "id": 3,
  "name": "Sunrise Traders",
  "code": "SUN-01",
  "status": "active",
  "regions": [
    { "id": 1, "name": "South", "states": [{ "id": 11, "name": "Karnataka", "code": "KA" }] }
  ]
}
```

---

## Brands

`search` covers name and code. Sortable: `id`, `name`, `code`, `status`, `created_at`. Default sort
`name desc`.

| Method | Path | Permission |
|--------|------|-----------|
| GET    | `/brands` | `brands:view` |
| GET    | `/brands/{id}` | `brands:view` |
| POST   | `/brands` | `brands:create` |
| PUT    | `/brands/{id}` | `brands:edit` |
| DELETE | `/brands/{id}` | `brands:delete` |

```json
{ "name": "Acme", "code": "ACM", "status": "active" }
```

Name and code are each unique. A brand cannot be deactivated while active products belong to it.

---

## Products

`search` covers SKU and product name. Sortable: `id`, `sku`, `product_name`, `status`, `created_at`.
Default sort `created_at desc`. Extra filter: `brand_id`.

| Method | Path | Permission |
|--------|------|-----------|
| GET    | `/products` | `products:view` |
| GET    | `/products/{id}` | `products:view` |
| POST   | `/products` | `products:create` |
| PUT    | `/products/{id}` | `products:edit` |
| DELETE | `/products/{id}` | `products:delete` |
| GET    | `/products/price-list` | `product_mrp:view` |

**Create** — an opening price may be supplied, in which case the product and its first MRP row are
written in one transaction:

```json
{
  "brand_id": 1,
  "sku": "SKU-001",
  "product_name": "Acme Widget 500g",
  "status": "active",
  "mrp": 120.00,
  "effective_from": "2026-01-01"
}
```

`effective_from` defaults to today. SKUs are unique; the brand must exist and be active.

**List row** — each product carries the price currently in force:

```json
{
  "id": 7,
  "brand_id": 1,
  "brand": { "id": 1, "name": "Acme", "code": "ACM" },
  "sku": "SKU-001",
  "product_name": "Acme Widget 500g",
  "status": "active",
  "current_mrp": {
    "id": 12, "product_id": 7, "mrp": 120.0,
    "effective_from": "2026-01-01", "effective_to": null
  }
}
```

`GET /products/{id}` adds `mrps`, the full history, newest first.

Deactivating a product leaves its MRP history untouched, so old reports keep pricing correctly.

### `GET /products/price-list`  *(`product_mrp:view`)*

Reads the `v_current_price_list` view — every active product with the price in force today. Optional
`search` matches SKU, product name or brand name.

```json
[
  {
    "product_id": 7,
    "sku": "SKU-001",
    "product_name": "Acme Widget 500g",
    "brand_name": "Acme",
    "mrp": 120.0,
    "effective_from": "2026-01-01"
  }
]
```

---

## Product MRP

Prices are **never overwritten**. Recording a new MRP closes the row currently in force on the day
before the new one starts, and inserts a fresh open row. That is what lets a report priced for any
past month pick the row whose date range covers it:

| mrp | effective_from | effective_to |
|-----|----------------|--------------|
| 100 | 2026-01-01     | 2026-03-31   |
| 110 | 2026-04-01     | `null`       | ← in force

### `GET /products/{id}/mrp`  *(`product_mrp:view`)*

The whole history, newest first.

### `POST /products/{id}/mrp`  *(`product_mrp:create`)*

```json
{ "mrp": 110.00, "effective_from": "2026-04-01" }
```

Returns 201 with the updated product. Refused when:

- another row already starts on that date (409), or
- `effective_from` is not after the current row's start date (400).

### `GET /products/{id}/mrp/on-date?on_date=2026-02-15`  *(`product_mrp:view`)*

The reporting lookup: the row whose date range covers `on_date`, or a 404 when the product had no
price then.

---

## CSV imports

The pipeline from the schema document:

```
CSV file -> product_import_staging -> check each row -> products / product_mrp
```

Every line lands in staging first, as plain text, because a bad file may carry words where a number
was expected. Rows that fail a check stay in staging with the reason in `error_message`, so the user
can be shown exactly which line failed and why. Each good row is applied in its **own transaction**,
so one bad line never rolls back the file.

| Method | Path | Permission |
|--------|------|-----------|
| GET    | `/imports` | `imports:view` |
| GET    | `/imports/{id}` | `imports:view` |
| GET    | `/imports/{id}/rows` | `imports:view` |
| GET    | `/imports/template?module=products` | `imports:view` |
| POST   | `/imports/products` | `products:import` |
| POST   | `/imports/product-mrp` | `product_mrp:import` |

### `POST /imports/products`

`multipart/form-data`:

| Field | Required | Notes |
|-------|----------|-------|
| `file` | yes | The `.csv` file (max 10 MB, 50 000 rows). |
| `effective_from` | no | Start date for any prices in the file. Defaults to today. |
| `create_missing_brands` | no | `true` to create brands that do not exist yet instead of failing those rows. |

Columns: `brand_name`, `sku`, `product_name`, `mrp` (optional). Header spelling is forgiving —
`brand`, `brand name`, `product`, `product name`, `sku code`, `price` and `mrp price` all map to the
right column.

```bash
curl -X POST http://localhost:8080/api/v1/imports/products \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@products.csv" \
  -F "create_missing_brands=true" \
  -F "effective_from=2026-04-01"
```

An existing SKU is updated rather than duplicated, and re-importing an unchanged price does not spawn
a duplicate history row.

### `POST /imports/product-mrp`

Same shape, without `create_missing_brands`. Columns: `sku`, `mrp` — both required, and every SKU
must already exist. A row whose price equals the current one is reported as an error rather than
silently skipped, so the file can be corrected.

### Batch record

```json
{
  "id": 4,
  "file_name": "products.csv",
  "module": "products",
  "total_records": 120,
  "success_records": 118,
  "failed_records": 2,
  "status": "completed"
}
```

`status` is `failed` only when every row failed; a partial success stays `completed` with a non-zero
`failed_records`.

### `GET /imports/{id}/rows`

Filter with `row_status` (`pending`, `valid`, `error`, `processed`). `row_number` is the line number
in the uploaded file, counting the header as line 1.

```json
{
  "id": 91,
  "import_batch_id": 4,
  "row_number": 17,
  "brand_name": "Acme",
  "sku": "SKU-016",
  "product_name": "Acme Widget 2kg",
  "mrp": "abc",
  "status": "error",
  "error_message": "MRP \"abc\" is not a number"
}
```

---

## Errors

```json
{ "success": false, "message": "Validation failed", "errors": [
  { "field": "email", "message": "A valid email address is required" }
] }
```

| Status | When |
|--------|------|
| 400 | A business rule rejected the request (inactive brand, price date out of order, bad upload). |
| 401 | Missing, malformed or expired access token; wrong credentials. |
| 403 | Signed in, but the role lacks the permission the endpoint needs; or the account/role is inactive. |
| 404 | No such record, or no such route. |
| 409 | A unique value is already taken (email, SKU, brand name, role name, distributor code) or a price already starts on that date. |
| 422 | Validation failed — `errors` lists the offending fields. |
| 429 | Rate limit exceeded. |
| 500 | Unexpected failure. The message is generic in production; details go to the log. |

Rate limits, per IP, per 15 minutes: 20 on `/auth/login` and `/auth/refresh`, and a general budget on
everything else (2000 in development, 300 in production — both configurable). `/health` and `/docs`
are exempt.
