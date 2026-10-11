<?php

namespace abdulkadir\seoredirectmanager\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Console controller for redirect rule CLI administration.
 */
class RedirectController extends Controller
{
    /**
     * Lists active redirect rules.
     * Example: php craft seo-redirect-manager/redirect/list
     */
    public function actionList(): int
    {
        $this->stdout("--- SEO Redirect Manager: Rules List ---\n", Console::FG_CYAN, Console::BOLD);

        $rules = SeoRedirectManager::getInstance()->redirects->getRedirects([], 100);
        if (empty($rules)) {
            $this->stdout("No redirect rules found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        foreach ($rules as $r) {
            $this->stdout(sprintf(" #%d [%d] [%s] %s -> %s (%d hits)\n",
                $r->id,
                $r->statusCode,
                strtoupper($r->matchType),
                $r->source,
                $r->destination,
                $r->hitCount
            ));
        }

        return ExitCode::OK;
    }
}
