# SMIS — School Management Information System

Bilingual (Arabic RTL default + English) system for ONE school: KG1–KG3, Grades 1–12, two semesters per year.
Monorepo: `backend/` (Laravel 13, PHP 8.3, MySQL 8, Redis, Sail) and `frontend/` (Vue 3, Vite, Vuetify 3, Pinia, vue-i18n).
Full run instructions and the role/permission matrix are in `README.md`.

## Status
- **Phase 1 done (2026-10-01):** auth (Google school-domain, dev login, break-glass), roles/permissions,
  user admin + CSV import, audit log. 122 Pest + 21 Vitest tests passing.
- Not yet verified: Docker/Sail stack (no Docker on the dev machine yet), real Google OAuth, the UI in a real browser.
- Phases are built one at a time; stop after each phase and summarise.

## Decisions already made (don't revisit without asking)
- Laravel **13**, not 11 — every 11.x release has unpatched 2026 security advisories.
- Vuetify pinned to **3**.
- Sessions use the **database** driver (so deactivation can delete all of a user's sessions); cache + queues use Redis.
- Accounts are created only by admins/IT. Login never creates users.
- Dev login exists only when `APP_ENV=local` AND `DEV_LOGIN_ENABLED=true`, and only for `@example.test` seed users.

## Conventions (follow in every phase)
- Every endpoint: a FormRequest (validation + `authorize()`) or `Gate::authorize()` against a Policy. The menu is not security.
- Never `$request->all()`; use `$request->safe()->only([...])`. Security fields are set explicitly, never mass-assigned.
- Public ids are ULIDs (`ulid` column, route key); never expose numeric `id`.
- Role/permission names live only in `app/Support/Access.php`.
- Audit with spatie/activitylog; never put phone numbers, secrets or other personal data in logs or audit old/new values.
- Every user-facing string goes in both `frontend/src/i18n/ar.js` and `en.js` (a test checks the keys match);
  backend messages go in `lang/ar` and `lang/en`.
- New sidebar items go in `frontend/src/navigation.js` with a `permission`; routes get `meta.permission`.
- Write Pest tests for every new endpoint (including a 403 test for an unauthorised role), and Vitest tests for UI logic.

## Running locally without Docker
PHP 8.3 + Composer are installed via winget at
`C:\Users\amalm\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe`
(`composer` is a wrapper script in that folder). If `php` isn't on PATH, prepend that folder.
- Backend tests: `cd backend && ./vendor/bin/pest` (in-memory SQLite, no Docker needed)
- Style: `cd backend && ./vendor/bin/pint`
- Frontend tests: `cd frontend && npm test`

## Not this project
`C:\Users\amalm\projects\Simple project` is a separate Jetour club app (Google Apps Script). Don't touch it from here.
