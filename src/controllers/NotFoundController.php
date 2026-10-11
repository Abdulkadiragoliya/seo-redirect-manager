<?php

namespace abdulkadir\seoredirectmanager\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use abdulkadir\seoredirectmanager\models\Redirect;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Controller for 404 Monitor Control Panel management.
 */
class NotFoundController extends Controller
{
    /**
     * List 404 missing request logs.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('seoRedirectManager:viewNotFoundLogs');

        $request = Craft::$app->getRequest();
        $search = $request->getQueryParam('search', '');
        $siteId = $request->getQueryParam('siteId', '');
        $status = $request->getQueryParam('status', 'unresolved');
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $criteria = [
            'search' => $search,
            'siteId' => $siteId,
            'status' => $status,
            'orderBy' => 'lastSeen DESC',
        ];

        $service = SeoRedirectManager::getInstance()->notFound;
        $logs = $service->getLogs($criteria, $limit, $offset);
        $total = $service->getTotalLogs($criteria);
        $totalPages = max(1, (int)ceil($total / $limit));

        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('seo-redirect-manager/not-found/index', [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'limit' => $limit,
            'search' => $search,
            'siteId' => $siteId,
            'status' => $status,
            'sites' => $sites,
        ]);
    }

    /**
     * Mark single 404 status (resolved / ignored / unresolved).
     */
    public function actionMarkStatus(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:manageNotFoundLogs');

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');
        $status = Craft::$app->getRequest()->getRequiredBodyParam('status');

        if (SeoRedirectManager::getInstance()->notFound->markStatus($id, $status)) {
            $this->setSuccessFlash(Craft::t('seo-redirect-manager', '404 record marked as {status}.', ['status' => $status]));
        } else {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not update 404 record.'));
        }

        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/not-found');
    }

    /**
     * Alias for actionMarkStatus matching CP form action.
     */
    public function actionUpdateStatus(): Response
    {
        return $this->actionMarkStatus();
    }

    /**
     * Clears all 404 missing request logs.
     */
    public function actionClear(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:manageNotFoundLogs');

        $count = SeoRedirectManager::getInstance()->notFound->clearAll();

        $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'All 404 logs cleared ({count} records removed).', ['count' => $count]));
        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/not-found');
    }

    /**
     * Create redirect directly from a 404 log entry and mark it resolved.
     */
    public function actionCreateRedirect(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:editRedirects');
        $this->requirePermission('seoRedirectManager:manageNotFoundLogs');

        $request = Craft::$app->getRequest();
        $logId = (int)$request->getRequiredBodyParam('logId');
        $source = trim((string)$request->getRequiredBodyParam('source'));
        $destination = trim((string)$request->getRequiredBodyParam('destination'));
        $statusCode = (int)$request->getBodyParam('statusCode', 301);
        $siteId = $request->getBodyParam('siteId') !== '' ? (int)$request->getBodyParam('siteId') : null;

        $redirect = new Redirect();
        $redirect->siteId = $siteId;
        $redirect->source = '/' . ltrim($source, '/');
        $redirect->destination = $destination;
        $redirect->statusCode = $statusCode;
        $redirect->matchType = 'exact';
        $redirect->queryPolicy = 'preserve';
        $redirect->enabled = true;
        $redirect->description = 'Resolved from 404 Monitor';

        $saved = SeoRedirectManager::getInstance()->redirects->saveRedirect($redirect);

        if ($saved) {
            SeoRedirectManager::getInstance()->notFound->resolveWithRedirect($logId, $redirect->id);
            $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Created redirect and marked 404 as resolved.'));
        } else {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not create redirect. Check destination formatting.'));
        }

        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/not-found');
    }

    /**
     * Bulk actions for 404 logs.
     */
    public function actionBulk(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:manageNotFoundLogs');

        $action = Craft::$app->getRequest()->getRequiredBodyParam('bulkAction');
        $ids = Craft::$app->getRequest()->getBodyParam('selectedIds', []);

        if (empty($ids)) {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'No 404 records selected.'));
            return $this->redirectToPostedUrl(null, 'seo-redirect-manager/not-found');
        }

        $service = SeoRedirectManager::getInstance()->notFound;

        switch ($action) {
            case 'ignore':
                $count = $service->bulkMarkStatus($ids, 'ignored');
                $this->setSuccessFlash(Craft::t('seo-redirect-manager', '{count} 404 records marked as ignored.', ['count' => $count]));
                break;

            case 'resolve':
                $count = $service->bulkMarkStatus($ids, 'resolved');
                $this->setSuccessFlash(Craft::t('seo-redirect-manager', '{count} 404 records marked as resolved.', ['count' => $count]));
                break;

            case 'delete':
                $count = $service->bulkDelete($ids);
                $this->setSuccessFlash(Craft::t('seo-redirect-manager', '{count} 404 records deleted.', ['count' => $count]));
                break;

            default:
                $this->setFailFlash(Craft::t('seo-redirect-manager', 'Unknown bulk action.'));
        }

        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/not-found');
    }
}
