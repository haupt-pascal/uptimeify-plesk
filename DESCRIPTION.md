## Uptimeify Monitoring for Plesk

Bring [uptimeify.io](https://uptimeify.io) uptime &amp; blacklist monitoring
directly into your Plesk panel. Instead of creating monitors by hand in an
external app, an admin connects one organization API token and activates
monitoring for hosted domains right from Plesk — one click at a time or fully
automatic.

This extension is a free, **open-source API client**. Monitoring, alerting and
billing all live in the uptimeify.io platform; the extension only talks to its
REST API over HTTPS.

### What you can do

- **Connect once** — store your organization API token (`wsm_…`); the extension
  validates it and resolves your organization automatically.
- **See every domain** — a searchable table of all local Plesk hosting domains,
  matched against the monitors you already have in uptimeify.
- **Enable per domain** — pick a customer and package for each domain; the
  extension creates the website monitor under that customer.
- **Disable in one click** — turning monitoring off removes the remote monitor.
- **Stay in sync automatically** — an hourly task reconciles domains and can
  auto-create monitors for new domains under a default customer and package.
- **Never blow your quota silently** — when a package limit is reached, the
  extension shows a clear upgrade prompt instead of failing.
- **Optional blacklist monitoring** — register your server IP for DNSBL checks
  (requires the DNSBL add-on on your uptimeify plan).
- **At-a-glance health** — a Plesk home-page widget summarizes whether all
  monitored sites are nominal or some are down.

### How packages work

In uptimeify a package is assigned to a **customer**, and a website inherits its
limits (max URLs, check interval, …) from that customer's package. When you
enable a domain you choose the target customer and package: a new customer is
created with that package, while an existing customer keeps its current package
unless you explicitly change it.

### Requirements

- Plesk Obsidian 18.0.20+ (Linux)
- An uptimeify.io account with an organization-scoped API token

No uptimeify account yet? Start at [uptimeify.io](https://uptimeify.io).

### Open source

Source, issues and releases:
[github.com/haupt-pascal/uptimeify-plesk](https://github.com/haupt-pascal/uptimeify-plesk)
(MIT licensed).
