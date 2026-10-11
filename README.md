# SEO Redirect Manager for Craft CMS 5

![SEO Redirect Manager](banner.jpeg)

An enterprise-grade, high-performance redirect management and 404 monitoring plugin for **Craft CMS 5**. Built with zero runtime performance penalty for valid requests, stateless Craft Cloud compatibility, SSRF security validation, and automated redirect loop/chain detection.

---

## ✨ Features

- ⚡ **Zero Overhead on Normal Requests**: Hooks into Craft's `ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION` so only actual 404/not-found requests ever execute redirect resolution logic. 200 OK traffic experiences 0ms latency.
- 🎯 **Multi-Tier Matching Engine**:
  - Exact URL match (site-specific prioritized over global fallback).
  - Regular Expression (regex) matching with capture group backreference substitutions (`$1`, `$2`).
  - Query string handling policies: `preserve`, `exact`, `ignore`.
- 🔍 **404 Monitor & Aggregation**:
  - Automatic hit-counter aggregation with first/last seen timestamps.
  - Sensitive query string parameter redaction (`token`, `password`, `auth`).
  - Ignore filters for static asset probes (`*.map`, `*.css`, `*.ico`, `wp-admin/*`).
  - Automated background queue pruning jobs.
- 🔄 **Automatic Slug / URI Tracking**:
  - Automatically intercepts `Entry::EVENT_AFTER_SAVE` to detect slug/URI changes and generate 301 redirects, preserving search engine rankings.
- 🔗 **Redirect Chain & Loop Detection**:
  - Graph traversal identifies intermediate hops (`/a` &rarr; `/b` &rarr; `/c`) with one-click direct optimization.
  - Cycle detection prevents browser `ERR_TOO_MANY_REDIRECTS` loops before they happen.
- 🚀 **Short Links / Vanity URLs**:
  - Create clean marketing aliases (`/go/summer-sale`) with hit tracking.
- 📁 **CSV Import & Export**:
  - Flexible column header recognition.
  - Dry-run validation preview table before execution.
  - Formula injection mitigation against spreadsheet DDE exploits.
- 🛡️ **SSRF Defense**:
  - Health checks on external URLs block loopback, link-local metadata (`169.254.*`), and private RFC 1918 subnets.
- 📜 **Administrative Audit Trail**:
  - Comprehensive history of rule creations, updates, deletions, and CSV imports.
- ☁️ **Craft Cloud Ready**:
  - Stateless architecture, streaming temporary file buffers (`php://temp`), queue-based background workers.

---

## 📦 Requirements

- Craft CMS `^5.0.0`
- PHP `^8.2.0`
- MySQL 8.0+ or PostgreSQL 13+

---

## 🛠️ Installation

```bash
composer require abdulkadir/seo-redirect-manager
php craft plugin/install seo-redirect-manager
```

---

## 📖 CLI Commands

Run maintenance tasks and cleanup via Craft CLI or automated cron jobs:

```bash
# Prune old 404 error logs beyond retention days
php craft seo-redirect-manager/cleanup/not-found --days=30

# Verify redirect targets and destination health via queue
php craft seo-redirect-manager/check/destinations --limit=100
```

---

## 📄 License

This plugin is open-source software licensed under the [MIT license](LICENSE.md).
