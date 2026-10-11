<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
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
     * @var array<string, string> Stored pre-save URIs indexed by "{elementId}:{siteId}"
     */
    private array $_pendingOldUris = [];

    /**
     * Handles Entry beforeSave event to record pre-save URI.
     */
    public function handleEntryBeforeSave(ModelEvent $event): void
    {
        $settings = SeoRedirectManager::getInstance()->getSettings();
        if (!$settings->enableAutoEntryRedirects) {
            return;
        }

        /** @var Entry $entry */
        $entry = $event->sender;

        if (!$entry instanceof Entry) {
            return;
        }

        // Skip new elements, drafts, revisions, and soft-deleted elements
        if ($event->isNew || empty($entry->id) || $entry->getIsDraft() || $entry->getIsRevision() || $entry->trashed) {
            return;
        }

        // Query elements_sites for the current (pre-save) URI in the database
        $oldUri = (new Query())
            ->select(['uri'])
            ->from('{{%elements_sites}}')
            ->where(['elementId' => (int)$entry->id, 'siteId' => (int)$entry->siteId])
            ->scalar();

        if ($oldUri !== false && $oldUri !== null && $oldUri !== '') {
            $this->_pendingOldUris[$entry->id . ':' . $entry->siteId] = (string)$oldUri;
        }
    }

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

        if (!$entry instanceof Entry) {
            return;
        }

        // Skip drafts, revisions, and soft-deleted elements
        if ($entry->getIsDraft() || $entry->getIsRevision() || $entry->trashed) {
            return;
        }

        $key = $entry->id . ':' . $entry->siteId;
        if (!isset($this->_pendingOldUris[$key])) {
            return;
        }

        $oldUri = $this->_pendingOldUris[$key];
        unset($this->_pendingOldUris[$key]);

        $newUri = $entry->uri;

        // Only handle entries that have public URLs
        if (empty($newUri)) {
            return;
        }

        // If old URI exists, is not empty, and differs from new URI
        if (!empty($oldUri) && $oldUri !== $newUri && $oldUri !== '__home__' && $newUri !== '__home__') {
            $this->createAutoRedirect($oldUri, $newUri, (int)$entry->siteId, (int)$entry->id, (string)$entry->title);
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
                $redirectService->saveRedirect($existing, true);
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
