<?php

namespace abdulkadir\seoredirectmanager\tests\unit;

use abdulkadir\seoredirectmanager\tests\TestCase;

class RegexMatchTest extends TestCase
{
    public function testRegexCaptureGroupReplacement(): void
    {
        $pattern = '^/blog/(.*)$';
        $destination = '/articles/$1';
        $requestPath = '/blog/craft-cms-5-release';

        $cleanPattern = ltrim(rtrim($pattern, '$'), '^');
        $regex = '#^' . $cleanPattern . '$#ui';

        $matched = preg_match($regex, $requestPath, $matches);
        $this->assertTrue((bool)$matched);

        foreach ($matches as $k => $v) {
            if (is_int($k) && $k > 0) {
                $destination = str_replace(['$' . $k, '\\' . $k], $v, $destination);
            }
        }

        $this->assertEquals('/articles/craft-cms-5-release', $destination);
    }

    public function testMultipleCaptureGroups(): void
    {
        $pattern = '^/archive/([0-9]{4})/([0-9]{2})$';
        $destination = '/news?year=$1&month=$2';
        $requestPath = '/archive/2026/10';

        $cleanPattern = ltrim(rtrim($pattern, '$'), '^');
        $regex = '#^' . $cleanPattern . '$#ui';

        $matched = preg_match($regex, $requestPath, $matches);
        $this->assertTrue((bool)$matched);

        foreach ($matches as $k => $v) {
            if (is_int($k) && $k > 0) {
                $destination = str_replace(['$' . $k, '\\' . $k], $v, $destination);
            }
        }

        $this->assertEquals('/news?year=2026&month=10', $destination);
    }
}
