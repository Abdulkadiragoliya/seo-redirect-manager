<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
use yii\base\Component;
use abdulkadir\seoredirectmanager\db\Table;
use abdulkadir\seoredirectmanager\SeoRedirectManager;

/**
 * High-performance redirect resolution engine.
 */
class RedirectResolver extends Component
{
    /**
     * Resolves an incoming 404 URI against short links, exact redirects, and regex rules.
     *
     * @param string $requestedUri Raw path and query string (e.g. "/old-post?utm=123")
     * @param int|null $siteId Current Craft site ID
     * @return array|null ['destination' => string, 'statusCode' => int, 'type' => string, 'id' => int]
     */
    public function resolve(string $requestedUri, ?int $siteId = null): ?array
    {
        $settings = SeoRedirectManager::getInstance()->getSettings();
        if (!$settings->enableRedirects) {
            return null;
        }

        $siteId = $siteId ?? (Craft::$app->getSites()->getCurrentSite()->id ?? null);

        // 1. Separate path from query string
        $parts = parse_url($requestedUri);
        $rawPath = $parts['path'] ?? $requestedUri;
        $requestQuery = $parts['query'] ?? '';

        // 2. Check Short Links (e.g. /go/promo)
        if ($settings->enableShortLinks) {
            $prefix = trim($settings->shortLinkPrefix, '/');
            $cleanPath = trim($rawPath, '/');

            if ($prefix !== '' && (str_starts_with($cleanPath, $prefix . '/') || $cleanPath === $prefix)) {
                $slug = substr($cleanPath, strlen($prefix) + 1);
                if (!empty($slug)) {
                    $shortLink = SeoRedirectManager::getInstance()->shortLinks->getShortLinkBySlug($slug, $siteId);
                    if ($shortLink && $shortLink->enabled) {
                        SeoRedirectManager::getInstance()->shortLinks->incrementHitCount($shortLink->id);
                        $dest = $shortLink->destination;
                        if (!empty($requestQuery)) {
                            $dest = $this->appendQueryString($dest, $requestQuery);
                        }
                        return [
                            'destination' => $dest,
                            'statusCode' => (int)$shortLink->statusCode,
                            'type' => 'short_link',
                            'id' => $shortLink->id,
                        ];
                    }
                }
            }
        }

        // 3. Normalized path for exact matching (both case-insensitive and case-sensitive variations)
        $normalizedLower = $this->normalizePath($rawPath, false, true);
        $normalizedExact = $this->normalizePath($rawPath, true, true);

        // 4. Exact Match — Site-Specific Check
        $exactRule = $this->findExactRule([$normalizedLower, $normalizedExact], $siteId, $requestQuery);

        // 5. Exact Match — Shared (All Sites) Fallback
        if (!$exactRule && $siteId !== null) {
            $exactRule = $this->findExactRule([$normalizedLower, $normalizedExact], null, $requestQuery);
        }

        if ($exactRule) {
            return $this->buildRedirectResponse($exactRule, $rawPath, $requestQuery);
        }

        // 6. Regex Matching (Ordered by priority DESC, id ASC)
        $regexRule = $this->findRegexRule($rawPath, $siteId, $requestQuery);
        if ($regexRule) {
            return $regexRule;
        }

        return null;
    }

    /**
     * Normalizes a path for matching.
     */
    public function normalizePath(string $path, bool $caseSensitive = false, bool $stripTrailingSlash = true): string
    {
        $path = trim($path);

        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            $path = explode('#', $path, 2)[0];
            $path = explode('?', $path, 2)[0];
        } else {
            $p = parse_url($path, PHP_URL_PATH);
            $path = $p !== null ? $p : $path;
        }

        if ($stripTrailingSlash) {
            $path = trim($path, '/');
        } else {
            $path = ltrim($path, '/');
        }

        if (!$caseSensitive) {
            $path = mb_strtolower($path, 'UTF-8');
        }

