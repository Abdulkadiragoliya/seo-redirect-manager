<?php

namespace abdulkadir\seoredirectmanager\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * Settings model for SEO Redirect Manager.
 */
class Settings extends Model
{
    public bool $enableRedirects = true;
    public bool $enableNotFoundMonitoring = true;
    public bool $enableAutoEntryRedirects = true;
    public bool $enableShortLinks = true;
    public string $shortLinkPrefix = 'go';
    public int $notFoundRetentionDays = 30;
    public string $ignoredNotFoundPatterns = "*.map\n*.woff\n*.woff2\n*.ico\n/admin/*\n/cpresources/*\n/healthz\n/ping\n/robots.txt\n/favicon.ico";
    public int $defaultStatusCode = 301;
    public string $defaultQueryPolicy = 'preserve';
    public bool $stripSensitiveQueryParams = true;
    public int $maxChainLength = 5;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['enableRedirects', 'enableNotFoundMonitoring', 'enableAutoEntryRedirects', 'enableShortLinks', 'stripSensitiveQueryParams'], 'boolean'],
            [['defaultStatusCode', 'notFoundRetentionDays', 'maxChainLength'], 'integer'],
            [['defaultStatusCode'], 'in', 'range' => [301, 302, 307, 308]],
            [['defaultQueryPolicy'], 'in', 'range' => ['preserve', 'exact', 'ignore']],
            [['shortLinkPrefix'], 'string', 'max' => 50],
            [['shortLinkPrefix'], 'match', 'pattern' => '/^[a-zA-Z0-9_-]+$/', 'message' => 'Prefix may only contain alphanumeric characters, hyphens, and underscores.'],
            [['ignoredNotFoundPatterns'], 'string'],
        ];
    }

    /**
     * Get array of ignored pattern lines.
     *
     * @return string[]
     */
    public function getIgnoredPatternsList(): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)$this->ignoredNotFoundPatterns);
        $clean = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if (!empty($t) && !str_starts_with($t, '#')) {
                $clean[] = $t;
            }
        }
        return $clean;
    }
}
