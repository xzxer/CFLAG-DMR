# Quickstart: F11 Extended Profiles + F12 User Directory

## Setup

1. Apply migration: `mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/015_extend_user_profiles_directory.sql`
2. Log in as a test user at `/login.php`

---

## F11 Scenario 1.1 — Fill in extended profile fields

1. Log in as `testuser`
2. Navigate to `/user/profile.php`
3. Scroll to the "Extended Profile" section
4. Enter: First Name = `Jane`, Last Name = `Smith`, Grid Square = `FN42aa`, Bio = `Testing the CFLAG DMR network.`, Phone = `555-0100`
5. Check "Show my name publicly"
6. Click **Save Extended Profile**

**Expected**: Flash message "Extended profile saved." Fields persist on page reload.

---

## F11 Scenario 1.2 — Invalid grid square rejected

1. Log in, navigate to `/user/profile.php`
2. Enter Grid Square = `ZZ` (too short / invalid)
3. Click **Save Extended Profile**

**Expected**: Error "Invalid grid square format." No data saved.

---

## F11 Scenario 2.1 — Profile visibility between users

1. Log in as `testuser`, set grid square + bio, enable name opt-in. Save.
2. Open a second browser / incognito session, log in as `testuser2`
3. Navigate to `/user/view.php?id=<testuser's ID>`

**Expected**: Grid square and bio visible. First + last name visible (opt-in enabled). Phone NOT visible.

---

## F11 Scenario 2.2 — Name opt-out hides name

1. Log in as `testuser`, disable "Show my name publicly". Save.
2. As `testuser2`, refresh `/user/view.php?id=<testuser's ID>`

**Expected**: Display name shown, first/last name NOT shown.

---

## F11 Scenario 3.1 — Admin sees phone number

1. Log in as `system_admin`
2. Navigate to `/admin/users/view.php?id=<testuser's ID>`

**Expected**: Phone number `555-0100` visible in extended profile section.

---

## F12 Scenario 4.1 — Browse the directory

1. Log in as any user
2. Navigate to `/users`

**Expected**: Paginated table showing active opted-in users. Columns: Callsign, Display Name, Grid Square, Devices.

---

## F12 Scenario 4.2 — Search by callsign partial match

1. Navigate to `/users`
2. Type `W1` in the search box, click Search

**Expected**: Only users whose callsign starts with or contains `W1` appear.

---

## F12 Scenario 4.3 — Opt out of directory

1. Log in as `testuser`
2. Navigate to `/user/profile.php`
3. Uncheck "Show me in the user directory". Save.
4. Log in as `testuser2`, navigate to `/users`

**Expected**: `testuser` does not appear in directory or search results.

---

## F12 Scenario 4.4 — Directory link goes to profile view

1. In the directory, click `testuser`'s callsign

**Expected**: Navigates to `/user/view.php?id=X` showing `testuser`'s public profile.
