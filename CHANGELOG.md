# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/uptimeify/plesk-extension/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/uptimeify/plesk-extension/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/uptimeify/plesk-extension/releases/tag/v1.0.0
