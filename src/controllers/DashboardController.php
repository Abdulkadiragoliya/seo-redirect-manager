<?php

namespace abdulkadir\seoredirectmanager\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Controller for SEO Redirect Manager Control Panel dashboard.
 */
class DashboardController extends Controller
{
    /**
     * Dashboard main view.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('seoRedirectManager:viewDashboard');

        $site = Craft::$app->getSites()->getCurrentSite();
        $stats = SeoRedirectManager::getInstance()->dashboard->getStats($site->id);

        return $this->renderTemplate('seo-redirect-manager/dashboard/index', [
            'stats' => $stats,
            'metrics' => $stats['metrics'] ?? $stats,
            'topRedirects' => $stats['topRedirects'] ?? [],
            'recent404s' => $stats['recent404s'] ?? [],
            'currentSite' => $site,
        ]);
    }
}
