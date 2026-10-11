<?php

namespace abdulkadir\seoredirectmanager\tests\unit;

use abdulkadir\seoredirectmanager\tests\TestCase;
use abdulkadir\seoredirectmanager\services\RedirectResolver;

class NormalizationTest extends TestCase
{
    private RedirectResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RedirectResolver();
    }

    public function testLeadingAndTrailingSlashes(): void
    {
        $this->assertEquals('blog/my-post', $this->resolver->normalizePath('/blog/my-post/'));
        $this->assertEquals('blog/my-post', $this->resolver->normalizePath('blog/my-post'));
        $this->assertEquals('blog/my-post', $this->resolver->normalizePath('///blog/my-post///'));
    }

    public function testCaseInsensitiveNormalization(): void
    {
        $this->assertEquals('blog/my-post', $this->resolver->normalizePath('/BLOG/My-Post/'));
    }

    public function testPreservesCaseWhenConfigured(): void
    {
        $this->assertEquals('Blog/My-Post', $this->resolver->normalizePath('/Blog/My-Post/', true));
    }

    public function testStripsQueryParametersFromPath(): void
    {
        $this->assertEquals('articles/news', $this->resolver->normalizePath('/articles/news?source=email&utm_campaign=spring'));
    }

    public function testUnicodePathHandling(): void
    {
        $this->assertEquals('café/münchen', $this->resolver->normalizePath('/CAFÉ/MÜNCHEN/'));
    }
}
