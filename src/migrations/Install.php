<?php

namespace abdulkadir\seoredirectmanager\migrations;

use Craft;
use craft\db\Migration;
use abdulkadir\seoredirectmanager\db\Table;

/**
 * Installation migration for SEO Redirect Manager.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->safeDown();
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(Table::AUDIT);
        $this->dropTableIfExists(Table::NOT_FOUND);
        $this->dropTableIfExists(Table::SHORT_LINKS);
        $this->dropTableIfExists(Table::REDIRECTS);

        return true;
    }

    /**
     * Creates database tables.
     */
    protected function createTables(): void
    {
        // 1. Redirect Rules Table
        if (!$this->db->tableExists(Table::REDIRECTS)) {
            $this->createTable(Table::REDIRECTS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->null(),
                'source' => $this->string(500)->notNull(),
                'sourceNormalized' => $this->string(500)->notNull(),
                'destination' => $this->text()->notNull(),
                'statusCode' => $this->smallInteger()->notNull()->defaultValue(301),
                'matchType' => $this->string(20)->notNull()->defaultValue('exact'),
                'queryPolicy' => $this->string(20)->notNull()->defaultValue('preserve'),
                'caseSensitive' => $this->boolean()->notNull()->defaultValue(false),
                'stripTrailingSlash' => $this->boolean()->notNull()->defaultValue(true),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'priority' => $this->integer()->notNull()->defaultValue(0),
                'hitCount' => $this->integer()->notNull()->defaultValue(0),
                'lastHitDate' => $this->dateTime()->null(),
                'description' => $this->string(255)->null(),
                'isAuto' => $this->boolean()->notNull()->defaultValue(false),
                'elementId' => $this->integer()->null(),
                'creatorId' => $this->integer()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        // 2. 404 Missing Requests Table
        if (!$this->db->tableExists(Table::NOT_FOUND)) {
            $this->createTable(Table::NOT_FOUND, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->null(),
                'path' => $this->string(500)->notNull(),
                'pathNormalized' => $this->string(500)->notNull(),
                'hitCount' => $this->integer()->notNull()->defaultValue(1),
                'firstSeen' => $this->dateTime()->notNull(),
                'lastSeen' => $this->dateTime()->notNull(),
                'referrer' => $this->string(500)->null(),
                'userAgent' => $this->string(255)->null(),
                'status' => $this->string(20)->notNull()->defaultValue('unresolved'),
                'redirectId' => $this->integer()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        // 3. Short Links Table
        if (!$this->db->tableExists(Table::SHORT_LINKS)) {
            $this->createTable(Table::SHORT_LINKS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->null(),
                'slug' => $this->string(255)->notNull(),
                'destination' => $this->text()->notNull(),
                'statusCode' => $this->smallInteger()->notNull()->defaultValue(301),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'hitCount' => $this->integer()->notNull()->defaultValue(0),
                'lastHitDate' => $this->dateTime()->null(),
                'description' => $this->string(255)->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        // 4. Audit Trail Table
        if (!$this->db->tableExists(Table::AUDIT)) {
            $this->createTable(Table::AUDIT, [
                'id' => $this->primaryKey(),
                'userId' => $this->integer()->null(),
                'action' => $this->string(50)->notNull(),
                'recordType' => $this->string(50)->notNull(),
                'recordId' => $this->integer()->null(),
                'summary' => $this->text()->notNull(),
                'changes' => $this->text()->null(),
                'ipAddress' => $this->string(45)->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }
    }

    /**
     * Creates database indexes.
     */
    protected function createIndexes(): void
    {
        $this->createIndex(null, Table::REDIRECTS, ['sourceNormalized', 'siteId'], false);
        $this->createIndex(null, Table::REDIRECTS, ['matchType', 'enabled', 'priority'], false);
        $this->createIndex(null, Table::REDIRECTS, ['elementId'], false);

        $this->createIndex(null, Table::NOT_FOUND, ['pathNormalized', 'siteId'], false);
        $this->createIndex(null, Table::NOT_FOUND, ['status', 'lastSeen'], false);

        $this->createIndex(null, Table::SHORT_LINKS, ['slug', 'siteId'], true);

        $this->createIndex(null, Table::AUDIT, ['dateCreated'], false);
        $this->createIndex(null, Table::AUDIT, ['userId'], false);
    }

    /**
     * Adds foreign keys.
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(null, Table::REDIRECTS, 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::REDIRECTS, 'creatorId', '{{%users}}', 'id', 'SET NULL', 'CASCADE');

        $this->addForeignKey(null, Table::NOT_FOUND, 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::NOT_FOUND, 'redirectId', Table::REDIRECTS, 'id', 'SET NULL', 'CASCADE');

        $this->addForeignKey(null, Table::SHORT_LINKS, 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey(null, Table::AUDIT, 'userId', '{{%users}}', 'id', 'SET NULL', 'CASCADE');
    }
}
