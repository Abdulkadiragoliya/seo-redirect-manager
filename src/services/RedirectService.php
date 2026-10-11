<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use yii\base\Component;
use abdulkadir\seoredirectmanager\db\Table;
use abdulkadir\seoredirectmanager\models\Redirect;
use abdulkadir\seoredirectmanager\records\RedirectRecord;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Service managing Redirect rules CRUD, querying, and bulk actions.
 */
class RedirectService extends Component
{
    /**
     * Get redirect model by ID.
     */
    public function getRedirectById(int $id): ?Redirect
    {
        $record = RedirectRecord::findOne($id);
        if (!$record) {
            return null;
        }

        return $this->populateModelFromRecord($record);
    }

    /**
     * Saves a redirect rule.
     */
    public function saveRedirect(Redirect $redirect, bool $runValidation = true): bool
    {
        $isNew = $redirect->id === null;

        // Auto compute normalized source
        $redirect->sourceNormalized = $redirect->getComputedNormalizedSource();

        if ($runValidation && !$redirect->validate()) {
            Craft::info('Redirect rule failed validation: ' . json_encode($redirect->getErrors()), __METHOD__);
            return false;
        }

        $record = $isNew ? new RedirectRecord() : RedirectRecord::findOne($redirect->id);
        if (!$record) {
            $redirect->addError('id', Craft::t('seo-redirect-manager', 'Redirect record not found.'));
            return false;
        }

        $oldAttributes = $isNew ? [] : $record->getAttributes();

        $record->siteId = $redirect->siteId;
        $record->source = $redirect->source;
        $record->sourceNormalized = $redirect->sourceNormalized;
        $record->destination = $redirect->destination;
        $record->statusCode = (int)$redirect->statusCode;
        $record->matchType = $redirect->matchType;
        $record->queryPolicy = $redirect->queryPolicy;
        $record->caseSensitive = (bool)$redirect->caseSensitive;
        $record->stripTrailingSlash = (bool)$redirect->stripTrailingSlash;
        $record->enabled = (bool)$redirect->enabled;
        $record->priority = (int)$redirect->priority;
        $record->description = $redirect->description;
        $record->isAuto = (bool)$redirect->isAuto;
        $record->elementId = $redirect->elementId;
        if ($isNew && Craft::$app->getUser()->getIdentity()) {
            $record->creatorId = (int)Craft::$app->getUser()->getId();
        }

        $success = $record->save(false);

        if ($success) {
            $redirect->id = $record->id;
            $redirect->dateCreated = $record->dateCreated;
            $redirect->dateUpdated = $record->dateUpdated;

            // Audit Trail
            $action = $isNew ? 'create' : 'update';
            $summary = $isNew
                ? sprintf('Created %s redirect from "%s" to "%s"', $redirect->matchType, $redirect->source, $redirect->destination)
                : sprintf('Updated redirect #%d ("%s" -> "%s")', $redirect->id, $redirect->source, $redirect->destination);

            SeoRedirectManager::getInstance()->audit->logAction(
                $action,
                'redirect',
                $redirect->id,
                $summary,
                $isNew ? ['new' => $record->getAttributes()] : ['old' => $oldAttributes, 'new' => $record->getAttributes()]
            );
        }

        return $success;
    }

    /**
     * Deletes a redirect rule by ID.
     */
    public function deleteRedirect(int $id): bool
    {
        $redirect = $this->getRedirectById($id);
        if (!$redirect) {
            return false;
        }

        $record = RedirectRecord::findOne($id);
        if (!$record) {
            return false;
        }

        $deleted = (bool)$record->delete();

        if ($deleted) {
            SeoRedirectManager::getInstance()->audit->logAction(
                'delete',
                'redirect',
                $id,
                sprintf('Deleted %s redirect from "%s" to "%s"', $redirect->matchType, $redirect->source, $redirect->destination),
                ['deleted' => $redirect->getAttributes()]
            );
        }

        return $deleted;
    }

    /**
     * Get paginated and filtered redirect rules.
     *
     * @param array $criteria Search, siteId, statusCode, enabled, matchType
     * @return Redirect[]
     */
    public function getRedirects(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        $query = $this->createRedirectQuery($criteria);
        $query->limit($limit)->offset($offset);

        // Sorting
        $orderBy = $criteria['orderBy'] ?? 'dateUpdated DESC';
        $query->orderBy($orderBy);

        $records = $query->all();
        $models = [];
        foreach ($records as $record) {
            $model = new Redirect();
            $model->setAttributes($record, false);
            $models[] = $model;
        }

        return $models;
    }

