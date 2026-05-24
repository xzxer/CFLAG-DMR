<!--
SYNC IMPACT REPORT
==================
Version change: [unversioned template] → 1.0.0
Added sections: All (initial authoring from project principles)
Removed sections: N/A
Modified principles: N/A (first authoring)

Templates reviewed:
  ✅ .specify/templates/plan-template.md   — Constitution Check gate is generic; compatible with all principles
  ✅ .specify/templates/spec-template.md   — Acceptance criteria requirement aligns with Principle V
  ✅ .specify/templates/tasks-template.md  — Phase/task structure compatible; security and migration task types supported
  ✅ .specify/templates/checklist-template.md — Generic; no updates required

Follow-up TODOs: None. All placeholders resolved from user input and repo context.
-->

# CFLAG DMR Constitution

## Core Principles

### I. Simplicity and Maintainability

Code MUST be kept simple, readable, and maintainable as a primary constraint—not a nice-to-have:

- Plain PHP is the default. A third-party framework MUST NOT be introduced unless a specific,
  documented capability gap makes plain PHP demonstrably inadequate for the task.
- Features MUST be implemented in small, independently testable increments. Large rewrites that
  replace working functionality in a single change are prohibited.
- New abstractions and helpers MUST each have a single, stated purpose. Multi-purpose utility files
  that accumulate unrelated logic MUST be split before they are merged.
- Dependencies (Composer packages, JS libraries, etc.) MUST be justified by a concrete, present
  need. Speculative dependencies are prohibited.

**Rationale**: A small team maintaining a focused dashboard sustains velocity through readable,
obvious code rather than framework indirection. Simple code is also easier to audit for security.

### II. Security First

Security-sensitive areas MUST receive explicit, documented protection in every feature that touches
them. These areas include: administrator login, password storage and verification, access request
and approval workflows, device and hotspot credentials, and future talkgroup or network routing
controls.

- Secrets (database credentials, API tokens, application keys) MUST live in `.env` and MUST NOT
  appear in any committed file. `.env.example` defines the public contract of required variables.
- Any committed secret, however briefly, MUST be rotated immediately and the offending commit MUST
  be removed from history before the branch is merged.
- All user-supplied output rendered to HTML MUST be escaped (e.g., `htmlspecialchars` with
  `ENT_QUOTES`). Unescaped output is prohibited.
- SQL queries MUST use PDO prepared statements. String interpolation into query strings is
  prohibited.
- Authentication checks for protected pages MUST be enforced server-side before any privileged
  logic executes. Client-supplied role or permission claims MUST NOT be trusted without server
  verification.

**Rationale**: CFLAG DMR controls access to a live radio network. A breach of the admin interface
or device registry has real-world consequences for network availability and operator safety.

### III. Public Directory Isolation

Apache MUST serve only the `public/` directory as its document root:

- `public/` MUST contain only the front controller (`index.php`), compiled/static assets, and
  publicly-accessible files. Application logic, configuration files, migration scripts, and
  credentials MUST NOT be placed inside `public/`.
- `.htaccess` or Apache virtual host configuration MUST route all unmatched requests through
  `public/index.php`.
- Any file outside `public/` MUST be inaccessible directly via HTTP. This boundary MUST be
  verified whenever the Apache or directory configuration is modified.

**Rationale**: Exposing application code or configuration files via the web server is one of the
most common PHP deployment vulnerabilities. This boundary must be treated as non-negotiable.

### IV. Database Integrity via Migrations

All changes to the database schema MUST be expressed as versioned SQL migration files:

- New tables, columns, indexes, constraints, and data-type changes MUST be committed as scripts in
  `migrations/` before they are applied to any environment.
- Migration scripts MUST be sequentially numbered and applied in order. Ad-hoc `ALTER TABLE` or
  `CREATE TABLE` commands run directly against the database without a corresponding migration file
  are prohibited.
- Destructive operations (DROP, TRUNCATE) MUST include a comment in the migration file explaining
  the rationale and confirming that a backup exists.
