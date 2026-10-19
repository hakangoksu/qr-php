# Contact QR

You fill in a contact, the app gives you a QR code. Scan it with a phone camera and the phone
offers to save the person as a new contact. You can do the same through a REST API.

- Live: https://qr.goksu.me
- API documentation: https://qr.goksu.me/api/docs
- Source: https://github.com/hakangoksu/qr-php (private)

Why things are built the way they are, what they cost and what is missing: [NOTES.md](NOTES.md).

## Architecture

```
                         server (Debian 13)
 browser / API client    ┌────────────────────────────────────────────────────┐
 ── HTTPS :443 ────────▶ │ app   FrankenPHP: Caddy + PHP 8.4 + Symfony 7.4    │
 ── HTTP :80 ──────────▶ │       Caddy gets the TLS certificate by itself     │
    (redirect to HTTPS)  │       web pages and REST API ─▶ ContactService     │
                         │       every request ─▶ request log                 │
                         │          │                                         │
                         │          ▼                                         │
                         │ db    MariaDB 11.8 ── volume db-data               │
                         └────────────────────────────────────────────────────┘
```

There are two containers: the app and the database. The web pages and the REST API are just
two doors into the same `ContactService`, so a contact is checked and saved the same way
whichever door it comes through. Everything the app needs to remember (contacts, their QR
images, the request log) is in MariaDB, and MariaDB keeps its files in one Docker volume.

There is no nginx in front. Caddy, the web server inside FrankenPHP, talks HTTPS itself and
renews its Let's Encrypt certificate on its own.

## Technology stack

| | |
|---|---|
| Language and runtime | PHP 8.4 on FrankenPHP 1.13 |
| Framework | Symfony 7.4 LTS |
| Web pages | Twig templates, Symfony Form and Validator, one plain CSS file, no JavaScript framework |
| REST API | API Platform 5, with OpenAPI 3.2 docs and Swagger UI |
| Database | MariaDB 11.8 LTS, Doctrine ORM and DBAL, Doctrine Migrations |
| vCard and QR | a small vCard 3.0 builder of our own, endroid/qr-code for the PNG |
| Tests | PHPUnit 13 against a real MariaDB (Testcontainers) |
| Web server and HTTPS | Caddy inside FrankenPHP, Let's Encrypt |
| CI/CD | GitHub Actions, images on ghcr.io, deploy over SSH |

## Running it locally

You need Docker with the Compose plugin. Nothing else, unless you want to run the tests
outside Docker (then also PHP 8.4 with `pdo_mysql` and `gd`, and Composer).

First create the two settings files from their examples and change the values in them:

    cp .env.local.example .env.local
    cp .env.db.local.example .env.db.local

Both files must use the same database user and password. They are ignored by git.

Then:

    docker compose up --build        # start (add -d to run in the background)
    docker compose down              # stop, your data stays
    docker compose down -v           # stop and delete the data too

Once it runs:

| Address | What you find |
|---|---|
| http://localhost:8081 | the form for a new contact |
| http://localhost:8081/contacts | all contacts, with a search box |
| http://localhost:8081/logs | the request log, user `reviewer`, password from `LOG_VIEWER_PASSWORD` in `.env.local` |
| http://localhost:8081/api/docs | the API documentation |
| http://localhost:8081/health | 200 when the app and the database are fine, 503 when not |
| http://localhost:8081/health/live | 200 as long as the app runs |

## Where the data lives

All data is in the database, and the database files are in the Docker volume `qr-php_db-data`,
on your machine and on the server alike. Stopping the app, rebuilding it or deploying a new
version does not touch this volume. Only `docker compose down -v` deletes it.

The QR images are saved in the database too, next to their contacts, not as files. So a
backup of the database is a backup of everything.

## Configuration

Settings come from environment variables. Symfony reads `.env` first (safe defaults, in git,
no secrets), then `.env.local` (your own values, not in git). Real environment variables win
over both. On the server every value is in `/opt/qr-php/.env`, created on the server and never
committed.

| Variable | Default | What it does |
|---|---|---|
| `DATABASE_URL` | a placeholder | how to reach the database: `mysql://user:password@db:3306/name?charset=utf8mb4` |
| `APP_SECRET` | empty | Symfony's own secret |
| `LOG_VIEWER_PASSWORD` | empty | password for the request log (user `reviewer`). Empty means the log is closed |
| `TRUSTED_PROXIES` | empty | proxies whose `X-Forwarded-*` headers are believed. Nothing sits in front of the app, so it stays empty |
| `SERVER_NAME` | `:8080` | where Caddy listens. On the server: the domain over HTTPS, plus `http://:8080` for the health check |
| `RATE_LIMIT_CONTACTS_PER_MINUTE` | 10 | how many new contacts one IP address may create per minute |
| `REQUEST_LOG_RETENTION_DAYS` | 90 | request log rows older than this are deleted. 0 keeps them all |
| `MIGRATE_ON_STARTUP` | 1 | update the database schema when the container starts |

The database container gets its own settings (`MARIADB_*`) from `.env.db.local`. The app never
sees the MariaDB root password.

## Running the tests

Docker has to be running, because the database tests start their own short-lived MariaDB
container. With PHP and Composer on your machine:

    composer install
    php bin/phpunit

