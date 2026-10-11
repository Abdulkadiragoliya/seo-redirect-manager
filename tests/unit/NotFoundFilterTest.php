<?php

namespace abdulkadir\seoredirectmanager\tests\unit;

use abdulkadir\seoredirectmanager\tests\TestCase;
use abdulkadir\seoredirectmanager\services\NotFoundLogService;

class NotFoundFilterTest extends TestCase
{
    private NotFoundLogService $service;

    protected function setUp(): void
    {
        $this->service = new NotFoundLogService();
    }

    public function testSanitizesSensitiveQueryParameters(): void
    {
        $raw = 'token=secret123&page=2&auth=bearer-xyz&search=craft';
        $sanitized = $this->service->sanitizeQueryString($raw);

        $this->assertStringContains('token=%5Bredacted%5D', $sanitized);
        $this->assertStringContains('auth=%5Bredacted%5D', $sanitized);
        $this->assertStringContains('page=2', $sanitized);
        $this->assertStringContains('search=craft', $sanitized);
        $this->assertFalse(str_contains($sanitized, 'secret123'));
        $this->assertFalse(str_contains($sanitized, 'bearer-xyz'));
    }
}
