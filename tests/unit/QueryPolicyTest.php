<?php

namespace abdulkadir\seoredirectmanager\tests\unit;

use abdulkadir\seoredirectmanager\tests\TestCase;
use abdulkadir\seoredirectmanager\services\RedirectResolver;

class QueryPolicyTest extends TestCase
{
    private RedirectResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RedirectResolver();
    }

    public function testPreservePolicy(): void
    {
        $res = $this->resolver->applyQueryPolicy('/new-page', 'preserve', 'utm_source=twitter&foo=bar');
        $this->assertEquals('/new-page?utm_source=twitter&foo=bar', $res);
    }

    public function testIgnorePolicy(): void
    {
        $res = $this->resolver->applyQueryPolicy('/new-page', 'ignore', 'utm_source=twitter&foo=bar');
        $this->assertEquals('/new-page', $res);
    }

    public function testPreservesExistingDestinationQuery(): void
    {
        $res = $this->resolver->applyQueryPolicy('/new-page?initial=1', 'preserve', 'extra=2');
        $this->assertEquals('/new-page?initial=1&extra=2', $res);
    }

    public function testPreservesUrlFragment(): void
    {
        $res = $this->resolver->applyQueryPolicy('/new-page#section-2', 'preserve', 'utm=3');
        $this->assertEquals('/new-page?utm=3#section-2', $res);

        $resWithExisting = $this->resolver->applyQueryPolicy('/new-page?param=1#section-2', 'preserve', 'extra=2');
        $this->assertEquals('/new-page?param=1&extra=2#section-2', $resWithExisting);
    }
}
