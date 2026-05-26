# Implementation Plan: Public User Directory (F12)

**Branch**: `014-user-directory` | **Date**: 2026-05-26 | **Spec**: [spec.md](spec.md)

**Note**: Implemented together with F11 (Extended Profiles, `specs/013-extended-profiles/`). Migration 015 covers both features. `public/user/view.php` is created by F11 and consumed by F12.

## Summary

Replace the `/users.php` placeholder with a fully functional paginated, searchable directory of active opted-in users. Add `show_in_directory` boolean to `users` via migration 015. Create `app/users/directory.php` with query functions. Wire up the existing `public/users.php` and create `public/user/view.php` for per-user profile viewing.

## Technical Context

**Language/Version**: PHP 8.3, `declare(strict_types=1)` in all files

**Primary Dependencies**: MariaDB via PDO; no new packages

**Storage**: `show_in_directory` column on `users` (migration 015, shared with F11); approved device count derived via JOIN at query time

**Target Platform**: Linux server, Apache 2.4, PHP-FPM

**Project Type**: Web application (server-rendered PHP)

**Constraints**: All output via `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')`; PDO prepared statements; `require_login()` enforced; pagination at 50 per page

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Simplicity | ✅ PASS | One new app module (`app/users/directory.php`), two page files |
| II. Security First | ✅ PASS | Auth required, all output escaped, PDO prepared statements |
| III. Public Dir Isolation | ✅ PASS | New app logic in `app/users/`, not in `public/` |
| IV. DB Migrations | ✅ PASS | `show_in_directory` column in migration 015 |
| V. Feature Quality | ✅ PASS | Spec has Given/When/Then for all three user stories |

## Project Structure

### Documentation (this feature)

```text
specs/014-user-directory/
├── plan.md              ← this file
├── data-model.md
└── quickstart.md
```

### Source Code

```text
migrations/
└── 015_extend_user_profiles_directory.sql   ← shared with F11

app/users/                                   ← NEW module
└── directory.php         ← get_directory_users(), search_directory_users(), get_user_profile_public()

public/
└── users.php             ← implement directory (replaces placeholder)

public/user/
└── view.php              ← NEW: read-only profile view for /user/view.php?id=X
                              (created by F11 plan, consumed here for directory links)

public/user/
└── profile.php           ← add action='update_directory_opt' handler + opt-out checkbox
```
