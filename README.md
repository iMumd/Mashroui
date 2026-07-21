# Mashroui

API backend for managing graduation projects at a university department — teams, proposals, tasks, meetings, final reports, and the review workflow between students, supervisors, and the committee.

Laravel 13, API-only, Sanctum for auth, MySQL.

## Stack

- PHP 8.3, Laravel 13
- Sanctum (token auth)
- MySQL
- Queue driver: database (used for WhatsApp/email dispatch jobs)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database named `mashroui` (or change `DB_DATABASE` in `.env`), then:

```bash
php artisan migrate --seed
php artisan serve
```

`--seed` creates the initial super admin and reference data (departments, specializations, academic term). Check `database/seeders` for the default login.

## Roles

Five roles, checked via `RoleEnum`: `super_admin`, `committee`, `supervisor`, `team_leader`, `student`. Permissions per role/module (projects, proposals, tasks, meetings) are enforced through `AccessControl` and Laravel Policies — super admin bypasses everything via `Gate::before`.

## How the project is organized

- A project happens within an **academic term**. Most data (teams, proposals, discussions) is scoped to the currently active term via a global scope, so past terms don't leak into current views.
- A **team** has a leader (student) and a supervisor. Team members submit a **proposal** for their project, which a supervisor/committee approves or rejects.
- Once approved, work is tracked with **tasks**, **meetings**, task **notes** and **files**, and a **final report** at the end.
- Every sensitive action (approvals, rejections, deletions, bulk notifications) is written to an **audit log**.

## Notifications

Credentials and announcements go out over email or WhatsApp. WhatsApp isn't the paid Business API — the system generates a `wa.me` link per recipient, and a staff member sends it manually. Delivery status is tracked in `message_deliveries`.

## Auth

Token-based via Sanctum. Login is rate-limited (5 attempts/minute per email+IP). New users are invited by email/link and set their own password on first login — there's a `force-password-change` middleware gate that blocks API access until that's done.

## Notes for contributors

- Commits are in English even though planning docs and conversations around this project are in Arabic.
- File uploads (project files, task files) are restricted to a document type whitelist and stored under randomized paths — never trust the original filename.
- Student contact info (email, phone) is only visible to their own team and supervisor, not to every authenticated user — see `App\Support\Rbac\StudentDataVisibility`.
