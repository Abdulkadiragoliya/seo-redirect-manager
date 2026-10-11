<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\elements\Entry;
use craft\events\ModelEvent;
use yii\base\Component;
use abdulkadir\seoredirectmanager\models\Redirect;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Service generating automatic 301 redirects upon entry URI changes.
 */
class AutomaticRedirectService extends Component
{
    /**
     * Handles Entry afterSave event to detect public URI changes.
     */
    public function handleEntrySave(ModelEvent $event): void
    {
        $settings = SeoRedirectManager::getInstance()->getSettings();
        if (!$settings->enableAutoEntryRedirects) {
            return;
        }

        /** @var Entry $entry */
        $entry = $event->sender;

        // Skip drafts, revisions, and soft-deleted elements
        if ($entry->getIsDraft() || $entry->getIsRevision() || $entry->trashed) {
            return;
        }

        // Only handle entries that have public URLs
        if (empty($entry->uri)) {
            return;
        }

        $oldUri = $entry->getOldAttribute('uri');
        $newUri = $entry->uri;

        // If old URI exists, is not empty, and differs from new URI
        if (!empty($oldUri) && $oldUri !== $newUri) {
            $this->createAutoRedirect($oldUri, $newUri, (int)$entry->siteId, (int)$entry->id, $entry->title);
        }
    }

    /**
     * Creates an automatic 301 redirect from old URI to new URI.
     */
    public function createAutoRedirect(string $oldUri, string $newUri, int $siteId, int $entryId, string $entryTitle): bool
    {
        $source = '/' . ltrim($oldUri, '/');
        $destination = '/' . ltrim($newUri, '/');

        // Check if destination is identical to source
        if (strcasecmp($source, $destination) === 0) {
            return false;
        }

        $redirectService = SeoRedirectManager::getInstance()->redirects;
        $normalizedSource = trim(mb_strtolower($oldUri, 'UTF-8'), '/');

        // Check if an existing rule already handles this exact path
        $existing = $redirectService->findDuplicate($normalizedSource, $siteId);
        if ($existing) {
            // If existing rule points to the old URI, update it to the new URI
            if ($existing->isAuto) {
                $existing->destination = $destination;
                $redirectService->saveRedirect($existing, false);
                return true;
            }
            return false; // Preserve manual rules
        }

        $redirect = new Redirect();
        $redirect->siteId = $siteId;
        $redirect->source = $source;
        $redirect->sourceNormalized = $normalizedSource;
        $redirect->destination = $destination;
        $redirect->statusCode = 301;
        $redirect->matchType = 'exact';
        $redirect->queryPolicy = 'preserve';
        $redirect->caseSensitive = false;
        $redirect->stripTrailingSlash = true;
        $redirect->enabled = true;
        $redirect->isAuto = true;
        $redirect->elementId = $entryId;
        $redirect->description = sprintf('Automatic redirect for entry "%s" (#%d)', $entryTitle, $entryId);

        return $redirectService->saveRedirect($redirect, true);
    }
}
