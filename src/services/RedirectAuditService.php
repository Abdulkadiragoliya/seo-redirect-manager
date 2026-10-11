<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use yii\base\Component;
use abdulkadir\seoredirectmanager\db\Table;
use abdulkadir\seoredirectmanager\models\AuditLog;

/**
 * Service recording and querying administrative audit trails.
 */
class RedirectAuditService extends Component
{
    /**
     * Records an administrative action to the audit trail.
     */
    public function logAction(string $action, string $recordType, ?int $recordId, string $summary, ?array $changes = null): void
    {
        try {
            $user = Craft::$app->getUser()->getIdentity();
            $userId = $user ? (int)$user->id : null;
            $ip = Craft::$app->getRequest()->getIsConsoleRequest() ? 'cli' : Craft::$app->getRequest()->getUserIP();

            Craft::$app->getDb()->createCommand()
                ->insert(Table::AUDIT, [
                    'userId' => $userId,
                    'action' => StringHelper::truncate($action, 50),
                    'recordType' => StringHelper::truncate($recordType, 50),
                    'recordId' => $recordId,
                    'summary' => $summary,
                    'changes' => $changes !== null ? Json::encode($changes) : null,
                    'ipAddress' => $ip ? StringHelper::truncate($ip, 45) : null,
                    'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                    'uid' => StringHelper::UUID(),
                ])
                ->execute();
        } catch (\Throwable $e) {
            Craft::error('Failed to write redirect audit log: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Get paginated audit logs.
     *
     * @return AuditLog[]
     */
    public function getAuditLogs(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        $query = (new Query())
            ->from(Table::AUDIT)
            ->limit($limit)
            ->offset($offset)
            ->orderBy('dateCreated DESC');

        if (!empty($criteria['search'])) {
            $s = trim($criteria['search']);
            $query->andWhere([
                'or',
                ['like', 'summary', $s],
                ['like', 'action', $s],
                ['like', 'recordType', $s],
            ]);
        }

        if (!empty($criteria['action'])) {
            $query->andWhere(['action' => $criteria['action']]);
        }

        if (!empty($criteria['recordType'])) {
            $query->andWhere(['recordType' => $criteria['recordType']]);
        }

        $rows = $query->all();
        $models = [];
        foreach ($rows as $row) {
            $m = new AuditLog();
            $m->setAttributes($row, false);
            $models[] = $m;
        }

        return $models;
    }

    /**
     * Total audit logs count.
     */
    public function getTotalAuditLogs(array $criteria = []): int
    {
        $query = (new Query())->from(Table::AUDIT);

        if (!empty($criteria['search'])) {
            $s = trim($criteria['search']);
            $query->andWhere([
                'or',
                ['like', 'summary', $s],
                ['like', 'action', $s],
            ]);
        }

        return (int)$query->count();
    }
}
