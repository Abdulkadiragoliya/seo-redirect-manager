# Security & Threat Model

## 1. Zero Runtime Vulnerability Surface

The redirect resolver does not execute on normal web requests. It is bound solely to `craft\web\ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION`. Any request handled normally by Craft CMS, custom controllers, or static files bypasses this plugin entirely.

## 2. Server-Side Request Forgery (SSRF) Defense

Destination health verification and external checks enforce strict constraints via `RedirectValidator`:
- **Private Subnets Blocked**: RFC 1918 ranges (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`) are blocked using `FILTER_FLAG_NO_PRIV_RANGE`.
- **Reserved Ranges Blocked**: Loopback (`127.0.0.0/8`, `::1`), link-local, and reserved ranges are blocked via `FILTER_FLAG_NO_RES_RANGE`.
- **Cloud Metadata Blocked**: Explicit defense against AWS / GCP / Azure metadata endpoints (`169.254.169.254`).
- **Protocols Restricted**: Only standard `http` and `https` schemes are permitted (blocking `file://`, `phar://`, `gopher://`).

## 3. CSV Formula Injection (DDE) Mitigation

When exporting redirect data via `CsvExportService`, any cell starting with dangerous spreadsheet formula triggers (`=`, `+`, `-`, `@`, `\t`, `\r`) is automatically escaped with a leading single quote (`'`). This neutralizes malicious formula execution in Microsoft Excel, LibreOffice Calc, and Google Sheets.

## 4. Header Injection Defense

Destinations are checked for carriage return (`\r`), line feed (`\n`), and null byte (`\0`) characters to prevent HTTP response splitting and response header injection.

## 5. Audit Logging

Administrative modifications (create, update, delete, bulk actions, CSV imports) record user attribution, timestamp, IP address, and JSON diffs to the `{{%seoredirect_audit}}` log table.
