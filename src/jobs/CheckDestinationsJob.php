<?php

namespace abdulkadir\seoredirectmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Queue job for scanning redirect destinations asynchronously.
 */
class CheckDestinationsJob extends BaseJob
{
    public int $batchLimit = 50;
    public int $limit = 50;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $effectiveLimit = max($this->batchLimit, $this->limit);
        $redirects = SeoRedirectManager::getInstance()->redirects->getRedirects(['enabled' => true], $effectiveLimit);
        $total = count($redirects);

        if ($total === 0) {
            $this->setProgress($queue, 1.0, Craft::t('seo-redirect-manager', 'No redirects to check.'));
            return;
        }

        $step = 0;
        foreach ($redirects as $redirect) {
            $step++;
            $this->setProgress($queue, $step / $total, Craft::t('seo-redirect-manager', 'Evaluating destination {current} of {total}...', [
                'current' => $step,
                'total' => $total,
            ]));

            SeoRedirectManager::getInstance()->destinationChecks->evaluateDestination($redirect->destination, $redirect->siteId);
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('seo-redirect-manager', 'Checking redirect destinations');
    }
}
