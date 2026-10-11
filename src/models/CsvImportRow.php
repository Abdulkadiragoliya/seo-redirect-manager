<?php

namespace abdulkadir\seoredirectmanager\models;

use Craft;
use craft\base\Model;

/**
 * Model representing a parsed CSV row during import.
 */
class CsvImportRow extends Model
{
    public int $lineNumber = 0;
    public string $source = '';
    public string $destination = '';
    public int $statusCode = 301;
    public string $matchType = 'exact';
    public ?string $siteHandle = null;
    public ?int $siteId = null;
    public bool $enabled = true;
    public string $queryPolicy = 'preserve';
    public int $priority = 0;
    public ?string $description = null;
    public bool $isValid = true;
    public array $errors = [];
    public string $status = 'pending'; // 'pending', 'imported', 'updated', 'skipped', 'failed'
    public ?string $errorMessage = null;

    public function getRowNumber(): int
    {
        return $this->lineNumber;
    }

    public function getError(): ?string
    {
        return $this->errorMessage ?? ($this->errors[0] ?? null);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['lineNumber'], 'integer'],
            [['source', 'destination'], 'required'],
            [['statusCode'], 'in', 'range' => [301, 302, 307, 308]],
            [['matchType'], 'in', 'range' => ['exact', 'regex']],
            [['queryPolicy'], 'in', 'range' => ['preserve', 'exact', 'ignore']],
            [['enabled'], 'boolean'],
            [['priority'], 'integer'],
            [['description'], 'string', 'max' => 255],
        ];
    }
}