        return $path;
    }

    /**
     * Looks up an exact matching rule from database.
     */
    protected function findExactRule(array $normalizedPaths, ?int $siteId, string $requestQuery): ?array
    {
        $query = (new Query())
            ->from(Table::REDIRECTS)
            ->where(['matchType' => 'exact', 'enabled' => true])
            ->andWhere(['in', 'sourceNormalized', $normalizedPaths]);

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        } else {
            $query->andWhere(['siteId' => null]);
        }

        $rows = $query->all();
        if (empty($rows)) {
            return null;
        }

        // Check query policy for candidates
        foreach ($rows as $row) {
            $policy = $row['queryPolicy'];

            if ($policy === 'exact') {
                $sourceParts = parse_url($row['source']);
                $sourceQuery = $sourceParts['query'] ?? '';
                if ($sourceQuery !== $requestQuery) {
                    continue; // Query strings must match exactly
                }
            }

            return $row;
        }

        return null;
    }

    /**
     * Finds and executes matching regex rule.
     */
    protected function findRegexRule(string $rawPath, ?int $siteId, string $requestQuery): ?array
    {
        $query = (new Query())
            ->from(Table::REDIRECTS)
            ->where(['matchType' => 'regex', 'enabled' => true]);

        if ($siteId !== null) {
            $query->andWhere(['or', ['siteId' => $siteId], ['siteId' => null]]);
        } else {
            $query->andWhere(['siteId' => null]);
        }

        $query->orderBy('priority DESC, id ASC');

        $rules = $query->all();
        if (empty($rules)) {
            return null;
        }

        $pathWithSlash = '/' . ltrim($rawPath, '/');
        $pathWithoutSlash = ltrim($rawPath, '/');

        foreach ($rules as $rule) {
            $pattern = trim($rule['source']);
            $caseSensitive = (bool)$rule['caseSensitive'];

            // Build regex with delimiters
            $cleanPattern = ltrim(rtrim($pattern, '$'), '^');
            $regex = '#^' . $cleanPattern . '$#u' . ($caseSensitive ? '' : 'i');

            // Try matching with both leading slash and without
            $matched = false;
            $matches = [];

            if (@preg_match($regex, $pathWithSlash, $matches)) {
                $matched = true;
            } elseif (@preg_match($regex, $pathWithoutSlash, $matches)) {
                $matched = true;
            }

            if ($matched) {
                // Check exact query policy if required
                if ($rule['queryPolicy'] === 'exact') {
                    $sourceParts = parse_url($rule['source']);
                    $sourceQuery = $sourceParts['query'] ?? '';
                    if ($sourceQuery !== $requestQuery) {
                        continue;
                    }
                }

                // Replace backreferences ($1, $2 or \1, \2)
                $destination = $rule['destination'];
                foreach ($matches as $k => $v) {
                    if (is_int($k) && $k > 0) {
                        $destination = str_replace(['$' . $k, '\\' . $k], $v, $destination);
                    }
                }

                // Apply query policy
                $destination = $this->applyQueryPolicy($destination, $rule['queryPolicy'], $requestQuery);

                // Prevent self-loop
                if ($this->isSelfRedirect($rawPath, $destination)) {
                    continue;
                }

                SeoRedirectManager::getInstance()->redirects->recordHit((int)$rule['id']);

                return [
                    'destination' => $destination,
                    'statusCode' => (int)$rule['statusCode'],
                    'type' => 'redirect',
                    'id' => (int)$rule['id'],
                ];
            }
        }

        return null;
    }

    /**
     * Builds response array from exact rule.
     */
    protected function buildRedirectResponse(array $rule, string $rawPath, string $requestQuery): ?array
    {
        $destination = $this->applyQueryPolicy($rule['destination'], $rule['queryPolicy'], $requestQuery);

        if ($this->isSelfRedirect($rawPath, $destination)) {
            return null;
        }

        SeoRedirectManager::getInstance()->redirects->recordHit((int)$rule['id']);

        return [
            'destination' => $destination,
            'statusCode' => (int)$rule['statusCode'],
            'type' => 'redirect',
            'id' => (int)$rule['id'],
        ];
    }

    /**
     * Applies query string handling policy.
     */
    public function applyQueryPolicy(string $destination, string $queryPolicy, string $requestQuery): string
    {
        if ($queryPolicy === 'ignore' || empty($requestQuery)) {
            return $destination;
        }

        if ($queryPolicy === 'preserve' || $queryPolicy === 'exact') {
            return $this->appendQueryString($destination, $requestQuery);
        }

        return $destination;
    }

    /**
     * Appends query parameters to destination URL.
     */
    public function appendQueryString(string $destination, string $queryString): string
    {
        if (empty($queryString)) {
            return $destination;
        }

        // Split existing fragment
        $fragment = '';
        if (str_contains($destination, '#')) {
            [$destination, $fragment] = explode('#', $destination, 2);
            $fragment = '#' . $fragment;
        }

        $separator = str_contains($destination, '?') ? '&' : '?';
        return $destination . $separator . $queryString . $fragment;
    }

    /**
     * Checks if target destination is identical to incoming path.
     */
    protected function isSelfRedirect(string $rawPath, string $destination): bool
    {
        $destPath = parse_url($destination, PHP_URL_PATH);
        if ($destPath === null) {
            $destPath = $destination;
        }

        $s = trim($rawPath, '/');
        $d = trim($destPath, '/');

        return (!empty($s) && strcasecmp($s, $d) === 0);
    }
}
