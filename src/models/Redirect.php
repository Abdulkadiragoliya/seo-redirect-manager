<?php

namespace abdulkadir\seoredirectmanager\models;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;

/**
 * Model representing a single Redirect rule.
 */
class Redirect extends Model
{
    public ?int $id = null;
    public ?int $siteId = null;
    public string $source = '';
    public string $sourceNormalized = '';
    public string $destination = '';
    public int $statusCode = 301;
    public string $matchType = 'exact';
    public string $queryPolicy = 'preserve';
    public bool $caseSensitive = false;
    public bool $stripTrailingSlash = true;
    public bool $enabled = true;
    public int $priority = 0;
    public int $hitCount = 0;
    public \DateTime|string|null $lastHitDate = null;
    public ?string $description = null;
    public bool $isAuto = false;
    public ?int $elementId = null;
    public ?int $creatorId = null;
    public \DateTime|string|null $dateCreated = null;
    public \DateTime|string|null $dateUpdated = null;
    public ?string $uid = null;

    public function getSourceUrl(): string
    {
        return $this->source;
    }

    public function setSourceUrl(string $val): void
    {
        $this->source = $val;
    }

    public function getDestinationUrl(): string
    {
        return $this->destination;
    }

    public function setDestinationUrl(string $val): void
    {
        $this->destination = $val;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['source', 'destination'], 'required'],
            [['source', 'sourceNormalized'], 'string', 'max' => 1000],
            [['destination'], 'string'],
            [['statusCode'], 'in', 'range' => [301, 302, 307, 308]],
            [['matchType'], 'in', 'range' => ['exact', 'regex']],
            [['queryPolicy'], 'in', 'range' => ['preserve', 'exact', 'ignore']],
            [['caseSensitive', 'stripTrailingSlash', 'enabled', 'isAuto'], 'boolean'],
            [['priority', 'hitCount', 'siteId', 'elementId', 'creatorId'], 'integer'],
            [['description'], 'string', 'max' => 255],
            [['destination'], 'validateDestinationSafety'],
            [['source'], 'validateSelfLoop'],
            [['source'], 'validateRegexPattern'],
        ];
    }

    /**
     * Validates destination safety.
     * Prevents header injection (\r, \n), null bytes, and malformed characters.
     */
    public function validateDestinationSafety(string $attribute): void
    {
        $val = (string)$this->$attribute;

        // Check for control characters or header injection
        if (preg_match('/[\r\n\0]/', $val)) {
            $this->addError($attribute, Craft::t('seo-redirect-manager', 'Destination contains invalid characters or header injection sequences.'));
            return;
        }

        // Protocol-relative check (e.g. //malicious.com)
        if (str_starts_with($val, '//')) {
            $this->addError($attribute, Craft::t('seo-redirect-manager', 'Protocol-relative URLs (//example.com) are not allowed for security reasons.'));
            return;
        }

        // Check URL scheme if absolute
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $val)) {
            $scheme = parse_url($val, PHP_URL_SCHEME);
            if (!in_array(strtolower($scheme ?: ''), ['http', 'https'], true)) {
                $this->addError($attribute, Craft::t('seo-redirect-manager', 'Only http:// and https:// external destination schemes are allowed.'));
                return;
            }
        }
    }

    /**
     * Prevents direct self-redirect loops (source === destination).
     */
    public function validateSelfLoop(string $attribute): void
    {
        if ($this->matchType === 'exact') {
            $s = trim($this->source, '/');
            $d = trim($this->destination, '/');
            if (!empty($s) && strcasecmp($s, $d) === 0) {
                $this->addError('destination', Craft::t('seo-redirect-manager', 'Direct redirect loop detected: destination cannot be identical to source path.'));
            }
        }
    }

    /**
     * Validates regex pattern validity and safety.
     */
    public function validateRegexPattern(string $attribute): void
    {
        if ($this->matchType === 'regex') {
            $pattern = (string)$this->$attribute;

            // Enclose in delimiters if not already enclosed
            $testRegex = '#^' . ltrim(rtrim($pattern, '$'), '^') . '$#u';
            if ($this->caseSensitive === false) {
                $testRegex .= 'i';
            }

            // Suppress warning during test compilation
            $valid = @preg_match($testRegex, '');
            if ($valid === false) {
                $error = error_get_last();
                $msg = $error['message'] ?? 'Invalid regular expression syntax.';
                $this->addError($attribute, Craft::t('seo-redirect-manager', 'Invalid Regular Expression: {msg}', ['msg' => $msg]));
            }
        }
    }

    /**
     * Computes the normalized source path for indexing and matching.
     */
    public function getComputedNormalizedSource(): string
    {
        if ($this->matchType === 'regex') {
            return trim($this->source);
        }

        $path = $this->source;

        // Parse path and query
        $parts = parse_url($path);
        $pathOnly = $parts['path'] ?? $path;

        // Strip leading and trailing slashes
        $trimmed = trim($pathOnly, '/');

        if (!$this->caseSensitive) {
            $trimmed = mb_strtolower($trimmed, 'UTF-8');
        }

        return $trimmed;
    }
}
