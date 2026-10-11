# School Management System — Phase 1

Bilingual (Arabic RTL by default, English) school management system for one school
(KG1–KG3, Grades 1–12, two semesters). Phase 1 covers **authentication, roles and
permissions, user administration and the audit log**.

```
/backend   Laravel 13 (PHP 8.3) API · MySQL 8 · Redis · Sanctum SPA cookie auth
/frontend  Vue 3 + Vite + Vuetify 3 + Pinia + vue-i18n
```

> **Why Laravel 13, not 11?** Every Laravel 11 release is affected by security
> advisories published in 2026 that will never be patched (11.x is end-of-life):
> CRLF injection in the `email` validation rule, signed-URL path confusion and a
> debug-page XSS. Composer refuses to install 11.x for this reason. Laravel 13 has
> the same application structure, so nothing in the design changes.

---

## 1. Run it with Docker (Laravel Sail)

Requirements: Docker Desktop (on Windows: with WSL 2), Git.

```bash
cd backend
cp .env.example .env               # then edit: SUPER_ADMIN_EMAIL=you@yourschool.edu

# Install PHP dependencies without needing PHP on the host
docker run --rm -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php83-composer:latest composer install --ignore-platform-reqs

./vendor/bin/sail up -d            # API :8000, SPA :5173, MySQL, Redis, queue worker
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

Open **http://localhost:5173**. You'll see the yellow **DEV MODE** banner and a list of
test users (one per role, `*@example.test`). Click one to sign in.

Frontend env (optional): `cp frontend/.env.example frontend/.env` (defaults to `http://localhost:8000`).

## 2. Run it without Docker (PHP 8.3 + Node 22 on the host)

```bash
cd backend
cp .env.example .env
# Use SQLite + file cache instead of MySQL/Redis:
#   DB_CONNECTION=sqlite   (and delete the DB_HOST..DB_PASSWORD lines)
#   CACHE_STORE=file       QUEUE_CONNECTION=database
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000

cd ../frontend
npm install
npm run dev                         # http://localhost:5173
```

## 3. Tests

```bash
cd backend  && ./vendor/bin/pest     # or: ./vendor/bin/sail pest
cd frontend && npm test
```

Backend tests use in-memory SQLite, so they run without Docker.

---

## Sign-in methods

| Method | When it is available | Route |
|---|---|---|
| **Google (school domain only)** | `GOOGLE_LOGIN_ENABLED=true` **and** client id, secret and `SCHOOL_GOOGLE_DOMAIN` set | `GET /auth/google/redirect` |
| **Dev login** | `APP_ENV=local` **and** `DEV_LOGIN_ENABLED=true`. Otherwise the routes do not exist (404) | `GET /auth/dev/users`, `POST /auth/dev/login` |
| **Break-glass** | Always. Only for the `SUPER_ADMIN_EMAIL` account, after setup | SPA page `/break-glass` → `POST /auth/break-glass` |

**Google rules:**

- Both Google's `hd` claim and the email domain must equal `SCHOOL_GOOGLE_DOMAIN`.
- The email must be verified by Google.
- The user must already exist and be active. Signing in never creates accounts.
- A user is matched by `google_sub` first, then by email (and `google_sub` is then saved).
- If Google login is on but `SCHOOL_GOOGLE_DOMAIN` is empty, the app refuses to start.

### Switching on Google sign-in

1. In Google Cloud Console, create an OAuth client of type *Web application*.
   Set the authorised redirect URI to `GOOGLE_REDIRECT_URI` (default: `http://localhost:8000/auth/google/callback`).
2. Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `SCHOOL_GOOGLE_DOMAIN=yourschool.edu`
   and `GOOGLE_LOGIN_ENABLED=true`.
3. Create the users first (admin form or CSV import). Their emails must be on the school domain.

### Setting up break-glass

```bash
./vendor/bin/sail artisan break-glass:setup    # prompts for a 16+ char password, prints the TOTP secret once
./vendor/bin/sail artisan break-glass:unlock   # clears a lockout early
```

Break-glass locks after 5 failed attempts for `BREAK_GLASS_LOCK_MINUTES` (default 30).
There is also a per-IP limit of 10 attempts per minute. TOTP codes are single-use.
Every attempt is in the audit log.

## Roles and permissions

| Role | manage-users | manage-roles | import-users | view-audit-log | manage-grading |
|---|:-:|:-:|:-:|:-:|:-:|
| super-admin | ✓ | ✓ | ✓ | ✓ | ✓ |
| it | ✓ | ✓ | ✓ | ✓ | |
| admin | ✓ | | ✓ | ✓ | ✓ |
| hod | | | | | ✓ |
| teacher, student, parent | | | | | |
| guard, driver, helper *(reserved)* | | | | | |

Who can assign which roles:

- Only a **super-admin** can assign `super-admin`.
- `manage-roles` (IT) is needed to assign `it`, `admin` or `hod`.
- Others with `manage-users` can assign the remaining roles.
- Nobody can edit or deactivate a user who holds a role they couldn't assign, and nobody can deactivate themselves.

Every API endpoint checks a Policy on the server. The sidebar only hides links.

## API overview

All `/api/*` routes require a signed-in, active user (Sanctum session cookie + CSRF).

| Method | Path | Permission |
|---|---|---|
| GET | `/api/me` · PATCH `/api/me/locale` | signed in |
| GET | `/api/roles` | manage-users or manage-roles |
| GET/POST | `/api/users` | manage-users |
| GET/PUT | `/api/users/{ulid}` | manage-users (+ role rules) |
| POST | `/api/users/{ulid}/activate` · `/deactivate` | manage-users (+ role rules, not self) |
| GET | `/api/users/import/template` | import-users |
| POST | `/api/users/import/preview` → `/commit` | import-users |
| GET | `/api/audit-logs` | view-audit-log |

**CSV import:** header `name,email,roles,locale,phone`. Separate multiple roles with `;`.
`locale` defaults to `ar`. The import has two steps:

1. **Preview** validates every row and saves nothing.
2. **Commit** re-validates and saves only the rows that are still valid.

A preview is tied to the admin who uploaded it and expires after 30 minutes.

## Security notes

- **Sessions:** httpOnly, SameSite=Lax session cookie, encrypted at rest, `Secure` in production.
  No tokens in `localStorage` (only the UI language is stored there).
- **Sessions use the database driver** (not Redis) so deactivating a user can delete every
  session they have. `EnsureUserIsActive` also logs out an inactive user on their next request.
- **Login hardening:** OAuth `state` is verified, the session id and CSRF token are regenerated
  after login, and login routes are rate-limited (10/min per IP).
- **Public IDs:** URLs use ULIDs and the numeric id is never exposed.
  Only `name, email, phone, locale` are mass-assignable.
- **CORS:** allowed only for `FRONTEND_URL`, with credentials. Sanctum treats only that origin as stateful.
- **Headers:** CSP, X-Frame-Options DENY, nosniff, Referrer-Policy on every API response,
  and HSTS in production. The production SPA image (`frontend/Dockerfile`) adds its own CSP.
- **Personal data:** phone numbers and the TOTP secret are encrypted.
  Phone numbers and secrets are excluded from audit old/new values.
  No personal data is written to application logs. Login history lives only in the access-controlled audit log.
- **Seeding:** test users seed only when `APP_ENV=local`. The seeder throws in any other environment.
