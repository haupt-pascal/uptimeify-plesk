# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.2...HEAD
[1.0.2]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/haupt-pascal/uptimeify-plesk/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/haupt-pascal/uptimeify-plesk/releases/tag/v1.0.0
