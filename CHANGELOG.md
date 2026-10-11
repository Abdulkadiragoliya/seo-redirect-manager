# Changelog

All notable changes to **SEO Redirect Manager** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
## [1.0.3] - 2026-10-11
- Fixed composer issue
## [1.0.2] - 2026-10-11

### Fixed
- Fixed Craft CMS 5 entry save failure (`getOldAttribute` unknown method) when editors rename entry slugs.
- Added `Entry::EVENT_BEFORE_SAVE` pre-save URI snapshot to reliably compare old and new public URIs and create automated 301 redirects.
- Fixed CSV preview dry-run table rendering, validation counters, and action form controls.
- Renamed Audit history filter query parameter from reserved `action` to `auditAction` to prevent Craft router 404 conflicts.
- Added session recovery for CSV import preview reloads.

## [1.0.1] - 2026-10-11

### Changed
- Updated README documentation with hero banner.
- Excluded internal development status from production release.
- Cleaned composer.json for automated Packagist release management.

## [1.0.0] - 2026-10-11

### Added
- Multi-tier redirect resolution (exact paths, regex patterns, vanity short links).
- Zero runtime overhead 404 exception listener (`ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION`).
- Bounded 404 monitoring with automated aggregation and sensitive query parameter redaction.
- Automatic 301 rule creation on Craft entry URI/slug updates (`Entry::EVENT_AFTER_SAVE`).
- Redirect chain graph traversal with one-click direct optimization.
- Predictive and retroactive redirect cycle / loop detection.
- SSRF-safe destination health checking with private IP and cloud metadata protection.
- High-performance streaming CSV import with dry-run validation preview and spreadsheet formula injection safeguards.
- Administrative audit log tracking before/after diffs.
- Control Panel interface with dashboard metrics, interactive tables, and search filters.
- Native Craft 5 CLI commands for background cleanup and destination health verification.
- Comprehensive automated test suite with 100% assertion pass rate.
