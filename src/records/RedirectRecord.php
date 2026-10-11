<?php

namespace abdulkadir\seoredirectmanager\records;

use craft\db\ActiveRecord;
use craft\records\Site;
use craft\records\User;
use yii\db\ActiveQueryInterface;
use abdulkadir\seoredirectmanager\db\Table;

/**
 * ActiveRecord for Redirect rules.
 *
 * @property int $id
 * @property int|null $siteId
 * @property string $source
 * @property string $sourceNormalized
 * @property string $destination
 * @property int $statusCode
 * @property string $matchType
 * @property string $queryPolicy
 * @property bool $caseSensitive
 * @property bool $stripTrailingSlash
 * @property bool $enabled
 * @property int $priority
 * @property int $hitCount
 * @property string|null $lastHitDate
 * @property string|null $description
 * @property bool $isAuto
 * @property int|null $elementId
 * @property int|null $creatorId
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class RedirectRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::REDIRECTS;
    }

    /**
     * Get associated Craft site.
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'siteId']);
    }

    /**
     * Get rule creator.
     */
    public function getCreator(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'creatorId']);
    }
}
