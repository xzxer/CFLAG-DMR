\# CFLAG DMR



CFLAG DMR is a web-based dashboard and management platform for a DMR network.



\## Goals



\- Provide a clean dashboard for CFLAG DMR network activity.

\- Support administrator login and protected management pages.

\- Support user access requests and approval workflows.

\- Support device and hotspot management.

\- Support future talkgroup and routing visibility.

\- Keep secrets out of source control.

\- Use GitHub as the source of truth.



\## Development Workflow



\- `main` is stable code.

\- `dev` is active development code.

\- The development server tracks the `dev` branch.

\- Production will eventually track the `main` branch.



\## Project Structure



```text

public/      Apache web root

app/         Application code

migrations/  Database migration scripts

scripts/     Maintenance/deployment scripts

docs/        Documentation

