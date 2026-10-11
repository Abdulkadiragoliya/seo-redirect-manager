<?php

namespace abdulkadir\seoredirectmanager;

use Craft;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\ExceptionEvent;
use craft\events\ModelEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\ErrorHandler;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;
use yii\base\Event;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use abdulkadir\seoredirectmanager\models\Settings;
use abdulkadir\seoredirectmanager\services\AutomaticRedirectService;
use abdulkadir\seoredirectmanager\services\CsvExportService;
use abdulkadir\seoredirectmanager\services\CsvImportService;
use abdulkadir\seoredirectmanager\services\DashboardService;
use abdulkadir\seoredirectmanager\services\DestinationCheckService;
use abdulkadir\seoredirectmanager\services\NotFoundLogService;
use abdulkadir\seoredirectmanager\services\RedirectAuditService;
use abdulkadir\seoredirectmanager\services\RedirectChainService;
use abdulkadir\seoredirectmanager\services\RedirectLoopService;
use abdulkadir\seoredirectmanager\services\RedirectResolver;
use abdulkadir\seoredirectmanager\services\RedirectService;
use abdulkadir\seoredirectmanager\services\RedirectValidator;
use abdulkadir\seoredirectmanager\services\ShortLinkService;

/**
 * SEO Redirect Manager main plugin class for Craft CMS 5.
 *
 * @property RedirectService $redirects
 * @property RedirectResolver $resolver
 * @property RedirectValidator $validator
 * @property NotFoundLogService $notFound
 * @property AutomaticRedirectService $autoRedirects
 * @property CsvImportService $csvImport
 * @property CsvExportService $csvExport
 * @property RedirectChainService $chains
 * @property RedirectLoopService $loops
 * @property DestinationCheckService $destinationChecks
 * @property ShortLinkService $shortLinks
 * @property RedirectAuditService $audit
 * @property DashboardService $dashboard
 */
class SeoRedirectManager extends Plugin
{
    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // Register plugin services
        $this->setComponents([
            'redirects' => RedirectService::class,
            'resolver' => RedirectResolver::class,
            'validator' => RedirectValidator::class,
            'notFound' => NotFoundLogService::class,
            'autoRedirects' => AutomaticRedirectService::class,
            'csvImport' => CsvImportService::class,
            'csvExport' => CsvExportService::class,
            'chains' => RedirectChainService::class,
            'loops' => RedirectLoopService::class,
            'destinationChecks' => DestinationCheckService::class,
            'shortLinks' => ShortLinkService::class,
            'audit' => RedirectAuditService::class,
            'dashboard' => DashboardService::class,
        ]);

        // Register CP navigation
        $this->registerCpNav();

        // Register CP URL rules
        $this->registerCpRoutes();

        // Register User Permissions
        $this->registerPermissions();

        // Register Automatic Entry URI change detection
        $this->registerEntrySaveListener();

