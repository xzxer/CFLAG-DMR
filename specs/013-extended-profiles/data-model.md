# Data Model: Extended User Profiles & Directory (F11 + F12)

Shared migration 015 covers all schema changes for both features.

## Migration 015: `015_extend_user_profiles_directory.sql`

Adds seven columns to the existing `users` table:

| Column | Type | Nullable | Default | Validation |
|--------|------|----------|---------|------------|
| `first_name` | `VARCHAR(64)` | YES | NULL | Max 64 chars |
| `last_name` | `VARCHAR(64)` | YES | NULL | Max 64 chars |
| `grid_square` | `VARCHAR(8)` | YES | NULL | 4 or 6 char Maidenhead; stored uppercase |
| `bio` | `TEXT` | YES | NULL | Max 500 chars enforced in PHP |
| `phone` | `VARCHAR(32)` | YES | NULL | Max 32 chars; no format validation |
| `show_name_publicly` | `TINYINT(1)` | NO | 0 | Boolean opt-in for name visibility |
| `show_in_directory` | `TINYINT(1)` | NO | 1 | Boolean opt-in for directory listing |

## Entity: User (extended)

Existing entity. New columns only — no existing columns change.

```
users
├── id                    (PK)
├── username              (existing)
├── callsign              (existing)
├── display_name          (existing)
├── email                 (existing)
├── dmr_id                (existing)
├── moderation_state      (existing)
├── ... (other existing columns)
│
├── first_name            ← NEW (F11)
├── last_name             ← NEW (F11)
├── grid_square           ← NEW (F11)
├── bio                   ← NEW (F11)
├── phone                 ← NEW (F11, admin-only visibility)
├── show_name_publicly    ← NEW (F11, default false)
└── show_in_directory     ← NEW (F12, default true)
```

## Visibility Rules

| Field | Own profile | Other logged-in user | Admin | Public (unauthenticated) |
|-------|-------------|----------------------|-------|--------------------------|
| callsign | ✅ | ✅ | ✅ | ❌ (auth required) |
| display_name | ✅ | ✅ | ✅ | ❌ |
| grid_square | ✅ | ✅ if set | ✅ | ❌ |
| bio | ✅ | ✅ if set | ✅ | ❌ |
| first_name / last_name | ✅ | ✅ only if show_name_publicly=1 | ✅ | ❌ |
| phone | ✅ | ❌ never | ✅ | ❌ |
| show_name_publicly | ✅ (editable) | ❌ | ✅ | ❌ |
| show_in_directory | ✅ (editable) | ❌ | ✅ | ❌ |

## Directory Query

The user directory selects from `users` with conditions:
- `moderation_state = 'active'`
- `show_in_directory = 1` (or admin bypasses this)
- `email_verified_at IS NOT NULL`

Device count is a subquery or LEFT JOIN COUNT on `devices WHERE status='approved' AND user_id=users.id`.

Sort order: `callsign ASC NULLS LAST, username ASC` (users with no callsign sort after those with one).
