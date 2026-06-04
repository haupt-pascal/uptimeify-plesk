# Uptimeify for Plesk

> Open-source Plesk Obsidian extension that syncs your local domains to
> [uptimeify.io](https://uptimeify.io) uptime &amp; blacklist monitoring.

The extension is a thin, **API-only client** for the (closed-source) uptimeify.io
SaaS platform. It lets a Plesk **admin** connect an organization API token, map
Plesk domains to uptimeify customers + packages, and keep monitors in sync —
manually with one click or automatically on a schedule. No billing happens
inside Plesk; the extension only talks to the uptimeify REST API.

![Status](https://img.shields.io/badge/Plesk-Obsidian-2ee6b6) ![License](https://img.shields.io/badge/license-MIT-blue)

---

## Features

- **Connect & validate** — store your organization token (`wsm_…`) and resolve
  the organization via `GET /api/organization`.
- **Domain dashboard** — searchable table of all local Plesk hosting domains,
  matched against existing uptimeify monitors by URL.
- **Per-domain enable** — pick a **customer** and **package** per domain; the
  extension creates the monitor (`POST /api/websites`) under that customer.
- **One-click disable** — toggling off deletes the remote monitor
  (`DELETE /api/websites/:id`).
- **Scheduled sync** — an hourly Plesk task reconciles domains and can
  auto-create monitors for new domains under a configurable default
  customer + package.
- **Quota handling** — `403 Active website limit reached` is caught and shown as
  an upgrade CTA instead of a hard error.
- **DNSBL add-on (optional)** — also register the server IP for blacklist
  monitoring (`POST /api/customers/:id/ips`).
- **Home widget** — compact "All systems nominal" / "X systems down" panel on
  the Plesk admin home page.

## How packages work (important)

In uptimeify, a **package is assigned to a customer**, not to an individual
website. A website's limits (max URLs, check interval, …) come from its
customer's package. Therefore:

| Action in the dashboard            | What the extension does                                                                 |
| ---------------------------------- | --------------------------------------------------------------------------------------- |
| Enable a domain under a **new** customer | `POST /api/customers` with the chosen `packageType`, then `POST /api/websites`.    |
| Enable a domain under an **existing** customer | `POST /api/websites` only. The customer's existing package is **not** changed.  |
| Change a customer's package        | `PATCH /api/customers/:id` — **affects every website of that customer** (warned in UI). |

The scheduled auto-create uses the **default customer + default package** set on
the Settings tab.

## Architecture

```
meta.xml                      Extension manifest (id, version, min Plesk)
plib/
  controllers/                Plesk MVC controllers
    IndexController.php          Dashboard + AJAX sync actions
    SettingsController.php       Token, handshake, sync defaults
  library/
    Settings.php                pm_Settings wrapper (token, mapping, defaults)
    Api/Client.php              Guzzle (PSR-18) uptimeify REST client, 5s timeout
    Api/Exception/*             Typed errors (Unauthorized, QuotaExceeded, …)
    Plesk/DomainRepository.php  Reads local domains via pm_Domain
    Sync/DomainSyncService.php  Core reconcile / enable / disable logic
  hooks/HomePage.php            Admin home page widget
  scripts/                      sync.php (cron), post-install.php, pre-uninstall.php
  views/scripts/                .phtml templates
  resources/locales/            en-US, de-DE
htdocs/                         Public CSS/JS (dark teal theme)
vendor/                         Bundled Guzzle (production build only)
```

Library classes use the Plesk `Modules_Uptimeify_*` underscore naming so they are
autoloaded by the Plesk runtime; Guzzle is loaded from the bundled
`vendor/autoload.php`.

## Requirements

- Plesk Obsidian 18.0.20+ (Linux)
- PHP 8.2+ on the Plesk server
- An uptimeify.io account with an **organization-scoped** API token

## Install

### From a release ZIP

1. Download `uptimeify-<version>.zip` from the
   [Releases](https://github.com/uptimeify/plesk-extension/releases) page.
2. In Plesk: **Extensions → My Extensions → Upload Extension** and select the ZIP.
3. Open the extension, go to **Settings**, paste your `wsm_…` token and save.

### Build it yourself

```bash
git clone https://github.com/uptimeify/plesk-extension.git
cd plesk-extension
composer install
bin/build.sh            # produces dist/uptimeify-<version>.zip
```

## Configuration

On the **Settings** tab:

| Setting                         | Purpose                                                              |
| ------------------------------- | ------------------------------------------------------------------- |
| Organization API token          | Your `wsm_…` token. Validated on save.                              |
| Default customer / package      | Used by the scheduled sync to auto-create new domains.              |
| Default monitoring type         | `combined`, `http status` or `ssl check`.                          |
| Default check interval          | 1–60 minutes (minimum depends on the package).                     |
| Enable scheduled sync           | Turns on the hourly reconcile task.                                |
| Auto-create monitors            | Let the scheduled sync add new, unmonitored domains automatically. |
| Register server IP for DNSBL    | Optional blacklist monitoring (requires the DNSBL add-on).         |

## Development

```bash
composer install
composer lint        # php-cs-fixer (dry run)
composer fix         # php-cs-fixer apply
composer stan        # phpstan (level 6, against SDK stubs)
composer test        # phpunit
```

The Plesk SDK (`pm_*`) is not available locally; minimal stubs in
`tests/stubs/plesk-sdk.php` make static analysis and unit tests runnable off a
Plesk server.

## CI / Releases

- **CI** (`.github/workflows/ci.yml`) — runs php-cs-fixer, PHPStan and PHPUnit on
  PHP 8.2/8.3/8.4 for every push and PR, then builds the extension ZIP as an
  artifact.
- **Release** (`.github/workflows/release.yml`) — on a `vX.Y.Z` tag, verifies the
  tag matches `meta.xml`, builds the ZIP and publishes a GitHub Release.

To cut a release:

```bash
# bump <release> in meta.xml and CHANGELOG.md, commit, then:
git tag v1.0.0
git push origin v1.0.0
```

## Security & privacy

- The API token is stored via Plesk's `pm_Settings` (admin-scoped).
- All API calls go to `https://uptimeify.io` over HTTPS with a 5-second timeout.
- Uninstalling removes the scheduled task and local settings; it does **not**
  delete remote monitors.

## License

[MIT](LICENSE) — this Plesk extension is open source. The uptimeify.io platform
itself is a separate, closed-source SaaS product.
