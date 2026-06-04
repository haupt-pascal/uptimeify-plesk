# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.2.2...HEAD
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
