<?php

namespace abdulkadir\seoredirectmanager\models;

use Craft;
use craft\base\Model;

/**
 * Model representing a Short Link.
 */
class ShortLink extends Model
{
    public ?int $id = null;
    public ?int $siteId = null;
    public string $slug = '';
    public string $destination = '';
    public int $statusCode = 301;
    public bool $enabled = true;
    public int $hitCount = 0;
    public \DateTime|string|null $lastHitDate = null;
    public ?string $description = null;
    public \DateTime|string|null $dateCreated = null;
    public \DateTime|string|null $dateUpdated = null;
    public ?string $uid = null;

    public function getDestinationUrl(): string
    {
        return $this->destination;
    }

    public function setDestinationUrl(string $val): void
    {
        $this->destination = $val;
    }

    public function getNotes(): ?string
    {
        return $this->description;
    }

    public function setNotes(?string $val): void
    {
        $this->description = $val;
    }

    public function getLastHitAt(): ?string
    {
        return $this->lastHitDate;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['slug', 'destination'], 'required'],
            [['slug'], 'string', 'max' => 255],
            [['slug'], 'match', 'pattern' => '/^[a-zA-Z0-9_\-\.]+$/', 'message' => Craft::t('seo-redirect-manager', 'Short link slug can only contain letters, numbers, hyphens, underscores, and dots.')],
            [['destination'], 'string'],
            [['statusCode'], 'in', 'range' => [301, 302, 307, 308]],
            [['enabled'], 'boolean'],
            [['hitCount', 'siteId'], 'integer'],
            [['description'], 'string', 'max' => 255],
            [['destination'], 'validateDestinationSafety'],
        ];
    }

    /**
     * Validates destination safety.
     */
    public function validateDestinationSafety(string $attribute): void
    {
        $val = (string)$this->$attribute;
        if (preg_match('/[\r\n\0]/', $val)) {
            $this->addError($attribute, Craft::t('seo-redirect-manager', 'Destination contains invalid characters.'));
        }
        if (str_starts_with($val, '//')) {
            $this->addError($attribute, Craft::t('seo-redirect-manager', 'Protocol-relative URLs are not allowed.'));
        }
    }
}
