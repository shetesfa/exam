# አጸደ ትጉሃን — Ethiopian Orthodox Sunday School Management System

A full-stack PHP/MySQL web application for managing an Ethiopian Orthodox Sunday School (Atseyde Tiguhanat), supporting two divisions — **Children** (Grades 1–6) and **Youth** (Grades 7–12).

---

## Features

### Administration
- Multi-role authentication: Admin, Teacher, Attendance Submitter, Student
- Full CRUD for classes, teachers, students, and user accounts
- Class lock / unlock (prevent marks/attendance edits per teacher or class)
- Semester management (open, close) with Ethiopian calendar year tracking
- Promotion engine: Grade-level progression with transaction-safe batch promotion and `promotion_history` audit trail
- Database backup (pure-PHP, UTF-8, no shell_exec required)
- Audit log for all critical actions
- System settings (admin names, phone numbers, display title)

### Children Division (Grades 1–6)
- Structured lesson plan editor with photo upload (OCR extraction of Amharic fields)
- Admin lesson plan review and approval workflow
- Lesson plan version history
- Teacher attendance tracking
- Per-class marking scheme (Assignment 20%, Participation 20%, Attendance 10%, Mid 20%, Final 30%)
- Marks entry and viewing
- Print/export results per semester

### Youth Division (Grades 7–12)
- Attendance by Attendance Submitter (no lesson plan requirement)
- Multi-teacher shared attendance model: one canonical attendance record per student/class/date
- Student marks viewing

### Student Portal
- PIN-based student login
- View own marks and attendance records
- PIN change on first login
- Student portal enable/disable toggle per student

### PWA / Offline / Mobile
- Progressive Web App manifest + service worker (exam-pwa-v5)
- Offline-capable via IndexedDB (offline-db.js)
- Background sync when connectivity restored (sync-manager.js)
- Bearer token auth for all API endpoints (30-day offline tokens)
- Web Push Notifications (RFC 8291/8292 compliant, pure-PHP VAPID)
- Responsive mobile navigation with unread notification badge
- Ethiopian calendar view

---

## Technical Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.2 |
| Database | MariaDB 10.4 (via XAMPP) |
| Frontend | Vanilla JS, CSS3 (no framework) |
| PWA | Service Worker, IndexedDB, Web Push |
| OCR | Pluggable: Tesseract / Google Cloud Vision / OCR.Space / Fallback |
| Crypto | Pure PHP (RFC 8291 AES-128-GCM + RFC 8292 VAPID) |

---

## Database

- **28 tables** — see [`database.sql`](database.sql) for the full schema
- Key tables: `users`, `students`, `classes`, `grades`, `divisions`, `semesters`, `marks`, `attendance_records`, `teacher_class`, `lesson_plans`, `calendar_events`, `push_subscriptions`, `auth_tokens`, `audit_log`
- All writes use prepared statements via `dbExecute()` / `dbFetchOne()` / `dbFetchAll()` helpers in `db.php`
- Soft-delete pattern on `students`, `marks`, `attendance_records` (`is_deleted = 1`)

---

## Installation (XAMPP / Windows)

```bash
# 1. Clone or copy project to your XAMPP htdocs directory
# Project root: C:\xampp\htdocs\exam\

# 2. Create the database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS atsede_sunday_school CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root atsede_sunday_school < database.sql

# 3. Configure environment
copy .env.example .env
# Edit .env with your DB credentials and paths

# 4. Ensure backups directory is writable
mkdir backups   (already exists)

# 5. Visit http://localhost/exam/
# Default admin login: admin / 123 (change immediately)
```

### Requirements
- PHP 8.0+ with extensions: `mysqli`, `gd`, `openssl`, `mbstring`, `curl`, `fileinfo`
- MariaDB 10.4+ / MySQL 8+
- OpenSSL — set `OPENSSL_CONF=C:/xampp/apache/conf/openssl.cnf` in your environment for VAPID key generation on Windows

---

## Security

