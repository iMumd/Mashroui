# Mashroui — Backend

API backend for **Masar** (مسار), a graduation-project management platform — teams, proposals, tasks, meetings, final reports, discussions, and the review workflow between students, supervisors, and the committee.

Laravel 13, API-only, Sanctum for auth, MySQL.

## Stack

- PHP 8.3+, Laravel 13
- Sanctum (token auth)
- MySQL
- Queue driver: `database` (used for email / WhatsApp dispatch jobs)

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

`--seed` creates the super admin, reference data (departments, specializations, academic term), and realistic demo data (teams, proposals, discussions, evaluations). See `database/seeders` for the default logins (`TestUsersSeeder`, `SuperAdminSeeder`).

Email and WhatsApp jobs are **queued**, not sent inline — run a worker or they stay `pending`:

```bash
php artisan queue:work
```

## Roles

Checked via `RoleEnum`: `super_admin`, `committee`, `supervisor`, `student`. Per role/module permissions (projects, proposals, tasks, meetings) are enforced through `AccessControl` and Laravel Policies — super admin bypasses everything via `Gate::before`.

> `team_leader` exists in `RoleEnum` only as **deprecated** legacy data. Leadership is now a capability inside a team (`teams.leader_id`), not a role; current leaders have the `student` role.

## Domain model

- A project happens within an **academic term**. Most data (teams, proposals, discussions) is scoped to the active term via a global scope, so past terms don't leak into current views.
- A **team** has a leader (a student) and a supervisor. Members submit a **proposal**, which a supervisor/committee approves or rejects.
- Once approved, work is tracked with **tasks**, **meetings**, task **notes** and **files**, and a **final report**.
- **Discussion** scheduling records the committee (examiners) per project; **evaluations** combine a supervisor form and examiner forms into a final grade.
- Every sensitive action (approvals, rejections, deletions, bulk sends) is written to an **audit log**.

## Email

All outgoing mail uses branded Blade templates (`resources/views/emails`, `app/Mail`) in the Masar identity, each with a plain-text alternative:

- `PasswordResetMail` — password reset (link valid 2 hours)
- `AccountInviteMail` — account activation / invite
- `GenericNotice` — meeting reminders and staff free-form email

Mail is driver-agnostic. For development `MAIL_MAILER=log` writes messages to `storage/logs/laravel.log`. For production set SMTP in `.env` and it just works:

```env
MAIL_MAILER=smtp
MAIL_HOST=...        MAIL_PORT=587
MAIL_USERNAME=...    MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@your-domain"
MAIL_FROM_NAME="مسار"
```

Reset/invite links point at `FRONTEND_URL` (`/reset-password?token=...`), so set that to the deployed frontend origin.

## Notifications

Credentials and announcements go out over email or WhatsApp. WhatsApp is not the paid Business API — the system builds a `wa.me` link per recipient for a staff member to send. Delivery status is tracked in `message_deliveries`.

## Auth & rate limiting

Token-based via Sanctum. New users are invited by email/link and set their own password on first login; a `force-password-change` gate blocks API access until that's done.

Rate limiters (`app/Providers/AppServiceProvider.php`), tuned against brute force and mail/SMS spam:

| Limiter | Routes | Limit |
|---|---|---|
| `login` | `POST /login` | 5/min per email+IP **and** 20/min per IP |
| `forgot-password` | `POST /forgot-password` | 1/min + 5/hour per email, 10/hour per IP |
| `email-send` | `POST /email/send`, `/whatsapp/send` | 10/min + 100/hour per user |
| `bulk-notify` | `POST /notify/bulk/send` | 5/min per user |
| `api` | authenticated routes | 300/min per user |
| `public` | public landing/archive routes | 30/min per IP |

## Assistant (فِكرة)

`POST /assistant/chat` proxies to an Ollama model (`fikra`) over `FIKRA_API_URL`. In dev this points at a personal ngrok tunnel, not a shared service — it only works while the tunnel owner has it running. Point `FIKRA_API_URL`/`FIKRA_MODEL` in `.env` at your own Ollama instance to use it independently.

## Notes for contributors

- Commits and PRs are in English, even though planning and conversations around the project are in Arabic.
- File uploads (project/task files) are restricted to a document-type whitelist and stored under randomized paths — never trust the original filename.
- Student contact info (email, phone) is visible only to their own team and supervisor, not to every authenticated user — see `App\Support\Rbac\StudentDataVisibility`.
- Code style is enforced with Laravel Pint (`./vendor/bin/pint`).