        // Register 404 Exception Hook for high-performance redirect matching
        $this->register404RedirectHook();
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('seo-redirect-manager/settings/_plugin-settings', [
            'settings' => $this->getSettings(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        if (!$item) {
            return null;
        }

        $user = Craft::$app->getUser();

        $subnav = [];
        if ($user->checkPermission('seoRedirectManager:viewDashboard')) {
            $subnav['dashboard'] = [
                'label' => Craft::t('seo-redirect-manager', 'Dashboard'),
                'url' => 'seo-redirect-manager',
            ];
        }
        if ($user->checkPermission('seoRedirectManager:viewRedirects')) {
            $subnav['redirects'] = [
                'label' => Craft::t('seo-redirect-manager', 'Redirects'),
                'url' => 'seo-redirect-manager/redirects',
            ];
        }
        if ($user->checkPermission('seoRedirectManager:viewNotFoundLogs')) {
            $subnav['not-found'] = [
                'label' => Craft::t('seo-redirect-manager', '404 Monitor'),
                'url' => 'seo-redirect-manager/not-found',
            ];
        }
        if ($user->checkPermission('seoRedirectManager:manageShortLinks')) {
            $subnav['short-links'] = [
                'label' => Craft::t('seo-redirect-manager', 'Short Links'),
                'url' => 'seo-redirect-manager/short-links',
            ];
        }
        if ($user->checkPermission('seoRedirectManager:viewReports')) {
            $subnav['chains'] = [
                'label' => Craft::t('seo-redirect-manager', 'Redirect Chains'),
                'url' => 'seo-redirect-manager/reports/chains',
            ];
            $subnav['loops'] = [
                'label' => Craft::t('seo-redirect-manager', 'Redirect Loops'),
                'url' => 'seo-redirect-manager/reports/loops',
            ];
            $subnav['destinations'] = [
                'label' => Craft::t('seo-redirect-manager', 'Destination Checks'),
                'url' => 'seo-redirect-manager/reports/destinations',
            ];
        }
        if ($user->checkPermission('seoRedirectManager:importExport')) {
            $subnav['import-export'] = [
                'label' => Craft::t('seo-redirect-manager', 'Import / Export'),
                'url' => 'seo-redirect-manager/import-export',
            ];
        }
        if ($user->checkPermission('seoRedirectManager:viewAuditHistory')) {
            $subnav['audit'] = [
                'label' => Craft::t('seo-redirect-manager', 'Audit History'),
                'url' => 'seo-redirect-manager/audit',
            ];
        }
        if ($user->checkPermission('seoRedirectManager:manageSettings')) {
            $subnav['settings'] = [
                'label' => Craft::t('seo-redirect-manager', 'Settings'),
                'url' => 'seo-redirect-manager/settings',
            ];
        }

        $item['subnav'] = $subnav;
        return $item;
    }

    /**
     * Registers Control Panel Navigation items.
     */
    protected function registerCpNav(): void
    {
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                if (Craft::$app->getUser()->checkPermission('seoRedirectManager:viewDashboard') ||
                    Craft::$app->getUser()->checkPermission('seoRedirectManager:viewRedirects')) {
                    // Default handling by getCpNavItem
                }
            }
        );
    }

