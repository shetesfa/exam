# Phase 1 + start of Phase 2 — Changelog

Applied against the audit in `PHASE_0_AUDIT.md`. Nothing here changes existing
page behavior for Youth classes; everything is additive or a targeted fix.

## Removed (confirmed dead / unreferenced anywhere in the codebase)
- `promotion_old.php`
- `exam-main/sw.js` (never registered by any page — `/exam/sw.js` is the real one)

## Fixed
- **`sw.js`**: bumped cache to `exam-pwa-v3` (forces a clean update for all
  clients) and added an explicit pass-through so requests to
  `/exam-main/api/*` are never intercepted or cached by the service worker.
  Previously `sync_pull.php` (a GET request) could have been served stale
  from cache after a real sync had already happened.
- **`exam-main/api/sync_push.php`**: attendance pushes now get the same
  conflict check marks already had — if the server's `last_updated` is newer
  than the client's `updated_at`, the push is rejected with
  `conflict: true` instead of silently overwriting.
- **`exam-main/assets/js/sync-manager.js`**: attendance conflicts are now
  actually counted (previously only marks conflicts were tallied — an
  attendance conflict response was silently dropped). Added a `'conflict'`
  status event so a page can show a warning badge.
- **`manifest.json`**: removed a UTF-8 BOM that made the file fail strict
  JSON parsing (`JSON.parse` in Node threw on it; some manifest parsers are
  equally strict). File is otherwise unchanged.
- **`teacher_profile.php`**: photo uploads are now verified as real,
  decodable images (`getimagesize`), not just files with a `.jpg` extension.
  Document uploads are now verified by actual MIME type
  (`mime_content_type`), not just extension.

## Added
- **`APP_DEBUG` flag** in `db.php` (defaults **off**, reads `getenv('APP_DEBUG')`).
  `fix.php` and `debug_students.php` now 404 unless it's explicitly enabled —
  both are legitimate maintenance tools, they just shouldn't be silently
  reachable on a live server. To use them locally/in XAMPP, set the
  environment variable `APP_DEBUG=1` before starting PHP.
- **`uploads/teachers/.htaccess`** and **`uploads/documents/.htaccess`**:
  defense-in-depth — disables script execution in both upload directories
  even if a bad file ever got past the extension/MIME checks.
- **`exam-main/migrations/002_divisions_and_grades.sql`** (+ rollback):
  adds `divisions` and `grades` tables, seeds Children (Grades 1–6) and
  Youth (Grades 7–12), adds a nullable `grade_id` column to the existing
  `classes` table, and best-effort backfills `grade_id` for any class whose
  name already says "Grade 7" / "7ኛ ክፍል" etc. **Non-destructive**: no
  existing table, column, or foreign key is removed or retyped.
- **`exam-main/migrations/003_drop_empty_backup_tables.sql`** (+ rollback):
  drops `marks_backup` and `marks_backup_20250217` — both verified empty.
- **`manage_classes.php`**: add/edit class forms now have an optional
  Division→Grade picker (grouped `<optgroup>` dropdown). Existing classes
  with no grade assigned show a clear "⚠️ No grade assigned" badge instead
  of silently defaulting to something. This is the first real Phase 3 UI.

## Requires before deploying
1. **Back up the live database first** (as UTF-8, not UTF-16 — see the note
   at the top of migration 002).
2. Run `002_divisions_and_grades.sql`, then `003_drop_empty_backup_tables.sql`.
   `manage_classes.php`'s grade picker will error on save if migration 002
   hasn't been run yet (the `grade_id` column won't exist).
3. Set `APP_DEBUG=1` in your local/XAMPP environment if you still need
   `fix.php` or `debug_students.php` — otherwise leave it unset.

## Verified
- Every `.php` file in the project passes `php -l` (no syntax errors).
- Every edited `.js` file passes `node --check` (no syntax errors).
- `manifest.json` now parses as valid JSON.

## Not done yet (next phases, not started)
Children division nav/dashboard cards, lesson plans, OCR, push notifications,
exam reminders, Children calendar, admin backup tooling, audit log,
pagination on student/teacher lists, per-feature locks
(attendance/marks/plan split). These come in later phases, one at a time,
each tested before moving to the next.

---

# Phase 3 (started) — Children attendance rights + Division-aware filters

## Added
- **`db.php`**: new `canMarkAttendance($conn, $userId, $semesterId)` helper.
  True for the existing `attendance_submitter` role, **or** for a `teacher`
  who has an explicit row in `attendance_assignments` for the current
  semester. A teacher with no assignment still only gets the existing
  read-only attendance view — nothing changes for Youth teachers unless an
  admin explicitly assigns them.
