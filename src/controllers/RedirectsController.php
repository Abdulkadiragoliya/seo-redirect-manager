<?php

namespace abdulkadir\seoredirectmanager\controllers;

use Craft;
use craft\helpers\ArrayHelper;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use abdulkadir\seoredirectmanager\models\Redirect;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Controller for managing redirect rules.
 */
class RedirectsController extends Controller
{
    /**
     * List redirect rules with search, filters, and pagination.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('seoRedirectManager:viewRedirects');

        $request = Craft::$app->getRequest();
        $search = $request->getQueryParam('search', '');
        $siteId = $request->getQueryParam('siteId', '');
        $statusCode = $request->getQueryParam('statusCode', '');
        $matchType = $request->getQueryParam('matchType', '');
        $enabled = $request->getQueryParam('enabled', '');
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $criteria = [
            'search' => $search,
            'siteId' => $siteId,
            'statusCode' => $statusCode,
            'matchType' => $matchType,
            'enabled' => $enabled,
            'orderBy' => 'dateUpdated DESC',
        ];

        $redirectService = SeoRedirectManager::getInstance()->redirects;
        $redirects = $redirectService->getRedirects($criteria, $limit, $offset);
        $total = $redirectService->getTotalRedirects($criteria);
        $totalPages = max(1, (int)ceil($total / $limit));

        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('seo-redirect-manager/redirects/index', [
            'redirects' => $redirects,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'limit' => $limit,
            'search' => $search,
            'siteId' => $siteId,
            'statusCode' => $statusCode,
            'matchType' => $matchType,
            'enabled' => $enabled,
            'sites' => $sites,
        ]);
    }

    /**
     * Create or edit a redirect rule.
     */
    public function actionEdit(?int $id = null): Response
    {
        $this->requirePermission('seoRedirectManager:editRedirects');

        $redirectService = SeoRedirectManager::getInstance()->redirects;
        $redirect = $id ? $redirectService->getRedirectById($id) : new Redirect();

        if ($id && !$redirect) {
            throw new NotFoundHttpException('Redirect rule not found.');
        }

        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('seo-redirect-manager/redirects/_edit', [
            'redirect' => $redirect,
            'isNew' => $redirect->id === null,
            'sites' => $sites,
        ]);
    }

    /**
     * Saves a redirect rule.
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:editRedirects');

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('id');

        $redirectService = SeoRedirectManager::getInstance()->redirects;
        $redirect = $id ? $redirectService->getRedirectById((int)$id) : new Redirect();

        if ($id && !$redirect) {
            throw new NotFoundHttpException('Redirect rule not found.');
        }

        $src = trim((string)$request->getBodyParam('sourceUrl', $request->getBodyParam('source', '')));
        $dst = trim((string)$request->getBodyParam('destinationUrl', $request->getBodyParam('destination', '')));

        $redirect->siteId = $request->getBodyParam('siteId') !== '' && $request->getBodyParam('siteId') !== null ? (int)$request->getBodyParam('siteId') : null;
        $redirect->source = $src;
        $redirect->destination = $dst;
        $redirect->statusCode = (int)$request->getBodyParam('statusCode', 301);
        $redirect->matchType = (string)$request->getBodyParam('matchType', 'exact');
        $redirect->queryPolicy = (string)$request->getBodyParam('queryPolicy', 'preserve');
        $redirect->caseSensitive = (bool)$request->getBodyParam('caseSensitive', false);
        $redirect->stripTrailingSlash = (bool)$request->getBodyParam('stripTrailingSlash', true);
        $redirect->enabled = (bool)$request->getBodyParam('enabled', true);
        $redirect->priority = (int)$request->getBodyParam('priority', 0);
        $redirect->description = trim((string)$request->getBodyParam('description', ''));

        // Check for loop before saving
        if ($redirect->matchType === 'exact') {
            if (SeoRedirectManager::getInstance()->loops->wouldCreateLoop($redirect->source, $redirect->destination, $redirect->siteId, $redirect->id)) {
                $redirect->addError('destination', Craft::t('seo-redirect-manager', 'Saving this redirect would create a redirect loop.'));
            }
        }

        if (!$redirectService->saveRedirect($redirect)) {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not save redirect rule. Please check the errors below.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'redirect' => $redirect,
            ]);

            return $this->renderTemplate('seo-redirect-manager/redirects/_edit', [
                'redirect' => $redirect,
                'isNew' => $redirect->id === null,
                'sites' => Craft::$app->getSites()->getAllSites(),
            ]);
        }

        $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Redirect rule saved successfully.'));
        return $this->redirectToPostedUrl($redirect, 'seo-redirect-manager/redirects');
    }

    /**
     * Deletes a redirect rule.
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:deleteRedirects');

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (SeoRedirectManager::getInstance()->redirects->deleteRedirect($id)) {
            $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Redirect rule deleted.'));
        } else {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not delete redirect rule.'));
        }

        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/redirects');
    }

    /**
     * Performs bulk operations on rules.
     */
    public function actionBulk(): Response
    {
        $this->requirePostRequest();

        $action = Craft::$app->getRequest()->getRequiredBodyParam('bulkAction');
        $ids = Craft::$app->getRequest()->getBodyParam('selectedIds', []);

        if (empty($ids)) {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'No redirect rules selected.'));
            return $this->redirectToPostedUrl(null, 'seo-redirect-manager/redirects');
        }

        $service = SeoRedirectManager::getInstance()->redirects;

        switch ($action) {
            case 'enable':
                $this->requirePermission('seoRedirectManager:editRedirects');
                $count = $service->bulkEnable($ids);
                $this->setSuccessFlash(Craft::t('seo-redirect-manager', '{count} redirect rules enabled.', ['count' => $count]));
                break;

            case 'disable':
                $this->requirePermission('seoRedirectManager:editRedirects');
                $count = $service->bulkDisable($ids);
                $this->setSuccessFlash(Craft::t('seo-redirect-manager', '{count} redirect rules disabled.', ['count' => $count]));
                break;

            case 'delete':
                $this->requirePermission('seoRedirectManager:deleteRedirects');
                $count = $service->bulkDelete($ids);
                $this->setSuccessFlash(Craft::t('seo-redirect-manager', '{count} redirect rules deleted.', ['count' => $count]));
                break;

            default:
                $this->setFailFlash(Craft::t('seo-redirect-manager', 'Unknown bulk action.'));
        }

        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/redirects');
    }
}
