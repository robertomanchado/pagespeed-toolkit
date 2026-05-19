# Symfony Integration

> **This directory is kept for reference only.**
> The official way to use this toolkit in Symfony is via Composer:
>
> ```bash
> composer require inforob/pagespeed-toolkit
> ```
>
> See the [main README](../../README.md) for full installation instructions.

---

## What the bundle provides

- **`pagespeed:audit`** console command — calls Google PageSpeed Insights and saves a JSON report
- Auto-wired `PageSpeedService` available for injection in your own services
- Configuration via `config/packages/pagespeed.yaml`

## Console command reference

```bash
# Audit all configured pages (mobile + desktop)
php bin/console pagespeed:audit

# Audit a single path
php bin/console pagespeed:audit --url /blog

# Audit specific paths
php bin/console pagespeed:audit --urls /,/blog,/about

# Desktop only
php bin/console pagespeed:audit --strategy desktop

# Custom output path
php bin/console pagespeed:audit --output var/my-report.json
```

## Configuration reference

```yaml
# config/packages/pagespeed.yaml
pagespeed:
    api_key: '%env(PAGESPEED_API_KEY)%'   # required
    site_url: '%env(SITE_URL)%'            # required
    report_path: 'var/pagespeed-report.json'  # optional default
```

## Page list resolution order

1. `--url /path` flag — single page
2. `--urls /,/blog,/about` flag — explicit list
3. `PAGESPEED_URLS` env var — comma-separated list from `.env`
4. Fallback: `/`, `/blog`, `/contact`, `/login`
