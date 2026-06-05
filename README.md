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

- **Connect & validate** — store your organization token (`wsm_…`); the
  organization is derived from the token automatically.
- **Mirror your Plesk customers** — each Plesk customer (client) maps 1:1 to a
  uptimeify customer. The extension matches existing customers by email or name
  and auto-creates the rest with your default package.
- **Domain dashboard** — searchable table of every Plesk hosting domain with its
  owning Plesk customer and the uptimeify customer it maps to.
- **Per-domain enable** — one click creates the monitor under the domain owner's
  customer (auto), or pick a different customer from the dropdown.
- **Mirror & sync all** — one button provisions every customer + monitor to match
  your Plesk structure (the agency workload killer).
- **Selective sync & ignore** — per-domain checkboxes with "Sync selected", a
  per-domain Ignore, and automatic exclusion of Plesk preview domains
  (`*.plesk.page`).
- **Customer filter (Filter tab)** — black/whitelist mode plus per-customer
  Always / Never / Default, applied to dashboard, bulk and scheduled sync.
- **One-click disable** — removes the remote monitor (`DELETE /api/websites/:id`).
- **Scheduled sync** — an hourly Plesk task mirrors customers and adds monitors
  for new domains.
- **Quota handling** — `403 Active website limit reached` is caught and shown as
  an upgrade prompt instead of a hard error.
- **Blacklist (DNSBL)** — optionally register the server IP per customer
  (`POST /api/customers/:id/ips`); included in the package, bound by its IP quota.
- **Home widget** — compact "All systems nominal" / "X systems down" panel on
  the Plesk admin home page.

## How the mirror works (important)

In uptimeify a **package is assigned to a customer**, not to an individual
website — a website inherits its limits (max URLs, check interval, …) from its
customer's package. The extension mirrors your Plesk structure accordingly:

| Step | What the extension does |
| ---- | ----------------------- |
| Resolve the domain's Plesk customer | `pm_Domain::getClient()` → name + email. |
| Find the matching uptimeify customer | by email, then by name (cached as a client→customer map). |
| If none exists and auto-create is on | `POST /api/customers` with the **default package**. |
| Create the monitor | `POST /api/websites` under that customer. |

Changing an existing customer's package is left to uptimeify (it would affect all
that customer's websites), so the extension never changes it implicitly.

## Architecture

```
meta.xml                      Extension manifest (id, version, release, min Plesk)
DESCRIPTION.md                Long-form description shown in the Plesk catalog
_meta/icons/                  Catalog icons (32/64/128/160 px PNG)
htdocs/index.php              UI entry point (pm_Application) — powers "Open"
plib/
  controllers/                Plesk MVC controllers
    IndexController.php          Dashboard + AJAX sync/enable/disable/ignore actions
    FilterController.php         Filter tab — customer black/whitelist
    SettingsController.php       Token, handshake, sync defaults (setup wizard)
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
plib/vendor/                    Bundled Guzzle (in the built package only; Plesk auto-loads it)
```

Library classes use the Plesk `Modules_Uptimeify_*` underscore naming so they are
autoloaded by the Plesk runtime. Composer dependencies live under `plib/vendor/`
in the built package, which Plesk includes automatically (`bin/build.sh` places
them there; locally they stay in the default `vendor/`).

## Requirements

- Plesk Obsidian 18.0.20+ (Linux)
- PHP 8.2+ on the Plesk server
- An uptimeify.io account with an **organization-scoped** API token

## Install

### From a release ZIP

1. Download `uptimeify-<version>.zip` from the
   [Releases](https://github.com/haupt-pascal/uptimeify-plesk/releases) page.
2. In Plesk: **Extensions → My Extensions → Upload Extension** and select the ZIP.
3. Open the extension, go to **Settings**, paste your `wsm_…` token and save.

### Build it yourself

```bash
git clone https://github.com/haupt-pascal/uptimeify-plesk.git
cd uptimeify-plesk
composer install
bin/build.sh            # produces dist/uptimeify-<version>.zip
```

## Configuration

On the **Settings** tab:

| Setting                          | Purpose                                                             |
| -------------------------------- | ------------------------------------------------------------------- |
| Organization API token           | Your `wsm_…` token. Validated on save.                             |
| Mirror Plesk customers           | Auto-create a uptimeify customer per Plesk customer.               |
| Default package for new customers| Package assigned to auto-created customers.                       |
| Default monitoring type          | `combined`, `http status` or `ssl check`.                         |
| Default check interval           | 1–60 minutes (minimum depends on the package).                    |
| Enable scheduled sync            | Hourly job that mirrors customers and adds monitors.              |
| Monitor server IP (DNSBL)        | Optional blacklist monitoring; included in the package.          |

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
  tag matches `meta.xml` `<version>`, builds the ZIP and publishes a GitHub Release.

To cut a release (see [VERSIONING.md](VERSIONING.md) for the field semantics):

```bash
# bump <version> (SemVer) and increment <release> (integer) in meta.xml,
# move the CHANGELOG entry, commit, then:
git tag v1.0.2
git push origin main --tags
```

## Security & privacy

- The API token is stored via Plesk's `pm_Settings` (admin-scoped).
- All API calls go to `https://uptimeify.io` over HTTPS with a 5-second timeout.
- Uninstalling removes the scheduled task and local settings; it does **not**
  delete remote monitors.

## License

[MIT](LICENSE) — this Plesk extension is open source. The uptimeify.io platform
itself is a separate, closed-source SaaS product.