- **`dashboard_attendance.php`**: gate widened from "is attendance_submitter"
  to `canMarkAttendance(...)`, so an assigned teacher can use the exact same
  shared-attendance marking screen attendance_submitters already use (same
  table, same unique key, same offline sync path - no second mechanism
  built).
- **`attendance_submitter_assign.php`**: the assignment dropdown and
  overview table now include `teacher` accounts, labeled "(መምህር)" vs
  "(ጸሐፊ)", so admin can grant a Children teacher attendance rights for
  their class(es) using the exact same screen used for Youth's
  attendance_submitters.
- **`mobile_nav.php`**: teachers who currently have attendance-marking
  rights now get an extra "አቴንዳንስ ምዝገባ" (mark attendance) nav item, on top
  of their existing read-only "አቴንዳንስ እይታ" view. Teachers without rights
  see no change.
- **`attendance_controller.php`** (admin grid): the class filter is now
  grouped by Division/Grade (`<optgroup>`), and the stats row now shows
  Present/Absent/Permission as percentages next to the raw counts, closing
  the two gaps called out in the audit (§11/§7).

## Requires
- Migration `002_divisions_and_grades.sql` must already be applied (division
  grouping in the class dropdown falls back to "ያልተመደበ / Unassigned" for
  any class with no grade set - it does not break if some classes aren't
  categorized yet).

## Verified
- All `.php` files still pass `php -l` after these changes.


---

# Phase 3 (continued) + Phase 4/5 start — Calendar, Notifications, Exam Reminders, Split Locks, Lesson Plans, Pagination

## Added (migrations)
- `exam-main/migrations/004_calendar_notifications_audit_locks.sql` (+rollback):
  `calendar_events`, `calendar_event_targets`, `notifications`,
  `notification_targets`, `notification_reads`, `audit_log`, and two new
  columns on `teacher_class` (`attendance_locked`, `plan_locked`) alongside
  the existing `locked` (marks) column.
