<p align="center">
    <img src="https://raw.githubusercontent.com/Swage-Deploy/swage-core/main/public/favicon.svg" width="72" height="72" alt="Swage">
</p>

<h1 align="center">Swage Console</h1>

<p align="center">
    One dashboard for the servers, sites and deployments across your Swage panels.
    <br>
    <a href="https://swagedeploy.app"><strong>swagedeploy.app</strong></a> · <a href="https://swagedeploy.app/docs/api">API documentation</a>
</p>

---

If you run more than one Swage panel, Swage Console shows all of them in one place. It reads each panel through the Swage API with a read-only key, so it can't change anything on a panel or its servers, and it never connects to the servers itself.

## Features

- **Every server at a glance**: each panel's servers listed under its name, with their CPU, load, memory and disk, and charts of the last 24 hours.
- **Needs attention**: unreachable servers, reboots waiting, failed tasks and deployments, and certificates that are not renewing, across every panel.
- **Sites and deployments**: the latest deployment of every site, and days until each site's certificate expires.
- **Daemons and schedules**: the Supervisor daemons and cron jobs on each server.
- **Recent activity**: the tasks Swage ran on your servers.
- **Links into Swage**: every server, site and panel opens its page in the panel it came from.
- **Secure sign-in**: a single account, created on first launch, with two-factor authentication and passkeys. API keys are stored encrypted.

## Requirements

- PHP 8.3 or later
- Swage `v1.3.19` or later on each panel
- A read-only API key for each panel, made on the panel's **Settings → API keys** page

The console reads what each key can reach. Give the key read access to **Servers**, **Sites**, **Deployments**, **Daemons** and **Certificates**. An area the key can't read is left empty rather than failing.

## Installation

Swage Console is a standard Laravel application. On the server that will run it:

```bash
git clone https://github.com/Swage-Deploy/swage-console.git
cd swage-console
composer run setup
```

Point your web server at `public/`, set `APP_URL` and `APP_ENV=production` in `.env`, and run the scheduler every minute, which records each server's usage every five minutes:

```bash
* * * * * cd /path/to/swage-console && php artisan schedule:run >> /dev/null 2>&1
```

Then open the console. Whoever opens it first creates its account, and setup closes once that account exists. Set up two-factor authentication under **Settings → Security**.

## Adding panels

On **Settings → Panels**, add each panel's name, its URL, such as `https://swage.example.com`, and its API key. The console checks the connection when you save and shows who the key belongs to and what it can read. Choose **Check** to test it again later.

If a panel is behind Cloudflare Access, its `api/*` path must bypass Access, or the console gets the sign-in page instead of the API. See [Cloudflare Access](https://swagedeploy.app/docs/cloudflare-access).

## Configuration

`config/swage.php` sets how the console reads your panels:

| Setting | Default | What it does |
|---|---|---|
| `cache_seconds` | `60` | How long each panel's API responses are cached. The API allows 120 requests a minute per key. |
| `history_hours` | `24` | Hours of usage history the charts show, up to 168. |
| `stale_after_minutes` | `15` | A server whose latest reading is older than this is shown as down. |
| `keep_readings_days` | `7` | Days of recorded readings to keep. |

## Development

```bash
composer run setup
composer run dev
```

Run the tests with:

```bash
php artisan test --compact
```

## Trademarks

Swage is an independent project and is not affiliated with, endorsed by or sponsored by Laravel or Cloudflare. These names, and any related logos, are trademarks of their respective owners, who also hold the copyright in their products. They are used here only to describe what Swage works with.

## License

Copyright (c) 2026 Node Dog Consulting. Released under the [MIT License](LICENSE).
