# OSBB Resident Portal

Monorepo for a residential building management system (OSBB — Ukrainian housing cooperative). Symfony 6.4 API backend + Nuxt 3 frontend, fully containerized with Docker.

## Architecture

```
/api            — Symfony 6.4 + API Platform (PHP 8.2)
/frontend       — Nuxt 3 + Pinia (Node 18)
docker-compose.yml — MySQL 8.0, API, Frontend containers
```

Everything runs in Docker. No local PHP or Node required.

### Dual Access Model

Users belong to an organization through **either**:
- **OrganizationMembership**: For admin/manager roles (may or may not also be a resident)
- **Resident linkage**: User linked to a Resident record (Resident → Apartment → Building → Organization)

This allows external managers who aren't property owners.

## Test Credentials

All users log in via a single `/login` endpoint.

| Role | Email | Password |
|------|-------|----------|
| Platform Admin | `admin@test.com` | `admin123` |
| Org Admin (Test OSBB) | `mvpuser@test.com` | `password123` |
| Regular User | `test@example.com` | `password123` |
| Regular User | `smoke@test.com` | `password123` |

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

- **Organization** — id, name, city (nullable), address, createdAt
- **Building** — id, organization (ManyToOne), address, createdAt
- **Apartment** — id, building (ManyToOne), number, totalArea, residents (OneToMany→Resident), createdAt
- **User** — id, email, password, firstName, lastName, phone, roles (JSON), createdAt. Single user entity for all roles including platform admins. `getUserIdentifier()` returns email.
- **Resident** — id, firstName, lastName, apartment (ManyToOne), ownedArea, user (nullable ManyToOne→User), createdAt. Admin-created record for real-world property owners, optionally linked to a User account.
- **ConnectionRequest** — id, user (ManyToOne→User), organization, building, apartment, fullName, phone, status (pending/approved/rejected), resident (nullable ManyToOne→Resident), createdAt, updatedAt. User request to link their account to a Resident.
- **OrganizationMembership** — id, user (ManyToOne), organization (ManyToOne), role (ROLE_ADMIN/ROLE_MANAGER), createdAt. Admin-only creation, no self-service join.
- **Request** — id, title, description, status (new/in_progress/resolved/rejected), visibility (public/private/internal), author (ManyToOne→User), assignee (nullable), organization (nullable), createdAt, updatedAt
- **Survey** — id, title, description, organization, createdBy (User), isActive, startDate, endDate, createdAt
- **SurveyQuestion** — id, survey (ManyToOne), questionText
- **SurveyVote** — id, question (ManyToOne→SurveyQuestion), user (ManyToOne→User), vote (bool). Unique constraint on (question, user).

### Authentication

- `POST /api/login` — Single login endpoint for all users (`{email, password}`), returns `{token}` (JWT)
- `POST /api/register` — Self-registration (`{email, password, firstName, lastName, phone?}`), creates user with ROLE_USER
- JWT includes custom claims: `id`, `firstName`, `lastName`, `isPlatformAdmin` (boolean), `roles`, `username`
- Custom claims set by `JWTCreatedListener` (`src/EventListener/`)
- `TypeAwareUserProvider` (`src/Security/`) resolves JWT → User entity (strips legacy `admin:`/`user:` prefixes for backward compat)

### Role Hierarchy

```
ROLE_PLATFORM_ADMIN > ROLE_ADMIN > ROLE_MANAGER > ROLE_RESIDENT > ROLE_USER
```

### API Security (per-operation)

| Resource              | GET list   | GET item | POST           | PATCH          | DELETE         |
|-----------------------|------------|----------|----------------|----------------|----------------|
| Organization          | PLATFORM_ADMIN | USER | PLATFORM_ADMIN | PLATFORM_ADMIN | PLATFORM_ADMIN |
| Building              | USER       | USER     | PLATFORM_ADMIN | PLATFORM_ADMIN | PLATFORM_ADMIN |
| Apartment             | USER       | USER     | PLATFORM_ADMIN | PLATFORM_ADMIN | PLATFORM_ADMIN |
| User                  | MANAGER    | PA/self  | PLATFORM_ADMIN | PLATFORM_ADMIN/self | PLATFORM_ADMIN |
| Resident              | USER       | USER     | PLATFORM_ADMIN | PA or ORG_ADMIN* | PLATFORM_ADMIN |
| ConnectionRequest     | USER       | USER     | USER           | ORG_ROLE_ADMIN | —              |
| OrganizationMembership| USER       | USER     | ORG_ROLE_ADMIN | ORG_ROLE_ADMIN | PLATFORM_ADMIN |
| Request               | USER       | USER     | RESIDENT       | MANAGER        | ADMIN          |
| Survey                | USER       | USER     | MANAGER        | MANAGER        | ADMIN          |
| SurveyQuestion        | USER       | USER     | MANAGER        | MANAGER        | MANAGER        |
| SurveyVote            | USER       | USER     | RESIDENT       | —              | ADMIN          |

