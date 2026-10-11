<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use yii\base\Component;
use abdulkadir\seoredirectmanager\models\CsvImportRow;
use abdulkadir\seoredirectmanager\models\Redirect;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Service managing CSV parsing, validation, dry-run previews, and execution.
 */
class CsvImportService extends Component
{
    /**
     * Parses and validates raw CSV content without database writes (dry-run preview).
     */
    public function parseAndValidate(string $csvContent, ?int $targetSiteId = null): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines)) {
            return [
                'success' => false,
                'message' => Craft::t('seo-redirect-manager', 'Uploaded CSV file is empty.'),
                'rows' => [],
                'totalRows' => 0,
                'validRows' => 0,
                'errorRows' => 0,
                'duplicateRows' => 0,
            ];
        }

        // Header detection
        $firstLine = array_shift($lines);
        $headers = str_getcsv($firstLine);
        $headerMap = $this->mapHeaders($headers);

        if (!isset($headerMap['source']) || !isset($headerMap['destination'])) {
            return [
                'success' => false,
                'message' => Craft::t('seo-redirect-manager', 'CSV must contain at least "source" and "destination" columns.'),
                'rows' => [],
                'totalRows' => 0,
                'validRows' => 0,
                'errorRows' => 0,
                'duplicateRows' => 0,
            ];
        }

        $parsedRows = [];
        $seenSources = [];
        $validCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;
        $lineNum = 1;

        foreach ($lines as $line) {
            $lineNum++;
            if (empty(trim($line))) continue;

            $data = str_getcsv($line);
            $rowModel = new CsvImportRow();
            $rowModel->lineNumber = $lineNum;

            $rowModel->source = trim($data[$headerMap['source']] ?? '');
            $rowModel->destination = trim($data[$headerMap['destination']] ?? '');

            if (isset($headerMap['statusCode']) && !empty($data[$headerMap['statusCode']])) {
                $rowModel->statusCode = (int)$data[$headerMap['statusCode']];
            } else {
                $rowModel->statusCode = 301;
            }

            if (isset($headerMap['matchType']) && !empty($data[$headerMap['matchType']])) {
                $rowModel->matchType = strtolower(trim($data[$headerMap['matchType']])) === 'regex' ? 'regex' : 'exact';
            }

            if (isset($headerMap['queryPolicy']) && !empty($data[$headerMap['queryPolicy']])) {
                $qp = strtolower(trim($data[$headerMap['queryPolicy']]));
                $rowModel->queryPolicy = in_array($qp, ['preserve', 'exact', 'ignore'], true) ? $qp : 'preserve';
            }

            if (isset($headerMap['enabled']) && $data[$headerMap['enabled']] !== '') {
                $rowModel->enabled = filter_var($data[$headerMap['enabled']], FILTER_VALIDATE_BOOLEAN);
            }

            if (isset($headerMap['priority']) && !empty($data[$headerMap['priority']])) {
                $rowModel->priority = (int)$data[$headerMap['priority']];
            }

            if (isset($headerMap['description'])) {
                $rowModel->description = trim($data[$headerMap['description']]);
            }

            // Site determination
            if (isset($headerMap['site']) && !empty($data[$headerMap['site']])) {
                $siteHandle = trim($data[$headerMap['site']]);
                $siteObj = Craft::$app->getSites()->getSiteByHandle($siteHandle);
                if ($siteObj) {
                    $rowModel->siteId = $siteObj->id;
                    $rowModel->siteHandle = $siteHandle;
                } else {
                    $rowModel->isValid = false;
                    $rowModel->errors[] = sprintf('Site handle "%s" does not exist.', $siteHandle);
                }
            } else {
                $rowModel->siteId = $targetSiteId;
            }

            // Validation checks
            if (empty($rowModel->source)) {
                $rowModel->isValid = false;
                $rowModel->errors[] = 'Source path is required.';
            }

            if (empty($rowModel->destination)) {
                $rowModel->isValid = false;
                $rowModel->errors[] = 'Destination path or URL is required.';
            }

            if (!in_array($rowModel->statusCode, [301, 302, 307, 308], true)) {
                $rowModel->isValid = false;
                $rowModel->errors[] = 'Status code must be 301, 302, 307, or 308.';
            }

            // Self-loop check
            if ($rowModel->matchType === 'exact' && strcasecmp(trim($rowModel->source, '/'), trim($rowModel->destination, '/')) === 0) {
                $rowModel->isValid = false;
                $rowModel->errors[] = 'Source and destination cannot be identical (self-loop).';
            }

            // In-CSV duplicate detection
            $normKey = ($rowModel->siteId ?? 'all') . ':' . trim(mb_strtolower($rowModel->source, 'UTF-8'), '/');
            if (isset($seenSources[$normKey])) {
                $rowModel->isValid = false;
                $rowModel->errors[] = sprintf('Duplicate source in CSV (already defined on line %d).', $seenSources[$normKey]);
                $duplicateCount++;
            } else {
                $seenSources[$normKey] = $lineNum;
            }

            if ($rowModel->isValid) {
                $validCount++;
            } else {
                $errorCount++;
                $rowModel->errorMessage = implode(' ', $rowModel->errors);
            }

            $parsedRows[] = $rowModel;
        }

        return [
            'success' => true,
            'rows' => $parsedRows,
            'totalRows' => count($parsedRows),
            'validRows' => $validCount,
            'errorRows' => $errorCount,
            'duplicateRows' => $duplicateCount,
        ];
    }

    /**
     * Executes validated CSV import into database.
     */
    public function executeImport(array $rows, string $conflictPolicy = 'skip', ?int $fallbackSiteId = null): array
    {
        $redirectService = SeoRedirectManager::getInstance()->redirects;
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows as $row) {
            /** @var CsvImportRow $row */
            if (!$row->isValid) {
                $failed++;
                continue;
            }

            $siteId = $row->siteId ?? $fallbackSiteId;
            $normalizedSource = trim(mb_strtolower($row->source, 'UTF-8'), '/');

            $existing = $redirectService->findDuplicate($normalizedSource, $siteId);

            if ($existing) {
                if ($conflictPolicy === 'overwrite') {
                    $existing->destination = $row->destination;
                    $existing->statusCode = $row->statusCode;
                    $existing->matchType = $row->matchType;
                    $existing->queryPolicy = $row->queryPolicy;
                    $existing->enabled = $row->enabled;
                    $existing->priority = $row->priority;
                    if (!empty($row->description)) {
                        $existing->description = $row->description;
                    }
                    if ($redirectService->saveRedirect($existing, true)) {
                        $updated++;
                    } else {
                        $failed++;
                    }
                } else {
                    $skipped++;
                }
                continue;
            }

            // Create new rule
            $redirect = new Redirect();
            $redirect->siteId = $siteId;
            $redirect->source = $row->source;
            $redirect->sourceNormalized = $normalizedSource;
            $redirect->destination = $row->destination;
            $redirect->statusCode = $row->statusCode;
            $redirect->matchType = $row->matchType;
            $redirect->queryPolicy = $row->queryPolicy;
            $redirect->enabled = $row->enabled;
            $redirect->priority = $row->priority;
            $redirect->description = $row->description;

            if ($redirectService->saveRedirect($redirect, true)) {
                $imported++;
            } else {
                $failed++;
            }
        }

        SeoRedirectManager::getInstance()->audit->logAction(
            'import',
            'redirect',
            null,
            sprintf('CSV Import executed: %d created, %d updated, %d skipped, %d failed', $imported, $updated, $skipped, $failed),
            ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'failed' => $failed]
        );

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * Map header names case-insensitively.
     */
    public function mapHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            $clean = strtolower(trim($h));
            if (in_array($clean, ['source', 'from', 'source url', 'source_url', 'path', 'from url'], true)) {
                $map['source'] = $idx;
            } elseif (in_array($clean, ['destination', 'to', 'destination url', 'destination_url', 'target', 'to url'], true)) {
                $map['destination'] = $idx;
            } elseif (in_array($clean, ['status', 'statuscode', 'status_code', 'code', 'http status'], true)) {
                $map['statusCode'] = $idx;
            } elseif (in_array($clean, ['type', 'matchtype', 'match_type', 'match type', 'match'], true)) {
                $map['matchType'] = $idx;
            } elseif (in_array($clean, ['site', 'site_handle', 'sitehandle'], true)) {
                $map['site'] = $idx;
            } elseif (in_array($clean, ['enabled', 'active', 'status_enabled'], true)) {
                $map['enabled'] = $idx;
            } elseif (in_array($clean, ['querypolicy', 'query_policy', 'query'], true)) {
                $map['queryPolicy'] = $idx;
            } elseif (in_array($clean, ['priority', 'weight', 'order'], true)) {
                $map['priority'] = $idx;
            } elseif (in_array($clean, ['description', 'note', 'label', 'comment'], true)) {
                $map['description'] = $idx;
            }
        }
        return $map;
    }
}
