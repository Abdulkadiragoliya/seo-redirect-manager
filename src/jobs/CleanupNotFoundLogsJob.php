<?php

namespace abdulkadir\seoredirectmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Queue job pruning old 404 missing request logs past configured retention threshold.
 */
class CleanupNotFoundLogsJob extends BaseJob
{
    public int $retentionDays = 30;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $this->setProgress($queue, 0.1, Craft::t('seo-redirect-manager', 'Calculating retention threshold...'));

        $pruned = SeoRedirectManager::getInstance()->notFound->cleanupOldLogs($this->retentionDays);

        $this->setProgress($queue, 1.0, Craft::t('seo-redirect-manager', 'Pruned {count} old 404 log records.', ['count' => $pruned]));

        Craft::info(sprintf('CleanupNotFoundLogsJob pruned %d records older than %d days', $pruned, $this->retentionDays), __METHOD__);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('seo-redirect-manager', 'Pruning old 404 missing request logs');
    }
}
