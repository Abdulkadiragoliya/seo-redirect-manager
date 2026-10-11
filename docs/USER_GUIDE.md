# User & Administrator Guide

## Redirect Matching Precedence

When a request results in a 404 exception, the resolver evaluates candidate matches in strict order:

1. **Short Links**: Matching the configured vanity prefix (default `/go/`).
2. **Exact Matches (Site-Specific)**: Strict or normalized exact path rules belonging to the active site.
3. **Exact Matches (Global)**: Fallback rules that apply across all multi-site installations.
4. **Regular Expressions (Regex)**: Dynamic pattern rules sorted by `priority DESC` and `id ASC`.

## Query String Policies

When defining a redirect, choose how query parameters are processed:

- **Preserve (Default)**: Any incoming parameters (e.g. `?utm_source=fb`) are appended to the destination URL.
- **Exact**: The redirect only triggers if the request's query string matches the rule's query string exactly.
- **Ignore**: Incoming parameters are discarded, directing visitors to the clean destination path.

## Managing 404 Logs

- The **404 Monitor** aggregates repeated hits to the same missing path into a single record with counters.
- Click **Redirect** on any 404 row to pre-fill a redirect rule and resolve the issue.
- Sensitive query parameters (such as `token`, `password`, `key`) are automatically redacted before logging to prevent credential leakage.
- Use **Ignore** on scanner spam (e.g. `wp-login.php`) to filter noise.

## Optimizing Redirect Chains

Under **Reports &rarr; Chains**, the plugin scans your redirect graph to find chains like:
`/old-link` &rarr; `/intermediate` &rarr; `/final-destination`

Click **Optimize to Direct Route** to update the starting rule to point directly to `/final-destination`, recovering link equity and reducing response latency.