    /**
     * Get total count matching criteria.
     */
    public function getTotalRedirects(array $criteria = []): int
    {
        $query = $this->createRedirectQuery($criteria);
        return (int)$query->count();
    }

    /**
     * Bulk enable rules.
     */
    public function bulkEnable(array $ids): int
    {
        if (empty($ids)) return 0;
        $count = Craft::$app->getDb()->createCommand()
            ->update(Table::REDIRECTS, ['enabled' => true, 'dateUpdated' => Db::prepareDateForDb(new \DateTime())], ['id' => $ids])
            ->execute();

        SeoRedirectManager::getInstance()->audit->logAction(
            'bulk_enable',
            'redirect',
            null,
            sprintf('Bulk enabled %d redirect rule(s)', $count),
            ['ids' => $ids]
        );

        return $count;
    }

    /**
     * Bulk disable rules.
     */
    public function bulkDisable(array $ids): int
    {
        if (empty($ids)) return 0;
        $count = Craft::$app->getDb()->createCommand()
            ->update(Table::REDIRECTS, ['enabled' => false, 'dateUpdated' => Db::prepareDateForDb(new \DateTime())], ['id' => $ids])
            ->execute();

        SeoRedirectManager::getInstance()->audit->logAction(
            'bulk_disable',
            'redirect',
            null,
            sprintf('Bulk disabled %d redirect rule(s)', $count),
            ['ids' => $ids]
        );

        return $count;
    }

    /**
     * Bulk delete rules.
     */
    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) return 0;
        $count = Craft::$app->getDb()->createCommand()
            ->delete(Table::REDIRECTS, ['id' => $ids])
            ->execute();

        SeoRedirectManager::getInstance()->audit->logAction(
            'bulk_delete',
            'redirect',
            null,
            sprintf('Bulk deleted %d redirect rule(s)', $count),
            ['ids' => $ids]
        );

        return $count;
    }

    /**
     * Find duplicate rule with same normalized source and site scope.
     */
    public function findDuplicate(string $sourceNormalized, ?int $siteId, ?int $excludeId = null): ?Redirect
    {
        $query = (new Query())
            ->from(Table::REDIRECTS)
            ->where(['sourceNormalized' => $sourceNormalized]);

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        } else {
            $query->andWhere(['siteId' => null]);
        }

        if ($excludeId !== null) {
            $query->andWhere(['not', ['id' => $excludeId]]);
        }

        $row = $query->one();
        if (!$row) {
            return null;
        }

        $model = new Redirect();
        $model->setAttributes($row, false);
        return $model;
    }

    /**
     * Increment redirect hit counter.
     */
    public function recordHit(int $id): void
    {
        Craft::$app->getDb()->createCommand(
            'UPDATE ' . Table::REDIRECTS . ' SET hitCount = hitCount + 1, lastHitDate = :now WHERE id = :id',
            [':now' => Db::prepareDateForDb(new \DateTime()), ':id' => $id]
        )->execute();
    }

    /**
     * Build query from criteria.
     */
    protected function createRedirectQuery(array $criteria = []): Query
    {
        $query = (new Query())
            ->select(['*'])
            ->from(Table::REDIRECTS);

        if (!empty($criteria['search'])) {
            $s = trim($criteria['search']);
            $query->andWhere([
                'or',
                ['like', 'source', $s],
                ['like', 'destination', $s],
                ['like', 'description', $s],
            ]);
        }

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

        if (!empty($criteria['matchType'])) {
            $query->andWhere(['matchType' => $criteria['matchType']]);
        }

        if (isset($criteria['enabled']) && $criteria['enabled'] !== '') {
            $query->andWhere(['enabled' => (bool)$criteria['enabled']]);
        }

        return $query;
    }

    /**
     * Populates model from ActiveRecord.
     */
    protected function populateModelFromRecord(RedirectRecord $record): Redirect
    {
        $model = new Redirect();
        $model->setAttributes($record->getAttributes(), false);
        return $model;
    }
}
