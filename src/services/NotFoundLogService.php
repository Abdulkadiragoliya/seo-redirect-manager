<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use yii\base\Component;
use abdulkadir\seoredirectmanager\db\Table;
use abdulkadir\seoredirectmanager\models\NotFoundLog;
use abdulkadir\seoredirectmanager\records\NotFoundRecord;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Service managing 404 missing request monitoring, aggregation, and cleanup.
 */
class NotFoundLogService extends Component
{
    /**
     * Logs or aggregates a 404 request.
     */
    public function log404(string $uri, ?int $siteId = null, ?string $referrer = null, ?string $userAgent = null): bool
    {
        $settings = SeoRedirectManager::getInstance()->getSettings();
        if (!$settings->enableNotFoundMonitoring) {
            return false;
        }

        if ($this->isIgnoredPath($uri)) {
            return false;
        }

        $siteId = $siteId ?? (Craft::$app->getSites()->getCurrentSite()->id ?? null);

        // Sanitize path and strip sensitive query parameters
        $parts = parse_url($uri);
        $pathOnly = $parts['path'] ?? $uri;
        $query = $parts['query'] ?? '';

        if ($settings->stripSensitiveQueryParams && !empty($query)) {
            $query = $this->sanitizeQueryString($query);
        }

        $cleanUri = $pathOnly . (!empty($query) ? '?' . $query : '');
        $pathNormalized = trim(mb_strtolower($pathOnly, 'UTF-8'), '/');

        // Bounded aggregation: Check if this (pathNormalized, siteId) is already logged
        $existing = (new Query())
            ->from(Table::NOT_FOUND)
            ->where(['pathNormalized' => $pathNormalized])
            ->andWhere($siteId !== null ? ['siteId' => $siteId] : ['siteId' => null])
            ->one();

        $now = Db::prepareDateForDb(new \DateTime());

        if ($existing) {
            Craft::$app->getDb()->createCommand()
                ->update(
                    Table::NOT_FOUND,
                    [
                        'hitCount' => (int)$existing['hitCount'] + 1,
                        'lastSeen' => $now,
                        'referrer' => $referrer ? StringHelper::truncate($referrer, 500) : $existing['referrer'],
                        'userAgent' => $userAgent ? StringHelper::truncate($userAgent, 255) : $existing['userAgent'],
                        'dateUpdated' => $now,
                    ],
                    ['id' => $existing['id']]
                )
                ->execute();
            return true;
        }

        // Insert new record
        return (bool)Craft::$app->getDb()->createCommand()
            ->insert(Table::NOT_FOUND, [
                'siteId' => $siteId,
                'path' => StringHelper::truncate($cleanUri, 1000),
                'pathNormalized' => StringHelper::truncate($pathNormalized, 1000),
                'hitCount' => 1,
                'firstSeen' => $now,
                'lastSeen' => $now,
                'referrer' => $referrer ? StringHelper::truncate($referrer, 500) : null,
                'userAgent' => $userAgent ? StringHelper::truncate($userAgent, 255) : null,
                'status' => 'unresolved',
                'redirectId' => null,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ])
            ->execute();
    }

