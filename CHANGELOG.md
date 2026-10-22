# Changelog

## 2026-10-22 — homepage, Admin, central AI

### Homepage
- New `index.php`: a download button for the panel's signed `.zxp` (version, size and date from
  the uploaded file), a 30-second intro video recorded from the real panel, the panel's features
  and the three install steps. The catalog moved to `market.php`; old `index.php?view=` and
  `?type=` links redirect there.
- The whole site uses the panel's look: monochrome HUD, full-colour glass buttons, mono type.

### Admin
- `admin.php`: claim it once with the setup token; admins can add and remove admins (never the
  last one). Settings: item life in days, maximum script / style / preview size, uploads per
  member per day, total storage, panel connection starts, which kinds are accepted, maintenance
  mode, `X-Forwarded-For` trust (with a diagnostics block to decide), maximum release size.
- Upload the panel's `.zxp`: it must be a signed `com.tata.pro` package; its version is read from
  the manifest and it replaces the previous download (`download.php`).
- Delete any item, disconnect a member's panels, and a log of every change.

### Central AI
- `api/ai.php`: connected panels use AI through the website's Replicate key, set in Admin, with
  the model, a per-member daily quota, a site-wide daily quota and a prompt size limit. Requests
  that fail to start are not counted; slow answers are polled by id, only by their owner.
  Replicate's own error text (which can echo the key) never reaches the panel.
- `api/info.php`: limits, accepted kinds, AI status and the current release for the panel and the
  homepage (ETag, 304 when unchanged).

### Developer
- `php tests/run.php`: 147 checks, now with a fake Replicate server, Admin permissions and
  settings, maintenance mode, and release upload and download.

## 2026-10-22

### Security
- Pages cannot be framed and run no scripts (CSP, X-Frame-Options): the panel "allow" button can
  no longer be clickjacked.
- The allow page shows the connection code, and the panel shows the same code, so a link with
  someone else's code is easy to spot.
- A panel token is handed over once and only its hash is kept. Signing out in the panel revokes
  it; members can disconnect every panel from the website.
- Panel connection starts are rate limited (20 per 10 minutes per address).
- Uploads are limited (20 per member per day, 2 GB in total); Graphic Style files must be
  Illustrator files and scripts must be text.
- `setup.php` closes itself once Google login is set and never recreates a damaged `config.json`.
- Logout needs the page's POST form; helpers, backups, dot folders and tests are not served;
  errors go to the log, not the page.

### Reliability
- Data files keep their previous version as `.bak`; a damaged file is read from it, and with both
  unreadable the site stops (503) instead of treating the catalog as empty and overwriting it.
- Hourly housekeeping removes expired items, stray files and expired panel connections.
- Downloads count once per visitor per day.

### Speed
- No PHP session for visitors who are not signed in (the panel included); reading the catalog
  takes no write lock.
- The catalog answers with an ETag (304 when unchanged); previews are cached for 30 days in browsers and the panel, one day on the CDN.
- Lazy-loaded previews with fixed sizes on the website.

### Website
- Members delete their own items (with a confirm step) and see how many days an item has left.
- Downloads keep Thai file names.

### Developer
- `php tests/run.php`: 87 end-to-end checks against PHP's built-in server; GitHub Actions runs them
  with `php -l` on every push and pull request.