    /**
     * Registers Control Panel URL rules.
     */
    protected function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['seo-redirect-manager'] = 'seo-redirect-manager/dashboard/index';
                $event->rules['seo-redirect-manager/dashboard'] = 'seo-redirect-manager/dashboard/index';
                $event->rules['seo-redirect-manager/redirects'] = 'seo-redirect-manager/redirects/index';
                $event->rules['seo-redirect-manager/redirects/new'] = 'seo-redirect-manager/redirects/edit';
                $event->rules['seo-redirect-manager/redirects/<id:\d+>'] = 'seo-redirect-manager/redirects/edit';
                $event->rules['seo-redirect-manager/not-found'] = 'seo-redirect-manager/not-found/index';
                $event->rules['seo-redirect-manager/short-links'] = 'seo-redirect-manager/short-links/index';
                $event->rules['seo-redirect-manager/short-links/new'] = 'seo-redirect-manager/short-links/edit';
                $event->rules['seo-redirect-manager/short-links/<id:\d+>'] = 'seo-redirect-manager/short-links/edit';
                $event->rules['seo-redirect-manager/reports/chains'] = 'seo-redirect-manager/reports/chains';
                $event->rules['seo-redirect-manager/reports/loops'] = 'seo-redirect-manager/reports/loops';
                $event->rules['seo-redirect-manager/reports/destinations'] = 'seo-redirect-manager/reports/destinations';
                $event->rules['seo-redirect-manager/import-export'] = 'seo-redirect-manager/import-export/index';
                $event->rules['seo-redirect-manager/audit'] = 'seo-redirect-manager/audit/index';
                $event->rules['seo-redirect-manager/settings'] = 'seo-redirect-manager/settings/index';
            }
        );
    }

    /**
     * Registers granular Craft user permissions.
     */
    protected function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('seo-redirect-manager', 'SEO Redirect Manager'),
                    'permissions' => [
                        'seoRedirectManager:viewDashboard' => [
                            'label' => Craft::t('seo-redirect-manager', 'View Dashboard'),
                        ],
                        'seoRedirectManager:viewRedirects' => [
                            'label' => Craft::t('seo-redirect-manager', 'View Redirects'),
                        ],
                        'seoRedirectManager:editRedirects' => [
                            'label' => Craft::t('seo-redirect-manager', 'Create and Edit Redirects'),
                        ],
                        'seoRedirectManager:deleteRedirects' => [
                            'label' => Craft::t('seo-redirect-manager', 'Delete Redirects'),
                        ],
                        'seoRedirectManager:viewNotFoundLogs' => [
                            'label' => Craft::t('seo-redirect-manager', 'View 404 Monitor Logs'),
                        ],
                        'seoRedirectManager:manageNotFoundLogs' => [
                            'label' => Craft::t('seo-redirect-manager', 'Manage and Resolve 404 Logs'),
                        ],
                        'seoRedirectManager:manageShortLinks' => [
                            'label' => Craft::t('seo-redirect-manager', 'Manage Short Links'),
                        ],
                        'seoRedirectManager:viewReports' => [
                            'label' => Craft::t('seo-redirect-manager', 'View SEO Reports (Chains, Loops, Destinations)'),
                        ],
                        'seoRedirectManager:importExport' => [
                            'label' => Craft::t('seo-redirect-manager', 'Import and Export Redirects (CSV)'),
                        ],
                        'seoRedirectManager:viewAuditHistory' => [
                            'label' => Craft::t('seo-redirect-manager', 'View Audit History'),
                        ],
                        'seoRedirectManager:manageSettings' => [
                            'label' => Craft::t('seo-redirect-manager', 'Manage Plugin Settings'),
                        ],
                    ],
                ];
            }
        );
    }

    /**
     * Registers entry save listener for automated URI change redirects.
     */
    protected function registerEntrySaveListener(): void
    {
        Event::on(
            Entry::class,
            Entry::EVENT_BEFORE_SAVE,
            function(ModelEvent $event) {
                $this->autoRedirects->handleEntryBeforeSave($event);
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function(ModelEvent $event) {
                $this->autoRedirects->handleEntrySave($event);
            }
        );
    }

    /**
     * Registers 404 Exception Hook for redirect resolution.
     * ZERO overhead on successful requests.
     */
    protected function register404RedirectHook(): void
    {
        Event::on(
            ErrorHandler::class,
            ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION,
            function(ExceptionEvent $event) {
                $this->handle404Exception($event);
            }
        );
    }

    /**
     * Intercepts 404 exceptions, evaluates redirect rules, and logs missing pages.
     */
    public function handle404Exception(ExceptionEvent $event): void
    {
        $request = Craft::$app->getRequest();

        // Skip console, CLI, CP requests, or Action requests
        if ($request->getIsConsoleRequest() || $request->getIsCpRequest() || $request->getIsActionRequest()) {
            return;
        }

        $exception = $event->exception;

        // Is this a 404 Not Found exception?
        $isNotFound = ($exception instanceof NotFoundHttpException) ||
            ($exception instanceof HttpException && $exception->statusCode === 404);

        if (!$isNotFound) {
            return;
        }

        $uri = $request->getUrl();
        $site = Craft::$app->getSites()->getCurrentSite();
        $siteId = $site ? (int)$site->id : null;

        // Attempt redirect resolution
        $match = $this->resolver->resolve($uri, $siteId);

        if ($match) {
            // Clean any existing buffer
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            // Dispatch redirect response cleanly
            Craft::$app->getResponse()
                ->redirect($match['destination'], (int)$match['statusCode'])
                ->send();

            exit(0);
        }

        // If no redirect matched, log to 404 monitor
        $this->notFound->log404(
            $uri,
            $siteId,
            $request->getReferrer(),
            $request->getUserAgent()
        );
    }
}
