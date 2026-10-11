<?php

namespace abdulkadir\seoredirectmanager\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Console controller for scanning redirect destination health.
 */
class CheckController extends Controller
{
    /**
     * Runs health check across active redirect destinations.
     * Example: php craft seo-redirect-manager/check/destinations
     */
    public function actionDestinations(): int
    {
        $this->stdout("--- SEO Redirect Manager: Destination Scanner ---\n", Console::FG_CYAN, Console::BOLD);

        $results = SeoRedirectManager::getInstance()->destinationChecks->checkDestinations();
        $total = count($results);
        $valid = 0;
        $warnings = 0;
        $invalid = 0;

        foreach ($results as $res) {
            if ($res['status'] === 'valid') {
                $valid++;
            } elseif ($res['status'] === 'warning') {
                $warnings++;
                $this->stdout(sprintf(" [WARN] %s -> %s (%s)\n", $res['source'], $res['destination'], $res['message']), Console::FG_YELLOW);
            } else {
                $invalid++;
                $this->stdout(sprintf(" [FAIL] %s -> %s (%s)\n", $res['source'], $res['destination'], $res['message']), Console::FG_RED);
            }
        }

        $this->stdout(sprintf("\nScan Complete: %d total, %d valid, %d warnings, %d invalid\n", $total, $valid, $warnings, $invalid), Console::FG_GREEN, Console::BOLD);

        return ExitCode::OK;
    }
}
