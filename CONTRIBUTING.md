# Contributing

Thanks for helping improve the uptimeify Plesk extension!

## Getting started

```bash
composer install
composer test     # PHPUnit
composer stan     # PHPStan (level 6, against tests/stubs/plesk-sdk.php)
composer lint     # php-cs-fixer dry run  (composer fix to apply)
```

The Plesk SDK (`pm_*` classes) only exists on a real Plesk server. Locally we
rely on the lightweight stubs in `tests/stubs/plesk-sdk.php`. Keep the library
layer (`plib/library`) free of UI/framework coupling so it stays testable.

## Conventions

- PHP 8.2+ with `declare(strict_types=1)`, constructor promotion and typed
  signatures.
- Library classes follow the Plesk `Modules_Uptimeify_*` underscore naming so
  the Plesk autoloader resolves them; tests autoload them the same way.
- All outbound HTTP goes through `Modules_Uptimeify_Api_Client`. Don't call the
  API from controllers/views directly.
- Keep API timeouts at 5s — the Plesk UI must stay responsive.

## Pull requests

1. Branch from `main`.
2. Make sure `composer lint`, `composer stan` and `composer test` pass.
3. Update `CHANGELOG.md` under `[Unreleased]`.
4. Open the PR — CI runs the same checks on PHP 8.2/8.3/8.4.

## Releases

Maintainers bump `<release>` in `meta.xml`, move the changelog entry, then tag
`vX.Y.Z`. The release workflow validates the tag, builds the ZIP and publishes a
GitHub Release.

## Scope

This extension is an API client only. Monitoring logic, billing and account
management live in the uptimeify.io platform and are out of scope here.
