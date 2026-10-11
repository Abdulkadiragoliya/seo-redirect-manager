<?php

namespace abdulkadir\seoredirectmanager\controllers;

use Craft;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use abdulkadir\seoredirectmanager\models\ShortLink;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Controller for Short Links & URL Aliases CP management.
 */
class ShortLinksController extends Controller
{
    /**
     * List short links.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('seoRedirectManager:manageShortLinks');

        $request = Craft::$app->getRequest();
        $search = $request->getQueryParam('search', '');
        $siteId = $request->getQueryParam('siteId', '');
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $criteria = [
            'search' => $search,
            'siteId' => $siteId,
            'orderBy' => 'dateUpdated DESC',
        ];

        $service = SeoRedirectManager::getInstance()->shortLinks;
        $links = $service->getShortLinks($criteria, $limit, $offset);
        $total = $service->getTotalShortLinks($criteria);
        $totalPages = max(1, (int)ceil($total / $limit));

        $sites = Craft::$app->getSites()->getAllSites();
        $settings = SeoRedirectManager::getInstance()->getSettings();

        return $this->renderTemplate('seo-redirect-manager/short-links/index', [
            'shortLinks' => $links,
            'links' => $links,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'limit' => $limit,
            'search' => $search,
            'siteId' => $siteId,
            'sites' => $sites,
            'prefix' => $settings->shortLinkPrefix,
        ]);
    }

    /**
     * Create or edit a short link.
     */
    public function actionEdit(?int $id = null): Response
    {
        $this->requirePermission('seoRedirectManager:manageShortLinks');

        $service = SeoRedirectManager::getInstance()->shortLinks;
        $link = $id ? $service->getShortLinkById($id) : new ShortLink();

        if ($id && !$link) {
            throw new NotFoundHttpException('Short link not found.');
        }

        $sites = Craft::$app->getSites()->getAllSites();
        $settings = SeoRedirectManager::getInstance()->getSettings();

        return $this->renderTemplate('seo-redirect-manager/short-links/_edit', [
            'shortLink' => $link,
            'link' => $link,
            'isNew' => $link->id === null,
            'sites' => $sites,
            'prefix' => $settings->shortLinkPrefix,
        ]);
    }

    /**
     * Saves a short link.
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:manageShortLinks');

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('id');

        $service = SeoRedirectManager::getInstance()->shortLinks;
        $link = $id ? $service->getShortLinkById((int)$id) : new ShortLink();

        if ($id && !$link) {
            throw new NotFoundHttpException('Short link not found.');
        }

        $dest = trim((string)$request->getBodyParam('destinationUrl', $request->getBodyParam('destination', '')));
        $desc = trim((string)$request->getBodyParam('notes', $request->getBodyParam('description', '')));

        $link->siteId = $request->getBodyParam('siteId') !== '' && $request->getBodyParam('siteId') !== null ? (int)$request->getBodyParam('siteId') : null;
        $link->slug = trim((string)$request->getBodyParam('slug', ''));
        $link->destination = $dest;
        $link->statusCode = (int)$request->getBodyParam('statusCode', 301);
        $link->enabled = (bool)$request->getBodyParam('enabled', true);
        $link->description = $desc;

        if (!$service->saveShortLink($link)) {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not save short link. Please check the errors below.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'shortLink' => $link,
                'link' => $link,
            ]);

            return $this->renderTemplate('seo-redirect-manager/short-links/_edit', [
                'shortLink' => $link,
                'link' => $link,
                'isNew' => $link->id === null,
                'sites' => Craft::$app->getSites()->getAllSites(),
                'prefix' => SeoRedirectManager::getInstance()->getSettings()->shortLinkPrefix,
            ]);
        }

        $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Short link saved successfully.'));
        return $this->redirectToPostedUrl($link, 'seo-redirect-manager/short-links');
    }

    /**
     * Deletes a short link.
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:manageShortLinks');

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (SeoRedirectManager::getInstance()->shortLinks->deleteShortLink($id)) {
            $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Short link deleted.'));
        } else {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not delete short link.'));
        }

        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/short-links');
    }
}
