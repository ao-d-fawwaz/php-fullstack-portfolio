# PHP Full-Stack Portfolio — Adib Dzakwan Fawwaz

Four web applications built with plain PHP 8, MySQL and vanilla JavaScript — no framework, so the architecture, security and SQL are my own. Each started as a project I built for work or my karate dojo; for this portfolio I rewrote them, fixed their security problems and added automated tests.

| Project | What it shows |
|---|---|
| [Kanban Task Board](01-kanban-task-board) | Drag & drop UI, session auth, secure file uploads, chat per task, audit history |
| [Travel Booking REST API](02-travel-booking-api) | Layered REST API, JWT + roles, rate limiting, transactions, validation, CORS |
| [Tour Quotation Generator](03-tour-quotation-generator) | Business calculations (multi-currency pricing, margins), server-side source of truth, printable output |
| [Karate Tournament Bracket](04-karate-tournament-bracket) | Algorithm design (bracket seeding, byes, club separation), public + admin views |

**Common practices across all four**
- PDO prepared statements everywhere; schema files for MySQL (`database/schema.mysql.sql`)
- Passwords hashed with `password_hash`; CSRF tokens or JWT on every state-changing request
- Output escaping against XSS; generic error messages to clients, details only in logs
- End-to-end tests (`tests/run_tests.sh`) that run PHP's built-in server against a fresh SQLite database — **123 checks in total**, plus unit tests for the calculation and bracket logic

## Running the tests
Requires PHP 8.1+ with `pdo_sqlite` and `curl`:
```bash
for p in 0*/; do (cd "$p" && bash tests/run_tests.sh); done
```

All sample data is fictional.
