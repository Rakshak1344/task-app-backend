# Task App Backend

A Laravel 13 REST API for a task manager. Authentication is token based via
[Laravel Sanctum](https://laravel.com/docs/sanctum), and every task is scoped to the user who
created it — a user can only ever read or modify their own tasks.

## Requirements

| Tool | Version |
| --- | --- |
| PHP | 8.3 or newer |
| Composer | 2.x |
| Node.js | 20 or newer (with npm) |
| PostgreSQL | 13 or newer |

## Setup

### 1. Clone and install PHP dependencies

```sh
git clone <repository-url> task-app-backend
cd task-app-backend
composer install
```

### 2. Create your environment file

```sh
cp .env.example .env
```

### 3. Create the database and add your credentials to `.env`

> [!IMPORTANT]
> Do this **before** running `composer run setup` or `composer run dev`. The setup script runs
> the database migrations, so it will fail immediately if these values are wrong or the
> database does not exist.

Create an empty database:

```sh
createdb task_app_backend
```

Or from a `psql` session:

```sql
CREATE DATABASE task_app_backend;
```

Then open `.env` and fill in the `DB_*` block to match your local PostgreSQL server:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=task_app_backend
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Run the setup script

```sh
composer run setup
```

This generates the application key, runs the migrations, and installs and builds the frontend
assets, so there is nothing else to do by hand.

## Running the app

```sh
composer run dev
```

This starts four processes together — the PHP development server, the queue listener, live log
tailing via [Pail](https://laravel.com/docs/logging#tailing-logs), and the Vite dev server. The
API is then available at **http://localhost:8000**.

If you only need the HTTP server, `php artisan serve` is enough.

## Tests

The suite runs against a **real PostgreSQL database** rather than in-memory SQLite, so it
exercises the same driver as production — case sensitivity, `LIKE` behaviour and date
handling all match what you get in deployment.

One-time setup:

```sh
createdb task_app_backend_testing
cp .env.testing.example .env.testing   # then fill in DB_USERNAME / DB_PASSWORD
php artisan key:generate --env=testing
```

Then:

```sh
composer run test          # or: ./vendor/bin/pest
```

> [!NOTE]
> The test database is wiped on every run. `tests/TestCase.php` refuses to start unless the
> configured database name ends in `_testing`, so a missing or misconfigured `.env.testing`
> fails loudly instead of dropping your development data.

## API reference

All routes are prefixed with `/api/v1`.

### Public

| Method | Endpoint | Body | Description |
| --- | --- | --- | --- |
| `POST` | `/api/v1/signup` | `name`, `email`, `password` (min 8) | Creates a user and returns an access token |
| `POST` | `/api/v1/login` | `email`, `password` | Returns an access token |

### Authenticated

These require an `Authorization: Bearer <access_token>` header.

| Method | Endpoint | Description |
| --- | --- | --- |
| `POST` | `/api/v1/logout` | Revokes the token used to make the request |
| `GET` | `/api/v1/me` | Returns the authenticated user |
| `GET` | `/api/v1/tasks` | Lists your tasks, newest first. Supports search, filtering, sorting and pagination — see below |
| `POST` | `/api/v1/tasks` | Creates a task |
| `GET` | `/api/v1/tasks/{id}` | Shows a single task |
| `PATCH` | `/api/v1/tasks/{id}` | Updates a task |
| `DELETE` | `/api/v1/tasks/{id}` | Soft deletes a task |

### Listing tasks

`GET /api/v1/tasks` accepts these query parameters. An unrecognised value is rejected with
`422` rather than silently ignored, so a typo in a filter never returns the wrong data.

| Parameter | Values | Description |
| --- | --- | --- |
| `search` | string, max 255 | Case-insensitive partial match on **title**. `%` and `_` are matched literally. |
| `status` | `pending`, `in_progress`, `completed` | Exact status match |
| `priority` | `low`, `medium`, `high` | Exact priority match |
| `sort` | `created_at` (default), `due_date`, `title` | Column to sort by |
| `direction` | `asc`, `desc` (default) | Sort direction |
| `per_page` | 1–100, default 10 | Results per page |
| `page` | integer | Page number |

Parameters combine as an `AND`, and are preserved in the `links.next` / `links.prev` URLs.

```sh
curl -s -G http://localhost:8000/api/v1/tasks \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  --data-urlencode 'search=report' -d 'status=pending' -d 'per_page=5'
```

### Response shape

Every successful response is wrapped consistently:

```jsonc
// single resource
{ "data": { "id": 1, "title": "..." }, "message": "Task created successfully" }

// collection
{ "data": [ ... ], "links": { ... }, "meta": { ... } }

// action-only endpoints (logout, delete)
{ "data": null, "message": "Task deleted successfully" }

// errors
{ "message": "The given data was invalid.", "errors": { "title": ["..."] } }
```

### Task fields

| Field | Rules |
| --- | --- |
| `title` | Required on create, string, max 255 |
| `description` | Optional, string |
| `status` | Optional — `pending` (default), `in_progress`, `completed` |
| `priority` | Optional — `low`, `medium` (default), `high` |
| `due_date` | Optional, any parseable date |

### Example

```sh
# Sign up and capture the token
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/signup \
  -H 'Accept: application/json' \
  -d 'name=Ada&email=ada@example.com&password=secret123' | jq -r .data.access_token)

# Create a task
curl -s -X POST http://localhost:8000/api/v1/tasks \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -d 'title=Write the docs&priority=high'
```
