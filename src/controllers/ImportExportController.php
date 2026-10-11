<?php

namespace abdulkadir\seoredirectmanager\controllers;

use Craft;
use craft\web\Controller;
use craft\web\UploadedFile;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Controller for CSV Import and Export operations.
 */
class ImportExportController extends Controller
{
    /**
     * Import / Export overview page.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('seoRedirectManager:importExport');

        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('seo-redirect-manager/import-export/index', [
            'sites' => $sites,
        ]);
    }

    /**
     * Preview and dry-run CSV upload.
     */
    public function actionPreview(): Response
    {
        $this->requirePermission('seoRedirectManager:importExport');

        $request = Craft::$app->getRequest();

        if ($request->getIsPost()) {
            $file = UploadedFile::getInstanceByName('csvFile');
            if (!$file) {
                $this->setFailFlash(Craft::t('seo-redirect-manager', 'Please select a CSV file to upload.'));
                return $this->redirect('seo-redirect-manager/import-export');
            }

            $content = file_get_contents($file->tempName);
            $siteId = $request->getBodyParam('siteId');
            $siteId = $siteId !== '' && $siteId !== null ? (int)$siteId : null;

            $parsed = SeoRedirectManager::getInstance()->csvImport->parseAndValidate($content, $siteId);

            if (!$parsed['success']) {
                $this->setFailFlash($parsed['message']);
                return $this->redirect('seo-redirect-manager/import-export');
            }

            $conflictPolicy = $request->getBodyParam('conflictPolicy', 'skip');
            $filename = $file->name;

            // Cache in user session
            Craft::$app->getSession()->set('seo_redirect_csv_preview_parsed', $parsed);
            Craft::$app->getSession()->set('seo_redirect_csv_preview_rows', $parsed['rows']);
            Craft::$app->getSession()->set('seo_redirect_csv_preview_site_id', $siteId);
            Craft::$app->getSession()->set('seo_redirect_csv_preview_conflict_policy', $conflictPolicy);
            Craft::$app->getSession()->set('seo_redirect_csv_preview_filename', $filename);
        } else {
            // GET request (e.g. browser refresh)
            $parsed = Craft::$app->getSession()->get('seo_redirect_csv_preview_parsed');
            $siteId = Craft::$app->getSession()->get('seo_redirect_csv_preview_site_id');
            $conflictPolicy = Craft::$app->getSession()->get('seo_redirect_csv_preview_conflict_policy', 'skip');
            $filename = Craft::$app->getSession()->get('seo_redirect_csv_preview_filename', 'uploaded.csv');

            if (!$parsed || empty($parsed['rows'])) {
                $this->setFailFlash(Craft::t('seo-redirect-manager', 'No CSV preview session found. Please upload a file.'));
                return $this->redirect('seo-redirect-manager/import-export');
            }
        }

        return $this->renderTemplate('seo-redirect-manager/import-export/preview', [
            'parsed' => $parsed,
            'preview' => $parsed['rows'],
            'rows' => $parsed['rows'],
            'total' => $parsed['totalRows'],
            'validCount' => $parsed['validRows'],
            'invalidCount' => $parsed['errorRows'],
            'duplicateCount' => $parsed['duplicateRows'],
            'filename' => $filename,
            'siteId' => $siteId,
            'conflictPolicy' => $conflictPolicy,
        ]);
    }

    /**
     * Alias for actionExecute matching form action.
     */
    public function actionImport(): Response
    {
        return $this->actionExecute();
    }

    /**
     * Execute confirmed CSV import.
     */
    public function actionExecute(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:importExport');

        $rows = Craft::$app->getSession()->get('seo_redirect_csv_preview_rows');
        $siteId = Craft::$app->getSession()->get('seo_redirect_csv_preview_site_id');
        $conflictPolicy = Craft::$app->getRequest()->getBodyParam('conflictPolicy', 'skip');

        if (empty($rows)) {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'No pending CSV import session found. Please re-upload.'));
            return $this->redirect('seo-redirect-manager/import-export');
        }

        $res = SeoRedirectManager::getInstance()->csvImport->executeImport($rows, $conflictPolicy, $siteId);

        // Clear session
        Craft::$app->getSession()->remove('seo_redirect_csv_preview_rows');
        Craft::$app->getSession()->remove('seo_redirect_csv_preview_site_id');

        $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Import complete: {imported} created, {updated} updated, {skipped} skipped, {failed} failed.', [
            'imported' => $res['imported'],
            'updated' => $res['updated'],
            'skipped' => $res['skipped'],
            'failed' => $res['failed'],
        ]));

        return $this->redirect('seo-redirect-manager/redirects');
    }

    /**
     * Stream CSV download of redirects.
     */
    public function actionExport(): Response
    {
        $this->requirePermission('seoRedirectManager:importExport');

        $request = Craft::$app->getRequest();
        $siteId = $request->getQueryParam('siteId');
        $statusCode = $request->getQueryParam('statusCode');

        $criteria = [
            'siteId' => $siteId !== '' && $siteId !== null ? $siteId : null,
            'statusCode' => $statusCode,
        ];

        $csv = SeoRedirectManager::getInstance()->csvExport->exportToCsv($criteria);
        $filename = 'redirects-' . date('Y-m-d-His') . '.csv';

        return Craft::$app->getResponse()->sendContentAsFile($csv, $filename, [
            'mimeType' => 'text/csv',
            'inline' => false,
        ]);
    }
}