- `exam-main/migrations/005_lesson_plans.sql` (+rollback): `lesson_plans` and
  `lesson_plan_versions` (full JSON snapshot taken before every edit, so a
  teacher's earlier version is never silently lost).

## Added (db.php helpers)
- `auditLog()`, `createNotification()`, `getNotificationsForUser()`,
  `markNotificationRead()`, `getUnreadNotificationCount()`.

## Added (pages)
- **`calendar_admin.php`** - admin creates/deletes school calendar events
  (Ethiopian date input, 9 event types from the brief, priority, reminder
  intervals, and ALL/Division/Grade/Class targeting).
- **`calendar_view.php`** - read-only calendar for teachers, attendance
  submitters, and students - shows only events targeted at their
  class/grade/division, plus anything untargeted.
- **`notifications.php`** - in-app notification center: unread badge, mark
  one/all as read. This is a real, working in-app notification system.
  **Not implemented: browser Push API notifications** (the kind that show
  up outside the browser tab) - those require an HTTPS domain and
  VAPID/push-service keys that don't exist in this environment. The
  `push_subscriptions` table and service-worker `push` event handler are
  the next step once you have a real domain to test against; building that
  scaffolding now against nothing would just be an untested stub.
- **`exam_reminder_run.php`** - reads exam-type calendar events and their
  `reminder_days_before` intervals (e.g. "14,7,3,1,0"), creates a
  notification for whichever interval is due today. Idempotent (checks a
  marker before inserting, so re-running the same day doesn't duplicate).
  Has both an admin "Run Now" button and a `--cron` CLI mode for a real
  scheduled task if your host supports one.
- **`lesson_plan_editor.php`** (teacher) - full digital lesson plan form
  (all fields from the brief: objective, method, materials, activities,
  homework, evaluation, notes), draft/submit workflow, versioned edits, and
  an *optional* photo-of-the-paper-plan upload for reference.
  **Important - what this does NOT do:** it does not OCR the photo into
  text. Real Amharic handwriting OCR needs either a paid cloud vision API
  or a local Tesseract install with Amharic-trained data, neither of which
  exists in this environment. Faking that with a placeholder would violate
  your own brief's "no fake features" rule. The photo is stored and shown
  next to the form as a reference while the teacher types the fields
  themselves - which is also exactly the fallback your brief said to use
  when OCR isn't reliable ("teacher can edit every field").
- **`lesson_plan_admin_review.php`** - filter by class/teacher/status,
  view full plan detail, leave feedback, approve / request correction /
  mark reviewed, print (dedicated print-friendly view via `@media print`).

## Changed
- **`class_locks.php`**: the single combined lock is now three independent
  per-assignment toggles (Marks / Attendance / Plan), shown as three
  columns in the existing assignments table. The old "lock by class" /
  "lock by teacher" / "lock all" bulk methods still only affect the
  original marks lock (`locked`) - unchanged behavior for existing usage.
- **`mobile_nav.php`**: added Calendar, Notifications, Exam Reminder
  (admin), and Lesson Plan nav entries per role.
- **`manage_students.php`**: added real pagination (30/page) - the list
  query was unbounded before. Search result counts now reflect the true
  total match count, not just the current page's row count.

## Requires
- Migrations 004 and 005 must be applied (in order, after 002 and 003)
  before these pages will work - they all query the new tables/columns.
- `uploads/lesson_plans/` was created with the same `.htaccess` script-
  execution block as the other upload folders.

## Verified
- Every `.php` file passes `php -l` (checked after every batch of changes).
- A static checker was run across every `dbExecute`/`dbFetchAll`/
  `dbFetchOne` call added this session, comparing type-string length against
  parameter count and `?` placeholder count. It caught three real bugs
  (`auditLog`, `createNotification`, and the lesson plan insert/update type
  strings all had swapped int/string positions) - all three are fixed.

## Still not done (real, not started)
Amharic OCR (needs an external service/model - flagged above, not faked),
browser Push notifications (needs a real HTTPS domain + VAPID keys),
admin database backup tooling, teacher list pagination (lower priority -
9 rows today), dashboard summary cards update (Children/Youth split,
pending plans, sync status), full offline support for the new
calendar/notifications/lesson-plan pages (they're online-only for now -
the existing offline layer covers attendance/marks/login only).

---

# Phase 5 (continued) — Dashboard cards, DB backup tool

## Added
- **`dashboard_admin.php`**: Children/Youth student-count cards and a
  pending-lesson-plans card (both hidden automatically if migrations
  004/005 haven't been run yet - no broken queries on an un-migrated DB),
  plus a "next exam in N days" banner sourced from the calendar.
- **`backup_admin.php`**: a real, working database backup tool. Pure PHP
  (reads the schema/data over the existing mysqli connection) rather than
  shelling out to `mysqldump` - many shared hosts and locked-down XAMPP
  setups disable `shell_exec`, so this works everywhere the app itself
  runs. Writes proper UTF-8 (the audit's UTF-16 dump-corruption issue does
  not apply to these backups). Backups are stored in `backups/`, which is
  fully denied from direct web access (`Require all denied`) - the only way
  to download or delete one is through this admin-only, CSRF-protected
  page. Delete requires a POST + CSRF token, not a bare link.

## Deliberately not done
- **Teacher list pagination**: audit flagged this as low-priority at
  current scale (9 teachers) and the page is a more complex assignment-card
  layout than the student list; revisit once Children-division teachers
  push the count up.
- **Real Amharic OCR** and **browser Push notifications**: both need
  external infrastructure (a cloud OCR service or local Tesseract+Amharic
  data; an HTTPS domain + VAPID keys) that doesn't exist in this
  development environment. Building either without the real dependency
  would mean shipping something that looks done but silently doesn't work
  - exactly what the original brief said not to do. The database schema
  (`push_subscriptions` is the one piece not yet added) and the lesson
  plan photo-attachment flow are both ready to plug the real thing into
  once you have a domain/API key to test against.

---

# Phase 5 (final pass) — Attendance statuses, production error handling, audit log viewer

## Added (migration)
- `exam-main/migrations/006_attendance_statuses.sql` (+rollback): expands
  `attendance_records.status` from 3 to 5 values, adding `late` and
  `excused` per the brief. `NOT_MARKED` is deliberately *not* added as a
  stored value - the app already represents "not marked" as the absence of
  a row, which is the cleaner design and every page already treats it that
  way; adding a redundant enum value for it would just create a second way
  to express the same state.

## Added (pages/behavior)
- **Production-safe error handling** in `db.php`: with `APP_DEBUG` off
  (the default), uncaught exceptions and fatal errors now show one plain
  Amharic message instead of a raw PHP stack trace/file path, and the real
  error is still logged via `error_log()` for you to check. With
  `APP_DEBUG=1` you get full errors as before, for local debugging.
- **`audit_log_admin.php`**: a real viewer for the `audit_log` table added
  earlier - filter by user or action text, paginated (50/page). Previously
  the app was writing audit entries with no way to actually look at them.

## Changed
- **`dashboard_attendance.php`**, **`attendance_controller.php`**: both now
  support and display the two new statuses (⏰ Late, 📄 Excused) alongside
  the existing three, including in the admin grid's stats/percentages and
  legend. No changes needed to the JS marking logic - it already toggled
  "active" generically across however many status dots exist in a row.

## Requires
- Migration 006 must be applied (after 002-005) before the new statuses
  will save correctly - the UI will show the buttons regardless, but
  saving 'late'/'excused' will fail against the old 3-value enum until the
  migration runs.

## Verified
- Full `php -l` pass, and a static bind-parameter checker across every new
  file, comparing mysqli type-string length against `?` placeholder count.
