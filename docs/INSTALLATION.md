# Installation & Configuration Guide

## Step 1: Composer Setup

Add the plugin to your project `composer.json` or install directly:

```bash
composer require abdulkadir/seo-redirect-manager
```

For local repository development, specify a path repository:

```json
"repositories": [
  {
    "type": "path",
    "url": "plugins/seo-redirect-manager"
  }
]
```

## Step 2: Plugin Installation

Install the plugin via Craft CLI:

```bash
php craft plugin/install seo-redirect-manager
```

Or visit the **Settings &rarr; Plugins** page in the Craft Control Panel and click **Install**.

## Step 3: Verifying Database Schema

The plugin automatically registers four relational database tables:
- `{{%seoredirect_redirects}}`
- `{{%seoredirect_not_found}}`
- `{{%seoredirect_short_links}}`
- `{{%seoredirect_audit}}`

Indexes are applied across source paths, status codes, enabled flags, and normalized URIs for high-throughput lookup performance.
