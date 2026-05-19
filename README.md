# inforob/pagespeed-toolkit

A Composer-installable Symfony Bundle + Claude Code toolkit for achieving 90-100 PageSpeed scores.

It combines a **Symfony Bundle** with a console command that generates audit reports, an **AI agent** that analyzes your project and recommends optimizations, and a **slash command** that reads audit reports and applies fixes interactively.

## Symfony Installation

```bash
composer require inforob/pagespeed-toolkit
```

That's it. The package automatically:

- Registers `PageSpeedBundle` in `config/bundles.php`
- Creates `config/packages/pagespeed.yaml` with default configuration
- Adds `PAGESPEED_API_KEY` and `SITE_URL` placeholders to `.env`
- Copies `/pagespeed-fix` and the `pagespeed-optimizer` agent into `.claude/`

The only manual step: fill in your API key in `.env.local`:

```dotenv
PAGESPEED_API_KEY=your_google_api_key_here
SITE_URL=https://your-site.com
```

---

## How it works

```
┌─────────────────────────────────────────────────────────────────┐
│  1. AUDIT                                                       │
│  Run an audit command → generates pagespeed-report.json         │
│     • Symfony:  php bin/console pagespeed:audit                 │
│     • Any stack: ./integrations/shell/pagespeed-audit.sh <url> │
├─────────────────────────────────────────────────────────────────┤
│  2. FIX  (Claude Code)                                          │
│  /pagespeed-fix → Claude reads the report, shows failing        │
│  audits, and applies fixes one by one with your confirmation    │
├─────────────────────────────────────────────────────────────────┤
│  3. REVIEW  (Claude Code)                                       │
│  pagespeed-optimizer agent → deep analysis of NGINX config,     │
│  Core Web Vitals, and framework-specific optimizations          │
└─────────────────────────────────────────────────────────────────┘
```

---

## Installation

### 1. Copy the Claude Code files into your project

```bash
# From the root of your project
cp -r /path/to/claude-pagespeed-toolkit/.claude .
```

Or copy manually:
- `.claude/agents/pagespeed-optimizer.md` → your project's `.claude/agents/`
- `.claude/commands/pagespeed-fix.md` → your project's `.claude/commands/`

### 2. Set up the audit integration for your framework

| Framework | Instructions |
|---|---|
| **Symfony** | See [integrations/symfony/README.md](integrations/symfony/README.md) |
| **Any stack** | See [integrations/shell/README.md](#shell-script-universal) |
| Laravel | Coming soon |
| Next.js | Coming soon |
| WordPress | Coming soon |

### 3. Get a Google PageSpeed API key

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Enable the **PageSpeed Insights API**
3. Create an API key
4. Add it to your environment: `PAGESPEED_API_KEY=your_key_here`

The free tier allows ~25,000 requests/day — more than enough for local development.

---

## Shell Script (Universal)

Works with any framework. Requires `curl` and `jq`.

```bash
chmod +x integrations/shell/pagespeed-audit.sh

# Basic usage
PAGESPEED_API_KEY=your_key ./integrations/shell/pagespeed-audit.sh https://your-site.com

# Audit specific pages
PAGESPEED_API_KEY=your_key ./integrations/shell/pagespeed-audit.sh https://your-site.com \
  --urls /,/blog,/about \
  --strategy both \
  --output pagespeed-report.json
```

---

## Usage

### `/pagespeed-fix` — Interactive fixer

Open Claude Code in your project and run:

```
/pagespeed-fix
```

Claude will:
1. Detect your framework automatically
2. Locate or generate the audit report
3. Show failing audits grouped by category and impact
4. Ask which ones you want to fix
5. Apply each fix with minimum-necessary changes, reading the file first
6. Show a summary of changes and manual action items

### `pagespeed-optimizer` agent — Deep analysis

Ask Claude to launch the optimizer agent for a comprehensive review:

> "Can you check if my project is optimized for PageSpeed?"

The agent will:
- Detect your stack and existing configurations
- Audit NGINX config, frontend assets, and Core Web Vitals
- Deliver copy-paste-ready fixes with before/after score estimates

---

## Supported frameworks

| Framework | Audit command | Fix coverage |
|---|---|---|
| Symfony + Twig | `php bin/console pagespeed:audit` | Full (Twig templates, NGINX, CSS) |
| Any stack | `pagespeed-audit.sh` | Full (NGINX, HTML, CSS, JS) |
| Laravel + Blade | Coming soon | — |
| Next.js | Coming soon | — |
| WordPress | Coming soon | — |

---

## Contributing

Contributions are welcome. The most valuable additions are:

1. **New framework integrations** — add a folder under `integrations/` with the audit command and a README
2. **Fix guide entries** — extend the fix table in `.claude/commands/pagespeed-fix.md` with framework-specific fixes
3. **Agent improvements** — refine the optimizer agent's detection or output format

See [docs/contributing.md](docs/contributing.md) for details.

---

## License

MIT
