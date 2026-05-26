# Research: Extended User Profiles (F11)

No external research required — all decisions are straightforward given the existing codebase patterns.

## Decisions

### Grid Square Validation
**Decision**: Accept 4-character (e.g. `FN42`) and 6-character (e.g. `FN42aa`) Maidenhead locators. Reject 2-character field-only inputs. Normalize to uppercase on save.
**Rationale**: 4 and 6 character locators are the standard amateur radio precision levels. 2-character is too coarse for any practical use. PHP `preg_match` handles validation inline in the manager function.
**Alternatives considered**: A separate validation library — rejected as unnecessary for a single regex check.

### Storage: Columns vs. Separate Table
**Decision**: Add columns directly to the `users` table.
**Rationale**: All fields are 1:1 with a user. A separate `user_profiles` table adds a JOIN to every profile fetch with no benefit at this scale. Keeps `get_profile()` simple.
**Alternatives considered**: Separate `user_profiles` table — rejected per Principle I (unnecessary complexity).

### Bio Length
**Decision**: `TEXT` column in the DB (no DB-level length limit), enforced at 500 chars in PHP validation.
**Rationale**: `TEXT` avoids a future migration if the limit is raised. PHP validation gives the user a friendly error message.

### Phone Visibility
**Decision**: Phone is stored in the `users` table but never rendered in any public-facing template. Admin pages read it directly via the full user query.
**Rationale**: Simplest approach — no separate visibility flag needed since the rule is unconditional.

### show_name_publicly Default
**Decision**: `DEFAULT 0` (false) — existing users are opted out until they explicitly opt in.
**Rationale**: Privacy-safe migration default. No existing user loses privacy on upgrade.
