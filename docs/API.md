# API Reference

Request/response examples for every endpoint. All requests are JSON except file uploads (`multipart/form-data`). All authenticated requests need `Authorization: Bearer <token>`.

Base URL in local dev: `http://mashroui.local/api`

Standard error shape (Laravel validation):

```json
{
  "message": "The email field is required.",
  "errors": { "email": ["The email field is required."] }
}
```

Endpoints marked **term-scoped** only return rows from the currently active academic term.

---

## Auth

### `POST /login`
No auth. Rate-limited to 5/min per email+IP.

```bash
curl -X POST http://mashroui.local/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@mashroui.local","password":"secret123"}'
```

```json
{ "user": { "id": 1, "name": "...", "role": "super_admin", "..." }, "token": "1|abcdef..." }
```

### `POST /logout`
Auth required. Revokes the current token.

### `GET /me`
Auth + password already changed. Returns the current user with `specialization` and `academicTerm` loaded.

### `GET /me/abilities`
Returns the caller's access level (`full` / `view_only` / `blocked`) per module:

```json
{ "projects": "full", "proposals": "full", "tasks": "view_only", "meetings": "full" }
```

### `POST /me/change-password`
```json
{ "password": "newpassword", "password_confirmation": "newpassword" }
```
Clears `must_change_password`.

---

## Invites

New users don't self-register — a super admin invites an existing user record, they accept the link and set their own password.

### `POST /users/{user}/invite`
Super admin only. Creates a 3-day invite token.

```json
{ "token": "64-char-random-string", "expires_at": "2026-07-24T12:00:00Z" }
```

### `POST /invite/{token}/accept`
No auth. Rate-limited to 10/min per IP.

```json
{ "password": "newpassword", "password_confirmation": "newpassword" }
```
Returns `{ "user": {...}, "token": "..." }`. 410 if the link is expired or already used.

---

## Public

### `GET /projects/featured`
No auth. Rate-limited to 30/min per IP. Cross-term. Paginated (6/page).

```json
{
  "data": [
    { "id": 12, "name": "...", "description": "...", "department_id": 2, "specialization_id": 5 }
  ],
  "links": { "...": "..." },
  "meta": { "current_page": 1, "total": 9 }
}
```

---

## Reference data (departments, specializations, academic terms)

Same shape for all three. `index`/`show` are open to any authenticated user; `store`/`update`/`destroy` require the `manage-org-structure` ability (super admin).

- `GET /departments`, `GET /departments/{id}`
- `POST /departments` — `{ "name": "Computer Science" }`
- `PUT /departments/{id}` — same body
- `DELETE /departments/{id}` — 204, audit-logged

- `GET /specializations`, `GET /specializations/{id}` (returns with `department` loaded)
- `POST /specializations` — `{ "department_id": 2, "name": "Software Engineering", "degree": "bachelor" }`

- `GET /academic-terms`, `GET /academic-terms/{id}`
- `POST /academic-terms` — `{ "name": "2026 Spring", "is_current": true }` (setting `is_current` unsets it on all other terms)

---

## Teams — term-scoped

A team has a `leader_id` (must be one of `member_ids`), a `supervisor_id`, and up to 4 student members.