- CSRF tokens on all state-changing forms (`csrfField()` / `verifyCsrfToken()`)
- All database queries use prepared statements
- IDOR protection: teachers can only access their own assigned classes and students
- Role-based access control: `requireAdmin()`, `requireLogin()`, `isTeacher()`, `isAttendanceSubmitter()`
- Soft-delete for students/marks/attendance preserves audit trail
- Class lock enforcement prevents marks/attendance edits on locked classes
- Backup download protected by filename pattern validation (no path traversal)
- XSS protection: all `$message` / `$error` outputs wrapped in `htmlspecialchars()`
- Input whitelist validation: event_type, priority, status enums validated server-side
- Push subscriptions validated before sending notifications

---

## Project Structure

```
exam/
├── db.php                      # Core: DB connection, helpers, business logic functions
├── index.php                   # Admin/Teacher/Attendance login
├── student_login.php           # Student PIN login
├── dashboard_admin.php         # Admin dashboard
├── dashboard_teacher.php       # Teacher dashboard + marks entry
├── dashboard_attendance.php    # Attendance submitter dashboard
├── dashboard_student.php       # Student portal
├── manage_*.php                # CRUD pages (classes, students, teachers, users, assignments)
├── teacher_*.php               # Teacher-specific pages (marks viewer, scheme, attendance view, profile)
├── lesson_plan_*.php           # Lesson plan editor and admin review
├── calendar_*.php              # Ethiopian calendar event management
├── semester.php                # Semester open/close
├── promotion.php               # Student promotion engine
├── class_locks.php             # Lock/unlock marks per class/teacher
├── print_results.php           # Results print/export
├── backup_admin.php            # Database backup (pure PHP)
├── admin_settings.php          # System settings
├── notifications.php           # Push notification management
├── exam_reminder_run.php       # Cron: exam reminder notifications
├── api/                        # REST endpoints for offline PWA
│   ├── api_common.php          # Shared auth (Bearer token + session)
│   ├── auth.php                # Token issuance
│   ├── ping.php                # Connectivity check
│   ├── sync_pull.php           # Delta pull (scoped by role/class)
│   ├── sync_push.php           # IDOR-hardened sync push
│   └── push_subscribe.php      # Web Push subscription registration
├── assets/
│   ├── js/
│   │   ├── offline-db.js       # IndexedDB abstraction layer
│   │   ├── sync-manager.js     # Background sync controller
│   │   └── push-notifications.js # Web Push client
│   └── css/
├── services/
│   ├── ocr/                    # OCR pipeline (pluggable providers)
│   └── push/                   # Web Push crypto (VAPID, AES-128-GCM)
├── sw.js                       # Service Worker (exam-pwa-v5)
├── manifest.json               # PWA manifest
├── pwa_head.php                # PWA head include + offline session bridge
├── mobile_nav.php              # Responsive navigation with notification bell
├── database.sql                # Full database schema + seed data
├── .env.example                # Environment configuration template
└── backups/                    # Database backup files (gitignored)
```

---

## Ethiopian Calendar

The system natively uses the Ethiopian calendar (Ge'ez) for:
- Semester dates and year tracking
- Attendance calendar display (Saturdays and Sundays)
- Calendar events and reminders
- Academic year progression

Conversion functions `ethiopianToGregorian()` and `gregorianToEthiopian()` are in `db.php`.

---

## Divisions & Grades

| Division | Grades | Lesson Plans | Attendance Submitter |
|----------|--------|-------------|---------------------|
| Children (ሕጻናት) | 1–6   | Required    | Not used |
| Youth (ወጣቶች) | 7–12  | Not required | Allowed |

A class can have **multiple teachers**. All teachers of a class share the same attendance dataset — one record per student/class/date.

---

## Tests

```bash
# Integration test suite (44 tests)
php scratch/test_integration_all.php

# PHP lint all files
php scratch/lint_all.php
```

---

## Changelog

See [`CHANGELOG.md`](CHANGELOG.md) for version history.
