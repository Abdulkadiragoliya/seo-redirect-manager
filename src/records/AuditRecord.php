<?php

namespace abdulkadir\seoredirectmanager\records;

use craft\db\ActiveRecord;
use craft\records\User;
use yii\db\ActiveQueryInterface;
use abdulkadir\seoredirectmanager\db\Table;

/**
 * ActiveRecord for administrative audit logs.
 *
 * @property int $id
 * @property int|null $userId
 * @property string $action
 * @property string $recordType
 * @property int|null $recordId
 * @property string $summary
 * @property string|null $changes
 * @property string|null $ipAddress
 * @property string $dateCreated
 * @property string $uid
 */
class AuditRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::AUDIT;
    }

    /**
     * Associated user actor.
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
