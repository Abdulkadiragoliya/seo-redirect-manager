<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
use yii\base\Component;
use abdulkadir\seoredirectmanager\db\Table;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Service aggregating dashboard metrics and health diagnostics.
 */
class DashboardService extends Component
{
    /**
     * Gathers high-level redirect and 404 metrics.
     */
    public function getStats(?int $siteId = null): array
    {
        $redirectQuery = (new Query())->from(Table::REDIRECTS);
        $notFoundQuery = (new Query())->from(Table::NOT_FOUND);
        $shortLinkQuery = (new Query())->from(Table::SHORT_LINKS);

        if ($siteId !== null) {
            $redirectQuery->andWhere(['or', ['siteId' => $siteId], ['siteId' => null]]);
            $notFoundQuery->andWhere(['siteId' => $siteId]);
            $shortLinkQuery->andWhere(['or', ['siteId' => $siteId], ['siteId' => null]]);
        }

        $totalRedirects = (int)$redirectQuery->count();
        $enabledRedirects = (int)(clone $redirectQuery)->andWhere(['enabled' => true])->count();
        $disabledRedirects = $totalRedirects - $enabledRedirects;

        $total404s = (int)$notFoundQuery->count();
        $unresolved404s = (int)(clone $notFoundQuery)->andWhere(['status' => 'unresolved'])->count();
        $total404Hits = (int)$notFoundQuery->sum('hitCount');
        $totalHits = (int)$redirectQuery->sum('hitCount');

        $totalShortLinks = (int)$shortLinkQuery->count();

        // Chains & loops detection
        $chains = SeoRedirectManager::getInstance()->chains->findChains($siteId);
        $loops = SeoRedirectManager::getInstance()->loops->findLoops($siteId);

        // Recent audit activity
        $recentAudit = SeoRedirectManager::getInstance()->audit->getAuditLogs([], 6);

        // Top redirects
        $topRedirectRows = (clone $redirectQuery)->orderBy('hitCount DESC')->limit(5)->all();
        $topRedirects = [];
        foreach ($topRedirectRows as $r) {
            $m = new \abdulkadir\seoredirectmanager\models\Redirect();
            $m->setAttributes($r, false);
            $topRedirects[] = $m;
        }

        // Recent 404s
        $recent404Rows = (clone $notFoundQuery)->andWhere(['status' => 'unresolved'])->orderBy('lastSeen DESC')->limit(5)->all();
        $recent404s = [];
        foreach ($recent404Rows as $r) {
            $m = new \abdulkadir\seoredirectmanager\models\NotFoundLog();
            $m->setAttributes($r, false);
            $recent404s[] = $m;
        }

        $metrics = [
            'total_redirects' => $totalRedirects,
            'active_redirects' => $enabledRedirects,
            'total_hits' => $totalHits,
            'unresolved_404s' => $unresolved404s,
            'chains_count' => count($chains),
            'loops_count' => count($loops),
        ];

        return [
            'metrics' => $metrics,
            'topRedirects' => $topRedirects,
            'recent404s' => $recent404s,
            'totalRedirects' => $totalRedirects,
            'enabledRedirects' => $enabledRedirects,
            'disabledRedirects' => $disabledRedirects,
            'totalHits' => $totalHits,
            'total404s' => $total404s,
            'unresolved404s' => $unresolved404s,
            'total404Hits' => $total404Hits,
            'totalShortLinks' => $totalShortLinks,
            'chainCount' => count($chains),
            'loopCount' => count($loops),
            'chains' => array_slice($chains, 0, 5),
            'loops' => array_slice($loops, 0, 5),
            'recentAudit' => $recentAudit,
        ];
    }
}
