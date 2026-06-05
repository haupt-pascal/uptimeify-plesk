# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.9.2] - 2026-06-05

### Changed
- **Catalog description refreshed** for the store listing: documents the two
  home-page widgets (per-server and account-wide) and the white-label brand
  name. Documentation only — no code or behaviour change.

## [1.9.1] - 2026-06-05

### Changed
- **Per-server widget now shows real coverage + health** instead of a thin block:
  monitored domains, how many are online (green), how many need attention (red)
  and open incidents on this server (red), plus an "X of Y domains on this server
  are monitored" caption. Reminder: the figures fill the first time the Dashboard
  is opened (or the scheduled sync runs) under this version, because the home
  widget reads cached values and never calls the API on home-page load.

## [1.9.0] - 2026-06-05

### Added
- **Per-server home-page widget** — a second status block scoped to *this* Plesk
  server only: domains on the server, how many are monitored, how many need
  attention (red) and open incidents on this server's domains (red). It sits
  above the account-wide block, since the server's own health is what the
  operator cares about most. Both blocks read cached values (no API call on
  home-page load); server-scoped incidents are matched from the org incident
  list by website URL.

## [1.8.0] - 2026-06-05

### Added
- **White-label brand name** (Settings → step 2): set a custom brand name that
  replaces “Uptimeify” in the Plesk home-page widget and page titles. Defaults to
  “Uptimeify” when left blank.
- **Richer home-page widget**: the status block is now a stat row — total
  monitors, how many need attention (red) and open incidents (red) — with an
  “Open <brand>” button. The figures reflect your whole uptimeify organization
  (account-wide health), not just this server's domains.
- **Open-incident metric**: `Api\Client::listIncidents()` (GET `/api/incidents`)
  and a cached `status.incidents` count, populated best-effort on dashboard load
  and scheduled sync so the widget never makes a blocking API call.

## [1.7.0] - 2026-06-05

### Added
- **Home page widget (new SPV dashboard)** via `Plesk\SDK\Hook\Home`: a status
  block (`plib/hooks/Home.php` + `Modules_Uptimeify_Hook_StatusBlock`) showing
  the cached monitoring status and a link into Uptimeify. Works on Plesk 18.0.60+.

### Removed
- The legacy `pm_Promo_AdminHome` widget, which is not rendered by the new
  personalised dashboard in current Plesk.

## [1.6.4] - 2026-06-05

### Fixed
- Home widget: removed the custom isActive() override (not part of the documented
  API) which could hide the promo; it now always renders, matching the canonical
  Plesk example.

## [1.6.3] - 2026-06-05

### Fixed
- **Home widget still not shown**: Plesk requires the exact class name
  `Modules_<Id>_Promo_AdminHome` in `plib/library/Promo/AdminHome.php` (per the
  official docs). Renamed from `Promo_Home` so Plesk auto-loads it.

## [1.6.2] - 2026-06-05

### Fixed
- **Home widget did not appear.** It needed no pm_Hook_Promos wrapper (Plesk
  discovers pm_Promo_AdminHome subclasses directly) and each promo method must
  call pm_Context::init() first, since promos run outside the extension context.

## [1.6.1] - 2026-06-05

### Fixed
- Add return types to the home-widget promo methods so PHPStan passes (no
  functional change over 1.6.0).

## [1.6.0] - 2026-06-05

### Added
- **Admin home-page widget** — shows the monitoring status (all nominal / N need
  attention) and links into Uptimeify. Implemented via the correct Plesk hook
  (`pm_Hook_Promos` → `pm_Promo_AdminHome`); reads a cached status so it never
  makes a blocking API call on home-page load.

### Fixed
- The previous widget never worked: it extended a non-existent `pm_Hook_HomePage`
  and lived in `plib/hooks/` (not autoloaded). Removed.

## [1.5.1] - 2026-06-05

### Fixed
- **Filter tab was unreachable**: Plesk's native tab bar does not render in this
  setup, so the Dashboard/Filter/Settings navigation is now an explicit link bar
  shown at the top of every page.

### Notes
- Service-plan level filtering was evaluated and intentionally **not** pursued:
  Plesk plan names are not unique (scoped per provider) and the domain→plan
  mapping requires fragile XML-RPC. Customer-level filtering covers the need.

## [1.5.0] - 2026-06-05

### Added
- **Filter tab with customer black/whitelist.** A new "Filter" tab lets you choose
  a mode (blacklist = sync all except excluded, or whitelist = sync only included)
  and set each Plesk customer to Default / Always sync / Never sync.
- Auto-sync, bulk sync and the dashboard now respect the filter; excluded
  customers' domains are hidden from the dashboard with a count + link to Filter.
- Precedence: domain "Ignore" > customer filter > mode default.

## [1.4.2] - 2026-06-04

