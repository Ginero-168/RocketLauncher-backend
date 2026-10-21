# Changelog

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
