<?php

namespace abdulkadir\seoredirectmanager\models;

use Craft;
use craft\base\Model;
use craft\elements\User;

/**
 * Model representing an Audit Log entry.
 */
class AuditLog extends Model
{
    public ?int $id = null;
    public ?int $userId = null;
    public string $action = '';
    public string $recordType = '';
    public ?int $recordId = null;
    public ?int $redirectId = null;
    public string $summary = '';
    public ?string $changes = null;
    public ?string $ipAddress = null;
    public \DateTime|string|null $dateCreated = null;
    public ?string $uid = null;

    /**
     * Get user who performed the action.
     */
    public function getUser(): ?User
    {
        if ($this->userId === null) {
            return null;
        }

        return Craft::$app->getUsers()->getUserById($this->userId);
    }

    /**
     * Get redirect ID if applicable.
     */
    public function getRedirectId(): ?int
    {
        return $this->redirectId ?? $this->recordId;
    }

    /**
     * Get decoded details / changes array.
     */
    public function getDetails(): mixed
    {
        if (empty($this->changes)) {
            return null;
        }

        $decoded = json_decode($this->changes, true);
        return $decoded !== null ? $decoded : $this->changes;
    }
}