### `GET /teams`
Returns all teams in the current term, `TeamResource` (student contact info hidden unless you're on the team or staff — see [Need-to-Know](#need-to-know-fields)).

### `POST /teams`
```json
{
  "name": "Team Falcon",
  "supervisor_id": 3,
  "specialization_id": 5,
  "member_ids": [10, 11, 12],
  "leader_id": 10
}
```

### `GET /teams/{id}`
Single `TeamResource`, `members`/`supervisor`/`leader`/`project` loaded.

### `GET /teams/export`
Streams `teams.xlsx`.

### `POST /teams/import/preview`
`multipart/form-data`, field `file` (`.xlsx`). Validates rows without writing anything — returns which rows are valid/invalid.

### `POST /teams/import/confirm`
```json
{
  "specialization_id": 5,
  "rows": [
    { "name": "Sara Ali", "email": "sara@example.com", "university_number": "12010099", "whatsapp": "970599123456" }
  ]
}
```
Creates the teams/users for the current term. `whatsapp` must match `970|972` + mobile format.

---

## Proposals — term-scoped

A proposal belongs to a project (1:1) and carries a PDF plus four free-text fields the AI source endpoint later reads.

### `POST /proposals`
`multipart/form-data`:
```
project_id=7
name=Smart Attendance System
description=...
problems=...
solutions=...
features_value=...
pdf=<file, application/pdf, max 10MB>
```

### `GET /proposals/{id}`
`ProposalResource`, only visible to your own team / supervisor / committee / super admin.

### `PUT /proposals/{id}`
Same fields as create, `pdf` optional (resubmission after rejection).

### `POST /proposals/{id}/approve`
No body. Supervisor/committee only.

### `POST /proposals/{id}/reject`
```json
{ "rejection_reason": "The problem statement needs more detail." }
```
Audit-logged with the reason.

---

## Tasks — term-scoped

### `GET /teams/{team}/tasks`
`TaskResource` collection. 403 if you're not on the team (and not staff).

### `POST /teams/{team}/tasks`
```json
{ "title": "Design the ERD", "description": "Optional detail" }
```

### `GET /tasks/{id}`
Loads `createdBy`, `files.uploadedBy`, `notes.user`.

### `PUT /tasks/{id}`
```json
{ "title": "Updated title" }
```

### `PATCH /tasks/{id}/status`
```json
{ "status": "in_progress" }
```

### `DELETE /tasks/{id}`
204, audit-logged.

### `GET /teams/{team}/progress`
Aggregate task-status counts for the team's board.

### `GET /progress/export`
Streams `progress.xlsx`.

### Task files
- `GET /tasks/{task}/files` — `TaskFileResource` collection
- `POST /tasks/{task}/files` — `multipart/form-data`, field `file` (whitelisted types, max 10MB — see [File uploads](#file-uploads))

### Task notes
- `GET /tasks/{task}/notes`
- `POST /tasks/{task}/notes` — `{ "note": "Talked to the client about scope." }`

---

## Meetings — term-scoped

### `GET /teams/{team}/meetings`
Ordered by `scheduled_at`.

### `POST /teams/{team}/meetings`
```json
{
  "title": "Weekly sync",
  "scheduled_at": "2026-07-25 14:00:00",
  "google_meet_link": "https://meet.google.com/abc-defg-hij",
  "notes": "Optional agenda"
}
```

### `GET /meetings/{id}`

---

## Project files & final reports — term-scoped

### `GET /projects/{project}/files`, `POST /projects/{project}/files`
`multipart/form-data`: `stage` (optional label), `file` (whitelisted types, max 20MB).

### `GET /projects/{project}/final-reports`, `POST /projects/{project}/final-reports`
`multipart/form-data`: `file` (PDF only, max 20MB).

---

## Discussions — term-scoped

The graduation defense session for a project.

### `GET /discussions`
Optional filters: `?department_id=`, `?specialization_id=`.

### `POST /discussions`
```json
{
  "project_id": 7,
  "supervisor_id": 3,
  "place": "Hall A",
  "discussion_date": "2026-08-01",
  "discussion_time": "10:30",
  "committee": "Dr. X, Dr. Y",
  "whatsapp": "970599123456",
  "status": "pending"
}
```

### `GET /discussions/{id}`, `PUT /discussions/{id}` (partial), `DELETE /discussions/{id}` (204, audit-logged)

### `GET /discussions/export`
Streams `discussions.xlsx`.

---

## Restrictions

Supervisors can restrict their own team leader from the `tasks` module; super admin can restrict any supervisor on any module.

### `GET /users/{user}/restrictions`
### `POST /users/{user}/restrictions`
```json
{ "module": "tasks", "level": "blocked" }
```
`level` is `view_only` or `blocked`.

### `DELETE /restrictions/{id}`
204.

---

## Bulk notifications

Super admin only. Sends account credentials/invites over email or a generated WhatsApp (`wa.me`) link.

### `POST /notify/bulk/preview`
```json
{ "user_ids": [10, 11, 12], "channel": "whatsapp" }
```
Returns what *would* be sent without dispatching anything.

### `POST /notify/bulk/send`
Same body. Actually dispatches, one `message_deliveries` row per user, audit-logged as a single `bulk_send` entry.

### `GET /message-deliveries`
Optional filters `?context=` `?status=`.

### `POST /message-deliveries/{id}/retry`
Only works on `failed` deliveries with a still-active invite link.

---

## AI data source

Super admin only, cross-term, paginated (50/page). Feeds an external Ollama pipeline — this endpoint only exposes data, it does not call any AI model itself.

### `GET /ai/projects-source`
Optional filters: `?status=proposed|in_progress|completed`, `?department_id=`, `?specialization_id=`.

```json
{
  "data": [
    {
      "id": 7,
      "name": "Smart Attendance System",
      "description": "...",
      "department": { "id": 2, "name": "..." },
      "specialization": { "id": 5, "name": "..." },
      "academic_term": { "id": 3, "name": "2026 Spring" },
      "status": "in_progress",
      "is_featured": false,
      "proposal": {
        "name": "...", "description": "...",
        "problems": "...", "solutions": "...", "features_value": "..."
      }
    }
  ],
  "meta": { "current_page": 1, "total": 41 }
}
```

---

## File uploads

Every upload endpoint whitelists types via `mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,png,jpg,jpeg` (final reports and proposal PDFs are `pdf` only). Files are stored under a randomized path — the original filename is never trusted or exposed in the storage path.

## Need-to-Know fields

`UserResource` (nested inside team/task/meeting/etc. responses) hides `email`, `whatsapp`, `university_number`, and `employee_number` for student users unless the viewer is that student, a teammate, their supervisor, or staff (committee/super admin). Everyone still sees `id`, `name`, `role`, `status`, `specialization_id`.
