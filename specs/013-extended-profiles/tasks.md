# Tasks: F11 Extended User Profiles + F12 Public User Directory

**Branch**: `014-user-directory` (both features implemented together)
**Specs**: [F11 spec.md](spec.md) | [F12 spec.md](../014-user-directory/spec.md)
**Plans**: [F11 plan.md](plan.md) | [F12 plan.md](../014-user-directory/plan.md)
**Data model**: [data-model.md](data-model.md)

---

## Phase 1: Setup

**Purpose**: Write and apply the shared database migration.

- [x] T001 Write `migrations/015_extend_user_profiles_directory.sql` — ALTER TABLE users ADD COLUMN first_name VARCHAR(64) NULL, last_name VARCHAR(64) NULL, grid_square VARCHAR(8) NULL, bio TEXT NULL, phone VARCHAR(32) NULL, show_name_publicly TINYINT(1) NOT NULL DEFAULT 0, show_in_directory TINYINT(1) NOT NULL DEFAULT 1
- [x] T002 Apply migration to dev DB: `mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/015_extend_user_profiles_directory.sql`

**Checkpoint**: `DESCRIBE users` shows all 7 new columns. No user story work starts until T002 is confirmed.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Extend the profile reader so all user stories have access to the new columns.

**⚠️ CRITICAL**: All user stories depend on get_profile() returning the extended fields.

- [x] T003 Extend `get_profile()` in `app/profile/manager.php` — update the SELECT to include first_name, last_name, grid_square, bio, phone, show_name_publicly, show_in_directory

**Checkpoint**: `get_profile($user_id)` returns all new columns (including NULL for unset fields).

---

## Phase 3: F11-US1 — Edit Extended Profile Fields (Priority: P1) 🎯 MVP

**Goal**: Users can fill in optional extended profile fields and save them from their profile page.

**Independent Test**: Log in, navigate to `/user/profile.php`, fill in grid square + bio, save, reload — confirm values persisted and displayed.

- [x] T004 [US1] Add `update_extended_profile(int $user_id, array $data): array` to `app/profile/manager.php` — trim all fields; validate grid_square with `/^[A-Ra-r]{2}[0-9]{2}([A-Xa-x]{2})?$/` (4 or 6 char Maidenhead), store uppercase; validate bio max 500 chars; validate first_name/last_name max 64 chars; validate phone max 32 chars; UPDATE users SET first_name=?, last_name=?, grid_square=?, bio=?, phone=?, show_name_publicly=?, show_in_directory=? WHERE id=?; log_audit_action; return ['ok'=>true] or ['ok'=>false,'error'=>'...']
- [x] T005 [US1] Add POST handler `action='update_extended'` to `public/user/profile.php` — call update_extended_profile($user_id, $_POST); PRG redirect with session flash
- [x] T006 [US1] Add "Extended Profile" form section to `public/user/profile.php` — inside a new `.panel` after the display name panel; fields: first_name (text, max 64), last_name (text, max 64), grid_square (text, max 8, placeholder "e.g. FN42aa"), bio (textarea, max 500), phone (text, max 32, hint "Visible to admins only"); checkbox show_name_publicly with label "Show my name to other logged-in users"; checkbox show_in_directory with label "Show me in the user directory"; values pre-filled from $user (get_profile result); submit button action=update_extended; CSRF token; htmlspecialchars on all values

**Checkpoint**: Extended profile section saves and reloads correctly. Grid square validation rejects "ZZ". Bio over 500 chars is rejected.

---

## Phase 4: F11-US2 — Profile View Page with Visibility Rules (Priority: P2)

**Goal**: Any logged-in user can view another user's profile at `/user/view.php?id=X` with visibility rules applied.

**Independent Test**: As testuser, set grid square + bio + enable name opt-in. As testuser2, visit `/user/view.php?id=<testuser_id>` — see grid/bio/name. Phone NOT visible.

- [x] T007 [US2] Create `public/user/view.php` — require_login(); $target_id = (int)($_GET['id'] ?? 0); redirect to /users if target not found or not active; load user via get_profile($target_id); apply visibility: always show callsign/display_name/grid_square/bio if set; show first_name/last_name only if $user['show_name_publicly']; never show phone; $page_title = callsign or display_name; $active_nav = 'users'; require header/footer; layout: `.layout-single` with single `.panel`; field-list showing: callsign (col-mono accent), display_name, grid_square (if set), bio (if set), name (if show_name_publicly and set), device count (query approved devices for this user); link back to "/users" directory

**Checkpoint**: Visiting `/user/view.php?id=X` as another user shows correct fields. Phone never appears. Unauthenticated visitor is redirected to login.

---

## Phase 5: F11-US3 — Admin View Extended Fields (Priority: P3)

**Goal**: Admins see all extended profile fields including phone on the admin user detail page.

**Independent Test**: As system_admin, view `/admin/users/view.php?id=X` for a user who has phone set — phone number is visible.

- [x] T008 [US3] Extend admin user view in `public/admin/users/view.php` — update the user query (or re-use get_profile which now returns all columns); add an "Extended Profile" field-list section to the admin info panel showing: first_name, last_name, grid_square, bio, phone (all displayed regardless of opt-in flags); show "—" for unset fields; phone labeled "Phone (admin-only)"; no edit controls (admin view is read-only for these fields)

**Checkpoint**: Admin user view shows phone for a user who has it set. Non-admin profile view does not.

---

## Phase 6: F12-US1 — Browse the User Directory (Priority: P1)

**Goal**: A paginated list of active opted-in users at `/users` with links to their profiles.

**Independent Test**: Navigate to `/users` as any logged-in user — see a table of users, click one — arrive at their profile view page.

