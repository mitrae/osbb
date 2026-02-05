# OSBB Resident Portal

Monorepo for a residential building management system (OSBB — Ukrainian housing cooperative). Symfony 6.4 API backend + Nuxt 3 frontend, fully containerized with Docker.

## Architecture

```
/api            — Symfony 6.4 + API Platform (PHP 8.2)
/frontend       — Nuxt 3 + Pinia (Node 18)
docker-compose.yml — MySQL 8.0, API, Frontend containers
```

Everything runs in Docker. No local PHP or Node required.

## Running Locally

```bash
docker compose up -d
```

| Service    | URL                        | Port |
|------------|----------------------------|------|
| API        | http://localhost:8000       | 8000 |
| API Docs   | http://localhost:8000/api/docs | 8000 |
| Frontend   | http://localhost:3000       | 3000 |
| MySQL      | mysql://osbb:osbb@localhost | 3306 |

## Common Commands

```bash
# PHP / Symfony (always through Docker)
docker compose run --rm api php bin/console <command>
docker compose run --rm api composer <command>

# Frontend / Node
docker compose run --rm frontend npm <command>

# Database migrations
docker compose run --rm api php bin/console doctrine:migrations:diff
docker compose run --rm api php bin/console doctrine:migrations:migrate --no-interaction

# Clear cache
docker compose run --rm api php bin/console cache:clear
```

## API Backend (`/api`)

### Entities (in `src/Entity/`)

- **Organization** — id, name, address, createdAt
- **Building** — id, organization (ManyToOne), address, createdAt
- **User** — id, email, password, firstName, lastName, phone, roles (JSON), organization, building, apartment, createdAt. Implements `UserInterface` + `PasswordAuthenticatedUserInterface`. `getUserIdentifier()` returns `'user:' . email`.
- **Admin** — id, email, password, roles (JSON, default `['ROLE_ADMIN']`), createdAt. Separate entity for platform admins. `getUserIdentifier()` returns `'admin:' . email`. Same email can exist in both User and Admin tables.
- **Request** — id, title, description, status (new/in_progress/resolved/rejected), author (ManyToOne→User), assignee (nullable), organization (nullable), createdAt, updatedAt
- **Survey** — id, title, description, organization, createdBy (User), isActive, startDate, endDate, createdAt
- **SurveyQuestion** — id, survey (ManyToOne), questionText
- **SurveyVote** — id, question (ManyToOne→SurveyQuestion), user (ManyToOne→User), vote (bool). Unique constraint on (question, user).

### Authentication

- `POST /api/login` — User JSON login (`{email, password}`), returns `{token}` (JWT)
- `POST /api/admin/login` — Admin JSON login (`{email, password}`), returns `{token}` (JWT)
- `POST /api/register` — Self-registration (`{email, password, firstName, lastName, phone?}`), creates user with ROLE_RESIDENT
- JWT `username` claim is prefixed: `user:email@example.com` or `admin:email@example.com`
- JWT includes custom claims: `id`, `type` (user/admin), `firstName`+`lastName` (user only), `roles`, `username`
- Custom claims set by `JWTCreatedListener` (`src/EventListener/`)
- `TypeAwareUserProvider` (`src/Security/`) resolves JWT → User or Admin entity by parsing prefix

### Role Hierarchy

```
ROLE_ADMIN > ROLE_MANAGER > ROLE_RESIDENT > ROLE_USER
```

### API Security (per-operation)

| Resource       | GET list   | GET item | POST      | PATCH     | DELETE |
|----------------|------------|----------|-----------|-----------|--------|
| Organization   | ADMIN      | USER     | ADMIN     | ADMIN     | ADMIN  |
| Building       | USER       | USER     | ADMIN     | ADMIN     | ADMIN  |
| User           | MANAGER    | self     | ADMIN     | ADMIN/self| ADMIN  |
| Admin          | ADMIN      | ADMIN    | ADMIN     | ADMIN     | ADMIN  |
| Request        | USER       | USER     | RESIDENT  | MANAGER   | ADMIN  |
| Survey         | USER       | USER     | MANAGER   | MANAGER   | ADMIN  |
| SurveyQuestion | USER       | USER     | MANAGER   | MANAGER   | MANAGER|
| SurveyVote     | USER       | USER     | RESIDENT  | —         | ADMIN  |

