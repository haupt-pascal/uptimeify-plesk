# Versioning

Short and crisp. We use [Semantic Versioning](https://semver.org): `MAJOR.MINOR.PATCH`.

- **MAJOR** — breaking change (API client incompatibility, removed setting, data/mapping migration required).
- **MINOR** — new feature, backwards compatible.
- **PATCH** — bug fix or internal change, no behaviour change for users.

Pre-releases: append `-rc.N` or `-beta.N` (e.g. `1.1.0-rc.1`). The release
workflow marks these as GitHub pre-releases automatically.

## The two meta.xml fields (don't mix them up)

Plesk's `meta.xml` has two distinct fields:

| Field             | Meaning                                              | Example  |
| ----------------- | ---------------------------------------------------- | -------- |
| `<version>`       | **The semantic version shown in Plesk.** Source of truth. Must equal the git tag. | `1.2.0`  |
| `<release>`       | **Integer build number.** Must strictly increase on every published build so Plesk detects an upgrade. Not shown as the version. | `7`      |

So the git tag matches `<version>`, prefixed with `v`:

```
<version>1.2.0</version>   ->   tag v1.2.0
<release>7</release>       (just bump by +1 each release)
```

The release workflow fails the build if the tag and `<version>` disagree.

Pre-releases: append `-rc.N` or `-beta.N` to `<version>` and the tag
(e.g. `1.1.0-rc.1`); the release workflow marks them as GitHub pre-releases.

## Cutting a release

1. Bump `<version>` to the new SemVer in `meta.xml`.
2. Increment `<release>` by 1 in `meta.xml`.
3. Move the `[Unreleased]` entries in `CHANGELOG.md` under the new version + date.
4. Commit (`chore(release): vX.Y.Z`).
5. Tag and push:

   ```bash
   git tag vX.Y.Z
   git push origin main --tags
   ```

Pushing the tag triggers `.github/workflows/release.yml`, which builds the
extension ZIP and publishes the GitHub Release. Never edit a published tag —
ship a new patch instead.