### Changed
- The DNSBL server-IP entry is now labelled `Plesk: <hostname>` (server
  hostname), matching the `Plesk: <domain>` website naming, instead of the
  generic "Plesk Server IP".

## [1.4.1] - 2026-06-04

### Changed
- Preview/ignored domains no longer show an "Enable" button. Preview domains
  (`*.plesk.page`) show only the tag; manually ignored domains show only
  "Stop ignoring".

## [1.4.0] - 2026-06-04

### Added
- **Ignore function**: exclude any domain from sync ("Ignore" / "Stop ignoring"
  per row). Ignored domains are skipped by auto-sync and bulk sync.
- **Plesk preview domains** (`*.plesk.page`) are now ignored by default — they
  are never auto-synced and are shown muted with a "(Plesk preview domain)" tag.
  They can still be enabled manually per row if desired.

### Changed
- Bulk and scheduled sync skip ignored and preview domains; their checkboxes are
  removed so they cannot be bulk-selected.

## [1.3.3] - 2026-06-04

### Changed
- The dashboard **status is now localized** (e.g. "Aktiv" instead of the raw API
  "active").

## [1.3.2] - 2026-06-04

### Changed
- **DNSBL server IP is registered per customer** (once per customer+IP), so every
  customer hosted on the server gets its own blacklist notifications. Redundant
  attempts within a sync run are skipped and "already exists" responses are
  treated as success.

## [1.3.1] - 2026-06-04

### Fixed
- **Enable/sync failed with a forgery-protection (CSRF) error**: the dashboard
  AJAX calls now read Plesk's `forgery_protection_token` meta tag and send it as
  both the `X-Forgery-Protection-Token` header and the form field.

### Changed
- **No more browser pop-ups.** Action results use Plesk's native status messages
  (shown after reload); the delete confirmation is now an inline "Remove / Cancel"
  control instead of a `window.confirm` dialog; transport errors show an inline
  Plesk-styled message box.

## [1.3.0] - 2026-06-04

### Added
- **Selective sync**: a checkbox per unmonitored domain (with a select-all
  header) and a **"Sync selected"** button to mirror & monitor just the chosen
  domains, alongside the existing "Mirror & sync all".

## [1.2.4] - 2026-06-04

### Changed
- The dashboard per-domain **package dropdown is pre-selected** with the default
  package configured in Settings.

## [1.2.3] - 2026-06-04

### Fixed
- **Plesk customer name now resolves correctly**: used the wrong client property
  key (`company`); the valid Plesk key is `cname`. Customer names (company, then
  contact person, then login) now show in the dashboard and drive customer
  matching/creation.

## [1.2.2] - 2026-06-04

### Fixed
- **Empty "Plesk customer" column**: reading a missing client property (e.g.
  `company`) threw and blanked the whole owner. Each property is now read
  defensively with `pname`/login fallbacks.
- **No way back to Settings from the dashboard**: tabs now render via
  `view->tabs`, plus an explicit "Settings" button on the dashboard.

### Changed
- The "Customer" column now shows the target customer (→ Plesk customer) for
  unmonitored domains instead of staying blank.

## [1.2.1] - 2026-06-04

### Fixed
- Whitespace in the install hook scripts so php-cs-fixer passes. No functional
  change over 1.2.0.

## [1.2.0] - 2026-06-04

### Fixed
- **Scheduled sync never registered**: the install hook used a non-existent
  `pm_Scheduler_Task::setScript()`. It now uses `setCmd()` + a proper cron
  schedule, so the background sync actually runs.

### Added
- **Configurable sync frequency** (every 15 min / 30 min / hourly / daily). The
  Plesk scheduled task is (re)registered whenever the sync settings are saved.
- Settings are now grouped into "Automatic synchronization" (enable, frequency,
  mirror customers, default package) and "Monitor defaults" (type, check
  interval), plus optional DNSBL.

## [1.1.1] - 2026-06-04

### Fixed
- Add missing array type annotations (`enable()`, `createMonitor()`) so PHPStan
  level 6 passes. No functional change over 1.1.0.

## [1.1.0] - 2026-06-04

### Added
- **Mirror Plesk customers into uptimeify (1:1).** Each Plesk customer (client)
  maps to a uptimeify customer — matched by email then name, auto-created with the
  default package when missing. Domains become monitors under the right customer.
- **"Mirror & sync all"** one-click bulk provisioning of customers + monitors.
- **Per-domain customer choice** on the dashboard: default "Auto: <Plesk customer>"
  or override via dropdown; package selectable for newly created customers.
- Dashboard now shows the owning Plesk customer and the mapped uptimeify customer.

### Changed
- Replaced the single "default customer" setting with "Mirror Plesk customers"
  plus a "default package for new customers".
- Corrected the DNSBL description: it is **included in the package** (bound by the
  IP quota), not a separate add-on.

