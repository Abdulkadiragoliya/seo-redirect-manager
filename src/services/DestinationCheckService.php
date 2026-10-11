<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use yii\base\Component;
use abdulkadir\seoredirectmanager\models\Redirect;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * Service evaluating destination health with strict SSRF defenses for external targets.
 */
class DestinationCheckService extends Component
{
    /**
     * Checks an array of redirects or all enabled redirects.
     *
     * @param Redirect[] $redirects
     * @return array
     */
    public function checkDestinations(array $redirects = []): array
    {
        if (empty($redirects)) {
            $redirects = SeoRedirectManager::getInstance()->redirects->getRedirects(['enabled' => true], 100);
        }

        $results = [];

        foreach ($redirects as $redirect) {
            $dest = trim($redirect->destination);
            $check = $this->evaluateDestination($dest, $redirect->siteId);
            $results[] = [
                'redirectId' => $redirect->id,
                'source' => $redirect->source,
                'destination' => $dest,
                'status' => $check['status'],
                'message' => $check['message'],
                'httpCode' => $check['httpCode'] ?? null,
                'isExternal' => $check['isExternal'],
            ];
        }

        return $results;
    }

    /**
     * Evaluates a single destination URL or path.
     */
    public function evaluateDestination(string $destination, ?int $siteId = null): array
    {
        // 1. Check for empty or malformed characters
        if (empty($destination)) {
            return [
                'status' => 'invalid',
                'message' => Craft::t('seo-redirect-manager', 'Destination is empty.'),
                'isExternal' => false,
            ];
        }

        if (preg_match('/[\r\n\0]/', $destination)) {
            return [
                'status' => 'invalid',
                'message' => Craft::t('seo-redirect-manager', 'Contains illegal control characters or header injection.'),
                'isExternal' => false,
            ];
        }

        if (str_starts_with($destination, '//')) {
            return [
                'status' => 'invalid',
                'message' => Craft::t('seo-redirect-manager', 'Protocol-relative destinations are blocked.'),
                'isExternal' => true,
            ];
        }

        // 2. External URL check
        $isExternal = (bool)preg_match('#^https?://#i', $destination);
        if ($isExternal) {
            return $this->checkExternalUrl($destination);
        }

        // 3. Internal Path Check
        return $this->checkInternalPath($destination, $siteId);
    }

    /**
     * Checks internal path against published Craft entries.
     */
    protected function checkInternalPath(string $path, ?int $siteId = null): array
    {
        $cleanPath = trim(parse_url($path, PHP_URL_PATH) ?? $path, '/');

        if ($cleanPath === '' || $cleanPath === '__home__') {
            return [
                'status' => 'valid',
                'message' => Craft::t('seo-redirect-manager', 'Points to homepage.'),
                'isExternal' => false,
            ];
        }

        $query = Entry::find()->uri($cleanPath);
        if ($siteId !== null) {
            $query->siteId($siteId);
        }

        $entry = $query->status(null)->one();

        if ($entry) {
            if ($entry->getStatus() === Entry::STATUS_LIVE) {
                return [
                    'status' => 'valid',
                    'message' => Craft::t('seo-redirect-manager', 'Valid published entry: "{title}"', ['title' => $entry->title]),
                    'isExternal' => false,
                ];
            }

            return [
                'status' => 'warning',
                'message' => Craft::t('seo-redirect-manager', 'Entry "{title}" exists but status is "{status}".', ['title' => $entry->title, 'status' => $entry->getStatus()]),
                'isExternal' => false,
            ];
        }

        // Could be a custom controller route, template, or static asset
        return [
            'status' => 'unknown',
            'message' => Craft::t('seo-redirect-manager', 'No Craft entry found for URI (may be custom route or template).'),
            'isExternal' => false,
        ];
    }

    /**
     * Checks external URL with SSRF protection.
     */
    protected function checkExternalUrl(string $url): array
    {
        $validator = SeoRedirectManager::getInstance()->validator;
        if (!$validator->isSafeExternalUrl($url)) {
            return [
                'status' => 'invalid',
                'message' => Craft::t('seo-redirect-manager', 'Blocked by SSRF protection (private IP or restricted host).'),
                'isExternal' => true,
            ];
        }

        // Perform safe HEAD request with strict timeout
        try {
            $client = new Client([
                RequestOptions::TIMEOUT => 4.0,
                RequestOptions::CONNECT_TIMEOUT => 2.5,
                RequestOptions::ALLOW_REDIRECTS => ['max' => 3],
                RequestOptions::HEADERS => [
                    'User-Agent' => 'CraftCMS-SeoRedirectManager/1.0',
                ],
                RequestOptions::VERIFY => true,
            ]);

            $response = $client->head($url);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 400) {
                return [
                    'status' => 'valid',
                    'message' => Craft::t('seo-redirect-manager', 'External URL responded with HTTP {code}.', ['code' => $statusCode]),
                    'httpCode' => $statusCode,
                    'isExternal' => true,
                ];
            }

            return [
                'status' => 'warning',
                'message' => Craft::t('seo-redirect-manager', 'External URL responded with HTTP {code}.', ['code' => $statusCode]),
                'httpCode' => $statusCode,
                'isExternal' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'warning',
                'message' => Craft::t('seo-redirect-manager', 'External request failed: {msg}', ['msg' => $e->getMessage()]),
                'isExternal' => true,
            ];
        }
    }
}
