<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use yii\base\Component;
use abdulkadir\seoredirectmanager\db\Table;
use abdulkadir\seoredirectmanager\models\ShortLink;
use abdulkadir\seoredirectmanager\records\ShortLinkRecord;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Service managing Short Links and vanity URL aliases.
 */
class ShortLinkService extends Component
{
    /**
     * Get short link by slug and site.
     */
    public function getShortLinkBySlug(string $slug, ?int $siteId = null): ?ShortLink
    {
        $cleanSlug = trim($slug, '/');

        $query = (new Query())
            ->from(Table::SHORT_LINKS)
            ->where(['slug' => $cleanSlug, 'enabled' => true]);

        if ($siteId !== null) {
            $query->andWhere(['or', ['siteId' => $siteId], ['siteId' => null]]);
            $query->orderBy(new \yii\db\Expression('CASE WHEN siteId IS NOT NULL THEN 0 ELSE 1 END'));
        } else {
            $query->andWhere(['siteId' => null]);
        }

        $row = $query->one();
        if (!$row) {
            return null;
        }

        $model = new ShortLink();
        $model->setAttributes($row, false);
        return $model;
    }

    /**
     * Get short link by ID.
     */
    public function getShortLinkById(int $id): ?ShortLink
    {
        $record = ShortLinkRecord::findOne($id);
        if (!$record) return null;

        $model = new ShortLink();
        $model->setAttributes($record->getAttributes(), false);
        return $model;
    }

    /**
     * Saves a short link model.
     */
    public function saveShortLink(ShortLink $model, bool $runValidation = true): bool
    {
        $isNew = $model->id === null;
        $model->slug = trim($model->slug, '/');

        if ($runValidation && !$model->validate()) {
            return false;
        }

        // Uniqueness check
        $query = (new Query())
            ->from(Table::SHORT_LINKS)
            ->where(['slug' => $model->slug]);

        if ($model->siteId !== null) {
            $query->andWhere(['siteId' => $model->siteId]);
        } else {
            $query->andWhere(['siteId' => null]);
        }

        if (!$isNew) {
            $query->andWhere(['not', ['id' => $model->id]]);
        }

        if ($query->exists()) {
            $model->addError('slug', Craft::t('seo-redirect-manager', 'A short link with this slug already exists for this site scope.'));
            return false;
        }

        $record = $isNew ? new ShortLinkRecord() : ShortLinkRecord::findOne($model->id);
        if (!$record) return false;

        $record->siteId = $model->siteId;
        $record->slug = $model->slug;
        $record->destination = $model->destination;
        $record->statusCode = (int)$model->statusCode;
        $record->enabled = (bool)$model->enabled;
        $record->description = $model->description;

        $success = $record->save(false);

        if ($success) {
            $model->id = $record->id;
            $model->dateCreated = $record->dateCreated;
            $model->dateUpdated = $record->dateUpdated;

            SeoRedirectManager::getInstance()->audit->logAction(
                $isNew ? 'create' : 'update',
                'short_link',
                $model->id,
                sprintf('%s short link "%s" -> "%s"', $isNew ? 'Created' : 'Updated', $model->slug, $model->destination),
                ['slug' => $model->slug, 'destination' => $model->destination]
            );
        }

        return $success;
    }

    /**
     * Deletes a short link.
     */
    public function deleteShortLink(int $id): bool
    {
        $link = $this->getShortLinkById($id);
        if (!$link) return false;

        $record = ShortLinkRecord::findOne($id);
        if (!$record) return false;

        $deleted = (bool)$record->delete();
        if ($deleted) {
            SeoRedirectManager::getInstance()->audit->logAction(
                'delete',
                'short_link',
                $id,
                sprintf('Deleted short link "%s"', $link->slug),
                ['deleted' => $link->getAttributes()]
            );
        }

        return $deleted;
    }

    /**
     * Increment hit counter.
     */
    public function incrementHitCount(int $id): void
    {
        Craft::$app->getDb()->createCommand(
            'UPDATE ' . Table::SHORT_LINKS . ' SET hitCount = hitCount + 1, lastHitDate = :now WHERE id = :id',
            [':now' => Db::prepareDateForDb(new \DateTime()), ':id' => $id]
        )->execute();
    }

    /**
     * Get paginated short links.
     *
     * @return ShortLink[]
     */
    public function getShortLinks(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        $query = (new Query())
            ->from(Table::SHORT_LINKS)
            ->limit($limit)
            ->offset($offset)
            ->orderBy($criteria['orderBy'] ?? 'dateUpdated DESC');

        if (!empty($criteria['search'])) {
            $s = trim($criteria['search']);
            $query->andWhere([
                'or',
                ['like', 'slug', $s],
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

        $rows = $query->all();
        $models = [];
        foreach ($rows as $row) {
            $m = new ShortLink();
            $m->setAttributes($row, false);
            $models[] = $m;
        }

        return $models;
    }

    /**
     * Total short links matching criteria.
     */
    public function getTotalShortLinks(array $criteria = []): int
    {
        $query = (new Query())
            ->from(Table::SHORT_LINKS);

        if (!empty($criteria['search'])) {
            $s = trim($criteria['search']);
            $query->andWhere([
                'or',
                ['like', 'slug', $s],
                ['like', 'destination', $s],
            ]);
        }

        return (int)$query->count();
    }
}
