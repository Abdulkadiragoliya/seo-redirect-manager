<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
use yii\base\Component;
use abdulkadir\seoredirectmanager\db\Table;

/**
 * Service generating streaming CSV exports with formula injection safeguards.
 */
class CsvExportService extends Component
{
    /**
     * Exports redirect rules to CSV string.
     */
    public function exportToCsv(array $criteria = []): string
    {
        $handle = fopen('php://temp', 'r+');

        // Header row
        fputcsv($handle, [
            'source',
            'destination',
            'statusCode',
            'matchType',
            'site',
            'enabled',
            'queryPolicy',
            'priority',
            'description',
        ]);

        $query = (new Query())
            ->from(Table::REDIRECTS)
            ->orderBy('id ASC');

        if (isset($criteria['siteId']) && $criteria['siteId'] !== '') {
            if ($criteria['siteId'] === 'null' || $criteria['siteId'] === null) {
                $query->andWhere(['siteId' => null]);
            } else {
                $query->andWhere(['siteId' => (int)$criteria['siteId']]);
            }
        }

        if (!empty($criteria['statusCode'])) {
            $query->andWhere(['statusCode' => (int)$criteria['statusCode']]);
        }

        if (isset($criteria['enabled']) && $criteria['enabled'] !== '') {
            $query->andWhere(['enabled' => (bool)$criteria['enabled']]);
        }

        // Cache site handles
        $sites = Craft::$app->getSites()->getAllSites();
        $siteMap = [];
        foreach ($sites as $site) {
            $siteMap[$site->id] = $site->handle;
        }

        foreach ($query->each() as $row) {
            $siteHandle = $row['siteId'] !== null ? ($siteMap[$row['siteId']] ?? '') : '';

            fputcsv($handle, [
                $this->sanitizeFormulaInjection($row['source']),
                $this->sanitizeFormulaInjection($row['destination']),
                $row['statusCode'],
                $row['matchType'],
                $siteHandle,
                $row['enabled'] ? '1' : '0',
                $row['queryPolicy'],
                $row['priority'],
                $this->sanitizeFormulaInjection($row['description'] ?? ''),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Sanitizes values against CSV/Spreadsheet formula injection (DDE attacks).
     * If value begins with =, +, -, @, \t, or \r, prefix with a single quote.
     */
    public function sanitizeFormulaInjection(?string $val): string
    {
        if ($val === null || $val === '') {
            return '';
        }

        $firstChar = substr($val, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $val;
        }

        return $val;
    }
}
