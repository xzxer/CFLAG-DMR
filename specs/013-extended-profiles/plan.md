# Implementation Plan: Extended User Profiles (F11)

**Branch**: `013-extended-profiles` | **Date**: 2026-05-26 | **Spec**: [spec.md](spec.md)

**Note**: Implemented together with F12 (User Directory, `specs/014-user-directory/`). Migration 015 covers both features.

## Summary

Add optional amateur radio operator fields (first name, last name, grid square, bio, phone) to the existing `users` table via migration 015. Extend the profile manager with a new `update_extended_profile()` function. Add an "Extended Profile" section to the existing profile edit page. Apply visibility rules when rendering other users' profiles. Admins see all fields including phone on the admin user view page.

## Technical Context

**Language/Version**: PHP 8.3, `declare(strict_types=1)` in all files

**Primary Dependencies**: MariaDB via PDO (`get_db()` singleton from `app/database/connection.php`); no new packages

**Storage**: Columns added to existing `users` table via `migrations/015_extend_user_profiles_directory.sql`

**Target Platform**: Linux server, Apache 2.4, PHP-FPM

**Project Type**: Web application (server-rendered PHP)

**Constraints**: All output via `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')`; all DB writes via PDO prepared statements; CSRF on all POST forms; `require_login()` before any user data access

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Simplicity | ✅ PASS | Plain PHP, no new framework; new function added to existing manager |
| II. Security First | ✅ PASS | All output escaped; PDO prepared statements; CSRF on new form action |
| III. Public Dir Isolation | ✅ PASS | No new files in wrong location |
| IV. DB Migrations | ✅ PASS | All schema changes in `migrations/015_…sql` |
| V. Feature Quality | ✅ PASS | Spec has Given/When/Then scenarios for all stories |

## Project Structure

### Documentation (this feature)

```text
specs/013-extended-profiles/
├── plan.md              ← this file
├── research.md
├── data-model.md
└── quickstart.md
```

### Source Code

```text
migrations/
└── 015_extend_user_profiles_directory.sql   ← new columns on users table

app/profile/
└── manager.php           ← add update_extended_profile(), get_profile() extended

public/user/
├── profile.php           ← add action='update_extended' handler + form section
└── view.php              ← NEW: read-only profile view (used by F12 directory links)

public/admin/users/
└── view.php              ← extend to show phone + extended fields in admin panel
```