    /**
     * Checks if a path matches ignored 404 filters (e.g. assets, admin, health checks).
     */
    public function isIgnoredPath(string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        $path = '/' . ltrim($path, '/');

        $settings = SeoRedirectManager::getInstance()->getSettings();
        $patterns = $settings->getIgnoredPatternsList();

        foreach ($patterns as $pattern) {
            // Wildcard pattern matching
            if (str_starts_with($pattern, '*.')) {
                $ext = substr($pattern, 1);
                if (str_ends_with(strtolower($path), strtolower($ext))) {
                    return true;
                }
            } elseif (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($path, $prefix . '/') || $path === $prefix) {
                    return true;
                }
            } elseif (str_contains($pattern, '*')) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
                if (@preg_match($regex, $path)) {
                    return true;
                }
            } else {
                if (strcasecmp($path, $pattern) === 0 || strcasecmp(rtrim($path, '/'), rtrim($pattern, '/')) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Removes sensitive keys (tokens, passwords, session ids) from query strings.
     */
    public function sanitizeQueryString(string $query): string
    {
        parse_str($query, $params);
        $sensitiveKeys = ['token', 'auth', 'key', 'password', 'pwd', 'secret', 'session', 'sessid', 'phpsessid'];

        foreach ($params as $k => $v) {
            if (in_array(strtolower($k), $sensitiveKeys, true)) {
                $params[$k] = '[redacted]';
            }
        }

        return http_build_query($params);
    }

    /**
     * Get paginated 404 logs.
     *
     * @return NotFoundLog[]
     */
    public function getLogs(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        $query = $this->createLogQuery($criteria);
        $query->limit($limit)->offset($offset);

        $orderBy = $criteria['orderBy'] ?? 'lastSeen DESC';
        $query->orderBy($orderBy);

        $rows = $query->all();
        $models = [];
        foreach ($rows as $row) {
            $model = new NotFoundLog();
            $model->setAttributes($row, false);
            $models[] = $model;
        }

        return $models;
    }

    /**
     * Total count matching criteria.
     */
    public function getTotalLogs(array $criteria = []): int
    {
        $query = $this->createLogQuery($criteria);
        return (int)$query->count();
    }

    /**
     * Mark single 404 log status.
     */
    public function markStatus(int $id, string $status): bool
    {
        return (bool)Craft::$app->getDb()->createCommand()
            ->update(Table::NOT_FOUND, [
                'status' => $status,
                'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
            ], ['id' => $id])
            ->execute();
    }

    /**
     * Bulk mark status.
     */
    public function bulkMarkStatus(array $ids, string $status): int
    {
        if (empty($ids)) return 0;
        return Craft::$app->getDb()->createCommand()
            ->update(Table::NOT_FOUND, [
                'status' => $status,
                'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
            ], ['id' => $ids])
            ->execute();
    }

    /**
     * Clears all 404 records.
     */
    public function clearAll(): int
    {
        return Craft::$app->getDb()->createCommand()
            ->delete(Table::NOT_FOUND)
            ->execute();
    }

    /**
     * Bulk delete 404 records.
     */
    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) return 0;
        return Craft::$app->getDb()->createCommand()
            ->delete(Table::NOT_FOUND, ['id' => $ids])
            ->execute();
    }

    /**
     * Mark 404 as resolved by associating it with a created redirect ID.
     */
    public function resolveWithRedirect(int $logId, int $redirectId): bool
    {
        return (bool)Craft::$app->getDb()->createCommand()
            ->update(Table::NOT_FOUND, [
                'status' => 'resolved',
                'redirectId' => $redirectId,
                'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
            ], ['id' => $logId])
            ->execute();
    }

    /**
     * Prune logs older than $days retention threshold.
     */
    public function cleanupOldLogs(int $days): int
    {
        if ($days <= 0) return 0;
        $threshold = (new \DateTime())->modify("-{$days} days");
        $thresholdStr = Db::prepareDateForDb($threshold);

        return Craft::$app->getDb()->createCommand()
            ->delete(Table::NOT_FOUND, ['<', 'lastSeen', $thresholdStr])
            ->execute();
    }

    /**
     * Build log query.
     */
    protected function createLogQuery(array $criteria = []): Query
    {
        $query = (new Query())
            ->select(['*'])
            ->from(Table::NOT_FOUND);

        if (!empty($criteria['search'])) {
            $s = trim($criteria['search']);
            $query->andWhere([
                'or',
                ['like', 'path', $s],
                ['like', 'referrer', $s],
            ]);
        }

        if (isset($criteria['siteId']) && $criteria['siteId'] !== '') {
            if ($criteria['siteId'] === 'null' || $criteria['siteId'] === null) {
                $query->andWhere(['siteId' => null]);
            } else {
                $query->andWhere(['siteId' => (int)$criteria['siteId']]);
            }
        }

        if (!empty($criteria['status'])) {
            $query->andWhere(['status' => $criteria['status']]);
        }

        return $query;
    }
}