### State Processors (`src/State/`)

- **UserPasswordHasher** — hashes `plainPassword` on User POST/PATCH
- **AdminPasswordHasher** — hashes `plainPassword` on Admin POST/PATCH
- **RequestProcessor** — auto-sets `author` and `organization` from JWT on POST (User only, rejects Admin)
- **SurveyProcessor** — auto-sets `createdBy` from JWT on POST (User only, rejects Admin)
- **VoteProcessor** — auto-sets `user` from JWT on POST (User only, rejects Admin)

### Key Config Files

- `config/packages/security.yaml` — firewalls (admin_login + login + api/jwt), three providers (user, admin, jwt/TypeAware), role hierarchy, access control
- `config/packages/api_platform.yaml` — API Platform settings, JSON+JSON-LD formats
- `config/packages/nelmio_cors.yaml` — CORS (allows localhost origins)
- `config/packages/lexik_jwt_authentication.yaml` — JWT key paths
- `config/services.yaml` — state processor wiring, JWT event listener
- `.env` — DATABASE_URL, CORS_ALLOW_ORIGIN, JWT keys

### Important Notes

- `phpdocumentor/reflection-docblock` must stay at ^5.x (v6 is incompatible with symfony/property-info v6.4)
- Organization on Request is nullable (users may not have an org assigned yet)
- Table name for User is backtick-quoted (`user`) since it's a MySQL reserved word
- Table name for Request is backtick-quoted (`request`) for the same reason

## Frontend (`/frontend`)

### Stack

- Nuxt 3 with file-based routing
- Pinia for state management (`@pinia/nuxt`)
- No CSS framework — custom lightweight styles in `layouts/default.vue`

### Pages (`pages/`)

| Route             | File                      | Description                              |
|-------------------|---------------------------|------------------------------------------|
| `/login`          | `pages/login.vue`         | User email/password login form           |
| `/admin/login`    | `pages/admin/login.vue`   | Admin email/password login form          |
| `/register`       | `pages/register.vue`      | Self-registration form                   |
| `/`               | `pages/index.vue`         | Dashboard with request/survey counts     |
| `/requests`       | `pages/requests/index.vue`| Request list + create form               |
| `/requests/:id`   | `pages/requests/[id].vue` | Request detail + status update (managers)|
| `/surveys`        | `pages/surveys/index.vue` | Survey list + create form (managers)     |
| `/surveys/:id`    | `pages/surveys/[id].vue`  | Survey detail + voting (yes/no)          |

### Auth Flow

- `stores/auth.ts` — Pinia store: login, adminLogin, register, logout, restore from localStorage. User type (`user`/`admin`) tracked.
- `plugins/auth.client.ts` — restores auth state from localStorage on page load
- `middleware/auth.global.ts` — redirects unauthenticated users to `/login`, authenticated users away from login/register
- `composables/useApi.ts` — fetch wrapper that attaches JWT `Authorization` header and handles 401→redirect

### Runtime Config

- `NUXT_PUBLIC_API_BASE` — API base URL (default: `http://localhost:8000`)

## Heroku Deployment

Both apps deploy from the monorepo using the [subdir buildpack](https://github.com/timanovsky/subdir-heroku-buildpack).

**API app:**
- Buildpack: `heroku/php`
- `PROJECT_PATH=api`
- Procfile: `web: heroku-php-apache2 public/`
- Config vars: `APP_ENV=prod`, `APP_SECRET`, `JWT_PASSPHRASE`, `CORS_ALLOW_ORIGIN`, `JAWSDB_URL` (auto from addon)
- Post-deploy: `bin/heroku-postdeploy.sh` runs migrations

**Frontend app:**
- Buildpack: `heroku/nodejs`
- `PROJECT_PATH=frontend`
- Procfile: `web: node .output/server/index.mjs`
- Config vars: `NUXT_PUBLIC_API_BASE=https://<api-app>.herokuapp.com`
