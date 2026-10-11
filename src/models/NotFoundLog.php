<?php

namespace abdulkadir\seoredirectmanager\models;

use craft\base\Model;

/**
 * Model representing a logged 404 missing request.
 */
class NotFoundLog extends Model
{
    public ?int $id = null;
    public ?int $siteId = null;
    public string $path = '';
    public string $pathNormalized = '';
    public int $hitCount = 1;
    public \DateTime|string|null $firstSeen = null;
    public \DateTime|string|null $lastSeen = null;
    public ?string $referrer = null;
    public ?string $userAgent = null;
    public string $status = 'unresolved'; // 'unresolved', 'resolved', 'ignored'
    public ?int $redirectId = null;
    public \DateTime|string|null $dateCreated = null;
    public \DateTime|string|null $dateUpdated = null;
    public ?string $uid = null;

    public function getUri(): string
    {
        return $this->path;
    }

    public function setUri(string $uri): void
    {
        $this->path = $uri;
    }

    public function getLastHitAt(): mixed
    {
        return $this->lastSeen;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['path'], 'required'],
            [['path', 'pathNormalized'], 'string', 'max' => 1000],
            [['hitCount', 'siteId', 'redirectId'], 'integer'],
            [['status'], 'in', 'range' => ['unresolved', 'resolved', 'ignored']],
            [['referrer'], 'string', 'max' => 500],
            [['userAgent'], 'string', 'max' => 255],
        ];
    }
}