Or the way the CI does it, inside the same PHP image that runs in production:

    docker build --target test -t qr-php-test .
    docker run --rm --add-host=host.docker.internal:host-gateway \
      -v /var/run/docker.sock:/var/run/docker.sock \
      -e TESTCONTAINERS_HOST_OVERRIDE=host.docker.internal qr-php-test

There are 104 tests. They check the input rules, the vCard text, the QR image (it is read back
with a separate QR reader), the web pages, the REST API, the request log, the health checks,
the log password, the rate limit, the search and the log clean-up.

## How CI/CD works

Every push to `main` runs `.github/workflows/ci.yaml`, three jobs in a row:

1. **test** builds the test image and runs all tests in it. If one test fails, everything
   stops here and nothing is deployed.
2. **image** builds the production image and pushes it to ghcr.io as
   `ghcr.io/hakangoksu/qr-php:<commit sha>`.
3. **deploy** connects to the server over SSH and asks it to run that exact version.

The runner itself has no PHP installed, the tests run inside the image. The actions used are
pinned to fixed commits. The server address, SSH port, deploy key and the server's host key
are repository secrets. The image is pushed with the job's own token.

## How a deployment works

The deploy key can do exactly one thing on the server: run `/usr/local/bin/qr-deploy`
(`deploy/qr-deploy.sh`) for this app. Whatever command the CI sends, that script is what runs.
It:

1. accepts only this app's name and a 40-character commit SHA,
2. pulls the new image. If that fails, nothing has changed yet,
3. writes the new version into `/opt/qr-php/.env` and replaces **only the app container**,
4. waits up to 60 seconds for `/health` to answer 200,
5. if it does not, starts the previous version again and fails the job, so you see it in red.

The database container is never touched by a deployment. Neither is the TLS certificate, which
is kept in its own volume (`caddy-data`). When the new app container starts, it first brings
the database schema up to date (Doctrine migrations), then starts serving. The health check in
step 4 goes to `127.0.0.1:5103`, a plain HTTP port that only the server itself can reach.

## Using the API

Everything is under `/api/v1`. You send and get JSON. Errors come back in the standard
problem details format (`application/problem+json`).

| Method | Path | What you get |
|---|---|---|
| POST | `/contacts` | 201 with the new contact and a `Location` header. 422 with a message per field if the input is wrong. 429 if you create too many too fast |
| GET | `/contacts?page=1&q=` | 200, 20 contacts per page, newest first. `q` searches |
| GET | `/contacts/{id}` | 200 with the contact, or 404 |
| GET | `/contacts/{id}/qr` | 200 with the PNG (`?download=true` to save it as a file), or 404 |
| GET | `/contacts/{id}/vcard` | 200 with the vCard text, or 404 |
| GET | `/request-logs?source=&status=&method=&path=&from=&to=&page=` | 200, 50 log rows per page. Needs the log password |

The full description, with every field and real examples, is at `/api/docs`.

### Example requests

```
# Create a contact
curl -i -X POST https://qr.goksu.me/api/v1/contacts \
  -H 'Content-Type: application/json' \
  -d '{"firstName":"Ada","lastName":"Lovelace","email":"ada@example.com","birthday":"1990-12-10"}'

HTTP/2 201
location: /api/v1/contacts/1
{"id":1,"firstName":"Ada","lastName":"Lovelace","email":"ada@example.com","phone":null,
 "birthday":"1990-12-10","street":null,"postalCode":null,"city":null,"country":null,
 "organization":null,"jobTitle":null,"createdAt":"2026-10-10T05:41:53Z",
 "qrCodeUrl":"/api/v1/contacts/1/qr","vCardUrl":"/api/v1/contacts/1/vcard"}

# Wrong input: no email and no phone
curl -s -X POST https://qr.goksu.me/api/v1/contacts \
  -H 'Content-Type: application/json' -d '{"firstName":"Ada","lastName":"Lovelace"}'

{"status":422,"violations":[
  {"propertyPath":"email","message":"Enter an email address or a phone number, or both.","code":null},
  {"propertyPath":"phone","message":"Enter an email address or a phone number, or both.","code":null}],
 "detail":"email: Enter an email address or a phone number, or both.\nphone: ...","title":"An error occurred",...}

# List and search, one contact, its QR code
curl -s 'https://qr.goksu.me/api/v1/contacts?page=1&q=lovelace'
curl -s https://qr.goksu.me/api/v1/contacts/1
curl -s -o ada.png https://qr.goksu.me/api/v1/contacts/1/qr

# A contact that does not exist
curl -s https://qr.goksu.me/api/v1/contacts/999
{"title":"Contact not found","detail":"There is no contact with id 999.","status":404,
 "type":"https://tools.ietf.org/html/rfc9110#section-15.5.5"}

# The request log (password needed)
curl -s -u reviewer:<password> 'https://qr.goksu.me/api/v1/request-logs?source=API&status=404'
```

## What a contact needs

First name and last name are required, plus **an email address or a phone number** (or both).
A QR code with no way to reach the person would be useless. The database checks the same rule
again, so no other way of writing data can get around it.

The other rules: a phone number has 7 to 15 digits, may start with `+` and may contain spaces,
brackets, dots, slashes and hyphens. A birthday must be a real date, not in the future and not
before 1900. Every text field has a maximum length that matches its database column. Why these
rules: see NOTES.md.
