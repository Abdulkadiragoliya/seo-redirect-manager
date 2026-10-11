<?php

namespace abdulkadir\seoredirectmanager\tests\unit;

use abdulkadir\seoredirectmanager\tests\TestCase;
use abdulkadir\seoredirectmanager\services\RedirectValidator;

class DestinationSafetyTest extends TestCase
{
    private RedirectValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RedirectValidator();
    }

    public function testBlocksPrivateIpAddresses(): void
    {
        // RFC 1918 Private Ranges
        $this->assertFalse($this->validator->isPublicIp('10.0.0.1'));
        $this->assertFalse($this->validator->isPublicIp('10.255.255.255'));
        $this->assertFalse($this->validator->isPublicIp('172.16.0.1'));
        $this->assertFalse($this->validator->isPublicIp('172.31.255.255'));
        $this->assertFalse($this->validator->isPublicIp('192.168.1.1'));
        $this->assertFalse($this->validator->isPublicIp('192.168.254.254'));

        // Loopback
        $this->assertFalse($this->validator->isPublicIp('127.0.0.1'));

        // Cloud Metadata / Link-Local
        $this->assertFalse($this->validator->isPublicIp('169.254.169.254'));
    }

    public function testAllowsPublicIpAddresses(): void
    {
        // Public DNS / routable IPs
        $this->assertTrue($this->validator->isPublicIp('8.8.8.8'));
        $this->assertTrue($this->validator->isPublicIp('1.1.1.1'));
        $this->assertTrue($this->validator->isPublicIp('93.184.216.34'));
    }

    public function testDetectsHeaderInjection(): void
    {
        $this->assertTrue($this->validator->hasHeaderInjection("https://example.com/\r\nSet-Cookie: evil=true"));
        $this->assertTrue($this->validator->hasHeaderInjection("https://example.com/\nLocation: https://attacker.com"));
        $this->assertTrue($this->validator->hasHeaderInjection("https://example.com/\0hidden"));
        $this->assertFalse($this->validator->hasHeaderInjection("https://example.com/clean-path?param=safe"));
    }
}
