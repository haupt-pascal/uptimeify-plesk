# Versioning

Short and crisp. We use [Semantic Versioning](https://semver.org): `MAJOR.MINOR.PATCH`.

- **MAJOR** — breaking change (API client incompatibility, removed setting, data/mapping migration required).
- **MINOR** — new feature, backwards compatible.
- **PATCH** — bug fix or internal change, no behaviour change for users.

Pre-releases: append `-rc.N` or `-beta.N` (e.g. `1.1.0-rc.1`). The release
workflow marks these as GitHub pre-releases automatically.

## Single source of truth

`meta.xml` `<release>` **is** the version. The git tag must match it exactly,
prefixed with `v`:

```
<release>1.2.0</release>   ->   tag v1.2.0
```

The release workflow fails the build if tag and `meta.xml` disagree.

## Cutting a release

1. Bump `<release>` in `meta.xml`.
2. Move the `[Unreleased]` entries in `CHANGELOG.md` under the new version + date.
3. Commit (`chore(release): vX.Y.Z`).
4. Tag and push:

   ```bash
   git tag vX.Y.Z
   git push origin main --tags
   ```

Pushing the tag triggers `.github/workflows/release.yml`, which builds the
extension ZIP and publishes the GitHub Release. Never edit a published tag —
ship a new patch instead.
