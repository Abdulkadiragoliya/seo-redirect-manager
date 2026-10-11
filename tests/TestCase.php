<?php

namespace abdulkadir\seoredirectmanager\tests;

abstract class TestCase
{
    private int $assertions = 0;
    private array $failures = [];

    public function run(): array
    {
        $methods = get_class_methods($this);
        $testMethods = array_filter($methods, fn($m) => str_starts_with($m, 'test'));

        $results = [
            'class' => static::class,
            'passed' => 0,
            'failed' => 0,
            'assertions' => 0,
            'errors' => [],
        ];

        foreach ($testMethods as $method) {
            $this->setUp();
            try {
                $this->$method();
                $results['passed']++;
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'method' => $method,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }
            $this->tearDown();
        }

        $results['assertions'] = $this->assertions;
        return $results;
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    protected function assertTrue(bool $condition, string $message = 'Failed asserting that condition is true.'): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new \AssertionError($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'Failed asserting that condition is false.'): void
    {
        $this->assertions++;
        if ($condition) {
            throw new \AssertionError($message);
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $msg = $message ?: sprintf('Failed asserting that %s matches expected %s.', json_encode($actual), json_encode($expected));
            throw new \AssertionError($msg);
        }
    }

    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            $msg = $message ?: sprintf('Failed asserting that "%s" contains "%s".', $haystack, $needle);
            throw new \AssertionError($msg);
        }
    }
}
