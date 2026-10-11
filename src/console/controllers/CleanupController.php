<?php

namespace abdulkadir\seoredirectmanager\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Console controller for cleanup and maintenance operations.
 */
class CleanupController extends Controller
{
    /**
     * @var int|null Retention days threshold
     */
    public ?int $days = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['days']);
    }

    /**
     * Prunes old 404 missing request logs past configured retention threshold.
     * Example: php craft seo-redirect-manager/cleanup/not-found --days=30
     */
    public function actionNotFound(?int $days = null): int
    {
        $settings = SeoRedirectManager::getInstance()->getSettings();
        $retentionDays = $this->days ?? ($days ?? $settings->notFoundRetentionDays);

        $this->stdout("--- SEO Redirect Manager: 404 Log Pruner ---\n", Console::FG_CYAN, Console::BOLD);
        $this->stdout("Pruning records older than {$retentionDays} days...\n");

        $pruned = SeoRedirectManager::getInstance()->notFound->cleanupOldLogs($retentionDays);

        $this->stdout("Successfully pruned {$pruned} old 404 log records.\n", Console::FG_GREEN, Console::BOLD);

        return ExitCode::OK;
    }

    /**
     * Alias for actionNotFound.
     */
    public function actionLogs(?int $days = null): int
    {
        return $this->actionNotFound($days);
    }
}
