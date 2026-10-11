<?php

namespace abdulkadir\seoredirectmanager\records;

use craft\db\ActiveRecord;
use craft\records\Site;
use yii\db\ActiveQueryInterface;
use abdulkadir\seoredirectmanager\db\Table;

/**
 * ActiveRecord for Short Links.
 *
 * @property int $id
 * @property int|null $siteId
 * @property string $slug
 * @property string $destination
 * @property int $statusCode
 * @property bool $enabled
 * @property int $hitCount
 * @property string|null $lastHitDate
 * @property string|null $description
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class ShortLinkRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SHORT_LINKS;
    }

    /**
     * Associated site.
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'siteId']);
    }
}
