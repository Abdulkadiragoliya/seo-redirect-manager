<?php

namespace abdulkadir\seoredirectmanager\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Controller for SEO Reports: Redirect Chains, Loops, and Broken Destinations.
 */
class ReportsController extends Controller
{
    /**
     * Redirect chains report.
     */
    public function actionChains(): Response
    {
        $this->requirePermission('seoRedirectManager:viewReports');

        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');
        $siteId = $siteId !== '' && $siteId !== null ? (int)$siteId : null;

        $chains = SeoRedirectManager::getInstance()->chains->findChains($siteId);
        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('seo-redirect-manager/reports/chains', [
            'chains' => $chains,
            'siteId' => $siteId,
            'sites' => $sites,
        ]);
    }

    /**
     * One-click action to optimize a chain: update the initial rule to point directly to the final destination.
     */
    public function actionApplyDirectRoute(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:editRedirects');

        $request = Craft::$app->getRequest();
        $ruleId = (int)$request->getRequiredBodyParam('ruleId');
        $finalDestination = trim((string)$request->getRequiredBodyParam('finalDestination'));

        $redirect = SeoRedirectManager::getInstance()->redirects->getRedirectById($ruleId);
        if (!$redirect) {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Initial redirect rule not found.'));
            return $this->redirectToPostedUrl(null, 'seo-redirect-manager/reports/chains');
        }

        $redirect->destination = $finalDestination;
        $saved = SeoRedirectManager::getInstance()->redirects->saveRedirect($redirect);

        if ($saved) {
            $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Chain optimized! Rule #{id} now points directly to "{dest}".', [
                'id' => $ruleId,
                'dest' => $finalDestination,
            ]));
        } else {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not update redirect rule.'));
        }

        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/reports/chains');
    }

    /**
     * Redirect loops report.
     */
    public function actionLoops(): Response
    {
        $this->requirePermission('seoRedirectManager:viewReports');

        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');
        $siteId = $siteId !== '' && $siteId !== null ? (int)$siteId : null;

        $loops = SeoRedirectManager::getInstance()->loops->findLoops($siteId);
        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('seo-redirect-manager/reports/loops', [
            'loops' => $loops,
            'siteId' => $siteId,
            'sites' => $sites,
        ]);
    }

    /**
     * Broken destination checks report.
     */
    public function actionDestinations(): Response
    {
        $this->requirePermission('seoRedirectManager:viewReports');

        $results = SeoRedirectManager::getInstance()->destinationChecks->checkDestinations();
        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('seo-redirect-manager/reports/destinations', [
            'results' => $results,
            'sites' => $sites,
        ]);
    }

    /**
     * Dispatches destination check queue job.
     */
    public function actionRunDestinationCheck(): Response
    {
        $this->requirePermission('seoRedirectManager:viewReports');

        $limit = (int)Craft::$app->getRequest()->getParam('limit', 50);

        Craft::$app->getQueue()->push(new \abdulkadir\seoredirectmanager\jobs\CheckDestinationsJob([
            'batchLimit' => $limit,
            'limit' => $limit,
        ]));

        try {
            $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Destination health check job queued.'));
        } catch (\Throwable $e) {
            // In environments where session is unavailable
        }

        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/reports/destinations');
    }

    /**
     * Alias for actionApplyDirectRoute.
     */
    public function actionOptimizeChain(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:editRedirects');

        $request = Craft::$app->getRequest();
        $startId = (int)$request->getBodyParam('startId', $request->getBodyParam('ruleId'));
        $target = trim((string)$request->getBodyParam('target', $request->getBodyParam('finalDestination')));

        $redirect = SeoRedirectManager::getInstance()->redirects->getRedirectById($startId);
        if (!$redirect) {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Initial redirect rule not found.'));
            return $this->redirectToPostedUrl(null, 'seo-redirect-manager/reports/chains');
        }

        $redirect->destination = $target;
        $saved = SeoRedirectManager::getInstance()->redirects->saveRedirect($redirect);

        if ($saved) {
            $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Chain optimized! Rule #{id} now points directly to "{dest}".', [
                'id' => $startId,
                'dest' => $target,
            ]));
        } else {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not update redirect rule.'));
        }

        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/reports/chains');
    }
}