- The development and production databases MUST use different names (e.g., `cflag_dmr_dev` vs.
  `cflag_dmr`) to prevent accidental cross-environment operations.

**Rationale**: Network membership records and device credentials are operationally critical. Schema
changes applied outside of migration scripts cannot be audited, rolled back, or reproduced on a
fresh server.

### V. Feature Quality and Acceptance Criteria

Every feature MUST define clear, measurable acceptance criteria before implementation begins:

- A feature specification MUST include at least one acceptance scenario in Given/When/Then form
  that can be verified manually or automatically.
- Features MUST be implemented in small slices where each slice delivers independently verifiable
  value. A slice that cannot be tested in isolation is too large.
- "Done" means the acceptance criteria pass in the development environment and the feature has
  been reviewed by at least one other contributor.
- Acceptance criteria discovered to be incomplete during implementation MUST be updated in the
  spec before the feature is marked complete—not after.

**Rationale**: Clear acceptance criteria prevent scope creep, give reviewers a concrete target,
and ensure that small-feature discipline is enforceable rather than aspirational.

### VI. Branch and Deployment Strategy

The `dev` branch is the active development surface; `main` is the stable, production-ready surface:

- All feature work branches from `dev` and merges back to `dev` via pull request.
- The development server MUST track the `dev` branch. Testing of new features happens on the dev
  server before any merge to `main` is proposed.
- `main` MUST only contain code that has been reviewed, tested on the dev server, and approved
  for production. Self-merges to `main` are prohibited except in documented emergencies.
- A merge from `dev` → `main` constitutes a deployment event and MUST be treated with the same
  care as a production release.

**Rationale**: Keeping development work on `dev` protects the stability of `main` and provides a
shared integration point for the team without the risk of untested code reaching production.

## Technology Stack

- **Language**: PHP 8.x — strict types enabled via `declare(strict_types=1)` in all source files
- **Database**: MariaDB — accessed exclusively via PDO with prepared statements
- **Web Server**: Apache — `public/` is the document root; all requests route through
  `public/index.php`
- **Frontend**: Semantic HTML5, plain CSS (`public/assets/css/`); JavaScript added only when
  server-side rendering is insufficient for the user interaction
- **Configuration**: `.env` at project root (not committed); `.env.example` is the committed
  variable contract
- **Version Control**: Git / GitHub — branching model defined in Principle VI

## Development Workflow

- **Feature cycle**: Branch from `dev` → implement in small slices → test on dev server → PR
  review → merge to `dev` → eventually promote to `main`
- **Code review**: All pull requests require at least one reviewer approval before merge
- **Environment parity**: Dev and production MUST use the same PHP version and Apache configuration;
  any divergence MUST be documented in `docs/`
- **Secrets rotation**: Any secret accidentally committed MUST be rotated and removed from history
  before the branch merges (see Principle II)
- **Migration discipline**: Every database schema change ships with a migration file (see
  Principle IV); no schema changes are applied to the dev or production database without a
  corresponding committed migration

## Governance

This constitution supersedes all informal practices, verbal conventions, and legacy habits. It is
the first reference when a design or implementation decision is disputed.

**Amendment procedure**:

1. Open a pull request against `dev` that modifies this file.
2. Increment the version following semantic versioning:
   - **MAJOR**: Removal of an existing principle or backward-incompatible redefinition.
   - **MINOR**: Addition of a new principle or materially expanded guidance.
   - **PATCH**: Clarifications, wording corrections, or non-semantic refinements.
3. The PR description MUST document the rationale and any migration impact for existing features.
4. At least one contributor other than the author MUST approve before merge.
5. Update **Last Amended** to the merge date.

All feature plans MUST include a Constitution Check confirming compliance with the principles
above. Violations MUST be justified in the plan's Complexity Tracking table before implementation
begins. All pull requests MUST reference the Constitution Check outcome in their description.

**Version**: 1.0.0 | **Ratified**: 2026-05-24 | **Last Amended**: 2026-05-24
