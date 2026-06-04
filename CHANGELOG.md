# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.5...HEAD
[1.0.5]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.4...v1.0.5
[1.0.4]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/haupt-pascal/uptimeify-plesk/releases/tag/v1.0.0
