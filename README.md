# Locker

A small shared-secret shelf for one student organization. Members store door codes, social logins, and the odds and ends that used to live in a group chat. Each account only sees the items it stored.

Plain PHP, SQLite, no framework.

## Features

- Register with a `.edu` email, sign in, sign out
- Forgot-password / same-day reset token
- Personal vault: label, secret, notes
- Advisor account exists in the seed data for org-wide holds

## Run locally

PHP 8.2+ with the PDO SQLite driver (`php-sqlite3` on Ubuntu/Debian).

```bash
php -S 127.0.0.1:3001 -t public public/router.php
```

Open [http://127.0.0.1:3001](http://127.0.0.1:3001). The database is created and seeded on first request at `data/locker.db`.

## Run with Docker

```bash
docker compose up --build
```

The app listens on port 3000. Data lives in the `locker-data` volume.

## Demo accounts

| Email | Password | Notes |
|---|---|---|
| `maya@campus.edu` | `campus123` | Has a couple of club secrets |
| `devon@campus.edu` | `campus123` | Empty shelf |

Register your own account if you want; it just has to end in `.edu`.

## Project layout

```
public/index.php      Front controller
public/router.php     PHP built-in server router
public/styles.css
src/bootstrap.php
src/db.php            Schema + first-run seed
src/crypto.php        Password and reset-token helpers
src/auth.php
src/vault.php
templates/
```

## Assignment notes

Host this somewhere your classmates and instructor can reach. Walk the running app until you can explain:

- how a password becomes the value stored in SQLite
- what happens after someone clicks “Forgot password”
- how vault rows are scoped to an account
- who the advisor account is for, even if you cannot sign in as them yet

Then look for a security defect in the running system, document how to trigger it, and patch it without breaking normal sign-in. Submit the hosted URL, a short architecture sketch, the writeup, and the patched repo.


## Case Study 4 patch: password reset

Reset tokens are now generated from 32 cryptographically random bytes. SQLite
stores only the SHA-256 digest of each token. A reset succeeds only if the token
was issued for that email on the current UTC day. A successful reset deletes the
token with the same SQL statement that checks it, so it cannot be used twice.
Requesting another token replaces the previous one. Old predictable tokens are
rejected. Password hashing and normal sign-in remain unchanged in this focused
patch.

The starter app displays a mail notice but has no mail delivery implementation.
For local testing, issue a token through the terminal:

```bash
php tools/issue-reset.php devon@campus.edu
```

Paste the printed token into `/reset` with the same email and a new password.
This helper is outside `public/`, works only with PHP CLI, and is for local demos.
A hosted recovery flow still needs actual email delivery; do not display tokens
on the public forgot-password page.

Run the isolated reset regression checks:

```bash
php tests/reset_tokens.php
```

The checks use an in-memory database and do not alter your demo accounts.

## Render Free classroom deployment

Create a Render Web Service from this GitHub repository, use the Docker runtime
with the root `Dockerfile`, select the Free instance type, and set the health
check path to `/health`. The container runs PHP using Render's `PORT` value.
Docker is used by Render only; the local PHP commands above remain available.

Free web services automatically sleep after 15 minutes without incoming traffic.
This timeout is controlled by Render. A request wakes the service back up, which
can take about one minute. No keep-alive service is configured.

Render Free has no persistent disk: SQLite data and PHP sessions reset when the
service sleeps, restarts, or redeploys. The supplied demo accounts are reseeded on
the next request. Newly registered accounts, added secrets, and changed passwords
are temporary. This is a classroom demonstration, not persistent secret storage.
Local database files are excluded from the repository upload and Docker build.

See [Render's Free service limitations](https://render.com/docs/free).