### State Processors (`src/State/`)

- **UserPasswordHasher** — hashes `plainPassword` on User POST/PATCH; prevents non-platform-admins from changing roles
- **ConnectionRequestProcessor** — POST: auto-sets user from JWT, validates building→org and apartment→building; PATCH: admin approves (links resident.user) or rejects
- **ResidentProcessor** — DELETE: unlinks user before deletion; PATCH: org admins can only disconnect (set user=null), all other fields are protected
- **RequestProcessor** — auto-sets `author` and `organization` from JWT on POST
- **SurveyProcessor** — auto-sets `createdBy` from JWT on POST
- **VoteProcessor** — auto-sets `user` from JWT on POST, calculates weight from Resident.ownedArea

### Organization Filter Extension

`OrganizationFilterExtension` (`src/Doctrine/`) filters data by org context:
- `X-Organization-Id` header required for collection queries on org-scoped entities
- Platform admins bypass all filters
- Building/Apartment/Resident item lookups bypass filter (needed for IRI denormalization)
- ConnectionRequest: users see own, org admins see all for their org
- OrganizationMembership: users see own, org admins see all for their org

### Key Config Files

- `config/packages/security.yaml` — firewalls (login + api/jwt), role hierarchy, access control
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

| Route                          | File                                    | Description                                         |
|--------------------------------|-----------------------------------------|-----------------------------------------------------|
| `/login`                       | `pages/login.vue`                       | Email/password login form (single endpoint)         |
| `/register`                    | `pages/register.vue`                    | Self-registration form                              |
| `/`                            | `pages/index.vue`                       | Dashboard with request/survey counts                |
| `/connect`                     | `pages/connect.vue`                     | Connect to Resident: org→building→apt selection     |
| `/organizations`               | `pages/organizations/index.vue`         | Org list, create (platform admin), membership view  |
| `/organizations/:id`           | `pages/organizations/[id].vue`          | Org detail, admin management links                  |
| `/organizations/:id/members`   | `pages/organizations/[id]/members.vue`  | Manage org memberships (admin)                      |
| `/organizations/:id/apartments`| `pages/organizations/[id]/apartments.vue`| Apartments & residents management (admin)          |
| `/organizations/:id/requests`  | `pages/organizations/[id]/requests.vue` | Connection request review & approval (admin)        |
| `/requests`                    | `pages/requests/index.vue`              | Request list + create form                          |
| `/requests/:id`                | `pages/requests/[id].vue`               | Request detail + status update (managers)           |
| `/surveys`                     | `pages/surveys/index.vue`               | Survey list + create form (managers)                |
| `/surveys/:id`                 | `pages/surveys/[id].vue`                | Survey detail + voting (yes/no)                     |

### Auth Flow

- `stores/auth.ts` — Pinia store: login, register, logout, restore from localStorage. `isPlatformAdmin` boolean tracked.
- `stores/organization.ts` — Pinia store: loads memberships and resident-based org access. `allOrgs` getter combines both.
- `plugins/auth.client.ts` — restores auth state from localStorage on page load
- `middleware/auth.global.ts` — redirects unauthenticated users to `/login`, authenticated users away from login/register
- `composables/useApi.ts` — fetch wrapper that attaches JWT `Authorization` header, `X-Organization-Id` header, and handles 401→redirect

### Connection Request Flow

1. User goes to `/connect`, selects org → building → apartment, enters name/phone
2. POST to `/api/connection_requests` creates pending request
3. Org admin goes to `/organizations/:id/requests`, sees pending requests
4. Admin clicks "Review" to see residents in the apartment
5. Admin selects matching resident → "Approve & Link" (PATCH with status=approved, resident IRI)
6. Or admin clicks "Reject" (PATCH with status=rejected)
7. On approval, resident.user is set to the requesting user

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
