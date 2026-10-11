# Testing Guide

The plugin includes an automated standalone test runner capable of executing in local CLI and CI environments without external test dependencies.

## Running Tests

From the plugin root directory:

```bash
php tests/run.php
```

Or from the Craft project root:

```bash
php plugins/seo-redirect-manager/tests/run.php
```

## Test Coverage Overview

- **`NormalizationTest`**: Verifies path normalization, multiple slash reduction, unicode handling, and case handling.
- **`DestinationSafetyTest`**: Validates SSRF blocking on private subnets, loopback, cloud metadata IPs, and CRLF header injection.
- **`QueryPolicyTest`**: Tests `preserve`, `exact`, and `ignore` parameter transformations with existing query strings and URL fragments (`#hash`).
- **`NotFoundFilterTest`**: Asserts token and credential redaction on 404 query strings.
- **`CsvValidationTest`**: Verifies flexible column mapping and formula injection neutralization.
- **`RegexMatchTest`**: Confirms capture group substitution (`$1`, `$2`) and regex matching logic.