### Fixed
- DNSBL server-IP registration now uses the real per-customer endpoint during
  monitor creation.

## [1.0.7] - 2026-06-04

### Fixed
- **`HTTP 400: Organization ID required`** on the dashboard: the organization is
  derived from the API token, so `organizationId` is no longer forced onto
  `listCustomers` (passing `0` made the API reject the request). It is now sent
  only when a valid id is known.

### Changed
- "Connected" state is now tracked by an explicit validation flag instead of the
  organization id (which the API does not always return), so the setup wizard
  works even when the org id is absent. Stored tokens are auto-validated on the
  Settings page after an upgrade.

## [1.0.6] - 2026-06-04

### Fixed
- **Fatal `Call to undefined method pm_Domain::getAsciiName()`** when opening the
  dashboard: use the real `pm_Domain` API (`getName()` already returns the ASCII
  name, plus `getDisplayName()`), and harden server-IP detection. Corrected the
  test SDK stub so PHPStan catches such mismatches in future.

## [1.0.5] - 2026-06-04

### Changed
- Surface the underlying transport error (cURL message) on connection failures
  instead of a generic "could not reach" message, to make network/DNS/proxy
  issues diagnosable.
- Raise the API timeout from 5s to 10s for slower outbound networks.

## [1.0.4] - 2026-06-04

### Fixed
- **Fatal `Failed opening required vendor/autoload.php`** when using the API:
  Composer dependencies are now bundled under `plib/vendor/` (so Plesk auto-loads
  them) and the client loads the autoloader via `pm_Context::getPlibDir()` instead
  of a brittle relative path.

## [1.0.3] - 2026-06-04

### Fixed
- **Translations not resolving** (`[[key]]` shown everywhere): Plesk locale files
  must assign to a `$messages` variable, not `return` the array.
- **Custom theme broke the Plesk layout**: removed the dark stylesheet; the UI now
  uses native Plesk styling (`list` tables, default form/colors).

### Added
- **Setup wizard**: the Settings tab is now a two-step flow — Step 1 connects and
  validates the API token; Step 2 (only after a successful connection) configures
  sync defaults with populated customer/package dropdowns.

## [1.0.2] - 2026-06-04

### Fixed
- **Missing "Open" button**: added `htdocs/index.php` entry point so Plesk
  exposes the extension UI.
- **Missing catalog icon**: added `_meta/icons/` PNGs (32/64/128/160) using the
  uptimeify brand mark.
- **Wrong version in Plesk**: corrected `meta.xml` field semantics — `<version>`
  now holds the SemVer (shown in Plesk) and `<release>` is an integer build
  number. The release workflow now verifies the tag against `<version>`.

### Added
- `DESCRIPTION.md` long-form catalog description.
- Real uptimeify logo for the in-app dark UI (`htdocs/css/logo.svg`).

### Changed
- `bin/build.sh` now bundles `_meta/` and `DESCRIPTION.md` into the package.

## [1.0.1] - 2026-06-04

### Changed
- Bump GitHub Actions to Node.js 24 compatible majors: `actions/checkout@v6`,
  `actions/cache@v5`, `actions/upload-artifact@v7`,
  `softprops/action-gh-release@v3`.

## [1.0.0] - 2026-06-04

### Added
- Initial release.
- Settings tab: organization API token, connection handshake, sync defaults.
- Domain dashboard matching local Plesk domains to uptimeify monitors.
- Per-domain enable (choose customer + package) and one-click disable.
- Hourly scheduled sync with optional auto-create for new domains.
- Quota (`403 limit reached`) handling with upgrade CTA.
- Optional DNSBL server-IP registration.
- Admin home page widget.
- GitHub Actions CI (lint, PHPStan, PHPUnit) and tag-based release pipeline.

[Unreleased]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.9.2...HEAD
[1.9.2]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.9.1...v1.9.2
[1.9.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.9.0...v1.9.1
[1.9.0]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.8.0...v1.9.0
[1.8.0]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.7.0...v1.8.0
[1.7.0]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.6.4...v1.7.0
[1.6.4]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.6.3...v1.6.4
[1.6.3]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.6.2...v1.6.3
[1.6.2]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.6.1...v1.6.2
[1.6.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.6.0...v1.6.1
[1.6.0]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.5.1...v1.6.0
[1.5.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.4.2...v1.5.0
[1.4.2]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.3.3...v1.4.0
[1.3.3]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.3.2...v1.3.3
[1.3.2]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.2.4...v1.3.0
[1.2.4]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.2.3...v1.2.4
[1.2.3]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.7...v1.1.0
[1.0.7]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.6...v1.0.7
[1.0.6]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.5...v1.0.6
[1.0.5]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.4...v1.0.5
[1.0.4]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/haupt-pascal/uptimeify-plesk/releases/tag/v1.0.0
