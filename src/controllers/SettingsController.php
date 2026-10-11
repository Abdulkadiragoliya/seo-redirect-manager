<?php

namespace abdulkadir\seoredirectmanager\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Controller for managing plugin settings.
 */
class SettingsController extends Controller
{
    /**
     * Settings overview page.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('seoRedirectManager:manageSettings');

        $plugin = SeoRedirectManager::getInstance();
        $settings = $plugin->getSettings();

        return $this->renderTemplate('seo-redirect-manager/settings/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Saves plugin settings.
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('seoRedirectManager:manageSettings');

        $plugin = SeoRedirectManager::getInstance();
        $settings = $plugin->getSettings() ?? new \abdulkadir\seoredirectmanager\models\Settings();
        $postSettings = Craft::$app->getRequest()->getBodyParam('settings');
        if (!is_array($postSettings)) {
            $postSettings = Craft::$app->getRequest()->getBodyParams();
        }

        $settings->setAttributes($postSettings, false);

        if (!$settings->validate()) {
            $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not save settings. Please check errors.'));
            return $this->renderTemplate('seo-redirect-manager/settings/index', [
                'settings' => $settings,
            ]);
        }

        if (Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            SeoRedirectManager::getInstance()->audit->logAction(
                'settings',
                'settings',
                null,
                'Updated SEO Redirect Manager configuration settings',
                $settings->toArray()
            );

            $this->setSuccessFlash(Craft::t('seo-redirect-manager', 'Settings saved successfully.'));
            return $this->redirectToPostedUrl(null, 'seo-redirect-manager/settings');
        }

        $this->setFailFlash(Craft::t('seo-redirect-manager', 'Could not save plugin settings.'));
        return $this->redirectToPostedUrl(null, 'seo-redirect-manager/settings');
    }
}
