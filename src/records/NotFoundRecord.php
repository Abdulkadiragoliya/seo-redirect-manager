<?php

namespace abdulkadir\seoredirectmanager\records;

use craft\db\ActiveRecord;
use craft\records\Site;
use yii\db\ActiveQueryInterface;
use abdulkadir\seoredirectmanager\db\Table;

/**
 * ActiveRecord for 404 missing request logs.
 *
 * @property int $id
 * @property int|null $siteId
 * @property string $path
 * @property string $pathNormalized
 * @property int $hitCount
 * @property string $firstSeen
 * @property string $lastSeen
 * @property string|null $referrer
 * @property string|null $userAgent
 * @property string $status
 * @property int|null $redirectId
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class NotFoundRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::NOT_FOUND;
    }

    /**
     * Associated site.
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'siteId']);
    }

    /**
     * Associated redirect created to resolve this 404.
     */
    public function getRedirect(): ActiveQueryInterface
    {
        return $this->hasOne(RedirectRecord::class, ['id' => 'redirectId']);
    }
}