- [x] T009 [P] [F12-US1] Create `app/users/directory.php` — functions: `get_directory_users(int $page, int $per_page, bool $admin = false): array` (SELECT u.id, u.callsign, u.username, u.display_name, u.grid_square, u.show_name_publicly, u.first_name, u.last_name, (SELECT COUNT(*) FROM devices d WHERE d.user_id=u.id AND d.status='approved') AS device_count FROM users u WHERE u.moderation_state='active' AND u.email_verified_at IS NOT NULL AND ($admin OR u.show_in_directory=1) ORDER BY COALESCE(u.callsign,'') = '' ASC, u.callsign ASC, u.username ASC LIMIT ? OFFSET ?); `count_directory_users(bool $admin = false): int` (same WHERE, returns COUNT(*))
- [ ] T010 [F12-US1] Implement `public/users.php` — require_login(); $is_admin = user_has_role('system_admin') || user_has_role('admin'); $q = trim($_GET['q'] ?? ''); $page = max(1, (int)($_GET['page'] ?? 1)); $per_page = 50; require_once app/users/directory.php; if $q !== '' use search function (T011), else get_directory_users; total count for pagination; $active_nav = 'users'; $page_title = 'User Directory'; layout: `.layout-table-page`; filter bar with search input + Search button + Clear link; panel with data-table (cols: Callsign, Display Name, Grid Square, Devices); callsign/display_name links to `/user/view.php?id=X`; empty state "No users match your search" or "No users in directory"; pagination in panel-footer using .pagination class with prev/next page links

**Checkpoint**: `/users` shows paginated list. Clicking a user goes to their profile view. Opted-out users absent (when logged in as non-admin).

---

## Phase 7: F12-US2 — Directory Search (Priority: P2)

**Goal**: Search the directory by callsign, username, or display name.

**Independent Test**: Type first 3 chars of a known callsign into search — only matching users appear.

- [ ] T011 [US2] Add `search_directory_users(string $q, int $page, int $per_page, bool $admin = false): array` and `count_search_results(string $q, bool $admin = false): int` to `app/users/directory.php` — same base query as get_directory_users but with AND (u.callsign LIKE ? OR u.username LIKE ? OR u.display_name LIKE ?) where param is "%{$q}%"; wire into public/users.php search path (T010 already calls this when $q !== '')

**Checkpoint**: Searching "W1" returns only users whose callsign/username/display_name contains "W1". Opted-out users excluded from search results.

---

## Phase 8: F12-US3 — Directory Opt-Out (Priority: P3)

**Goal**: Users can opt out of the directory from their profile page. (The checkbox is already added in T006 — this phase ensures the opt-out is wired correctly end-to-end and the sidebar nav shows the directory link.)

**Independent Test**: Uncheck "Show me in the user directory" on profile page, save. Browse directory as another user — opted-out user is absent. Re-enable — they reappear.

- [ ] T012 [US3] Verify `update_extended_profile()` in `app/profile/manager.php` persists show_in_directory=0 correctly (already included in T004 — confirm the WHERE u.show_in_directory=1 filter in directory queries excludes the user after save)
- [ ] T013 [US3] Verify sidebar nav "User Directory" link in `app/views/header.php` is active (active_nav='users') and accessible to all logged-in users — confirm the link exists and points to /users

**Checkpoint**: Opt-out end-to-end works. Sidebar shows "User Directory" for all logged-in users.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [ ] T014 Update `specs/000-project-overview/spec.md` — mark F11 and F12 as ✅ Complete
- [ ] T015 Update `CLAUDE.md` — move F11+F12 from active to completed, clear active development section
- [ ] T016 Run quickstart.md scenarios 1.1–4.4 on dev server and confirm expected outcomes

---

## Dependencies & Execution Order

- **T001 → T002**: Migration must be written before applying
- **T002 → T003**: Migration must be applied before extending get_profile
- **T003 → T004**: get_profile must return new columns before writing update function
- **T004 → T005 → T006**: Manager function before handler before form
- **T003 → T007**: get_profile extended before building profile view page
- **T003 → T008**: get_profile extended before admin view update
- **T009 and T010**: Can be written in parallel (different files); T010 calls T009's functions so T009 must be importable before T010 is tested
- **T010 → T011**: directory.php must exist before adding search function
- **T006 (show_in_directory checkbox) → T012**: Checkbox must exist before verifying opt-out
- **T006, T007 must be complete before T009/T010** (view.php links from directory)

### Parallel Opportunities

- T007 (view.php) and T008 (admin view) can be written in parallel — different files
- T009 (directory.php) can be started as soon as T003 is done, in parallel with T007/T008
- T014, T015, T016 can all run in parallel (polish phase)

## Implementation Strategy

### MVP (US1 only — F11 extended profile editing)

1. T001 → T002 → T003 → T004 → T005 → T006
2. **Stop and validate**: Profile page saves/loads extended fields correctly

### Full delivery order

Phase 1 → Phase 2 → Phase 3 (F11-US1) → Phase 4 (F11-US2) → Phase 5 (F11-US3) → Phase 6 (F12-US1) → Phase 7 (F12-US2) → Phase 8 (F12-US3) → Phase 9

---

## Notes

- All PHP files: `declare(strict_types=1)` at top
- All rendered output: `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')`
- All DB writes: PDO prepared statements via `get_db()`
- All POST forms: CSRF token via `csrf_token()` / `verify_csrf()`
- PRG pattern: POST → redirect with `$_SESSION['_flash_ok']` / `$_SESSION['_flash_error']`
- New files in `app/` require `declare(strict_types=1)` and `require_once` for DB connection
