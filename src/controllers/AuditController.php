<?php

namespace abdulkadir\seoredirectmanager\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Controller for reviewing administrative Audit History.
 */
class AuditController extends Controller
{
    /**
     * Lists audit log entries.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('seoRedirectManager:viewAuditHistory');

        $request = Craft::$app->getRequest();
        $search = $request->getQueryParam('search', '');
        $actionFilter = $request->getQueryParam('action', $request->getQueryParam('actionFilter', ''));
        $recordType = $request->getQueryParam('recordType', '');
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $criteria = [
            'search' => $search,
            'action' => $actionFilter,
            'recordType' => $recordType,
        ];

        $service = SeoRedirectManager::getInstance()->audit;
        $logs = $service->getAuditLogs($criteria, $limit, $offset);
        $total = $service->getTotalAuditLogs($criteria);
        $totalPages = max(1, (int)ceil($total / $limit));

        return $this->renderTemplate('seo-redirect-manager/audit/index', [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'limit' => $limit,
            'search' => $search,
            'action' => $actionFilter,
            'actionFilter' => $actionFilter,
            'recordType' => $recordType,
        ]);
    }
}
