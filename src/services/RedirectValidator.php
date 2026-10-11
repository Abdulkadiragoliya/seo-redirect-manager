<?php

namespace abdulkadir\seoredirectmanager\services;

use yii\base\Component;

/**
 * Service providing security validation, SSRF defense, and syntax verification.
 */
class RedirectValidator extends Component
{
    /**
     * Checks if an external URL is safe against Server-Side Request Forgery (SSRF).
     * Blocks loopback, link-local, cloud metadata, and RFC 1918 private network addresses.
     */
    public function isSafeExternalUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        // 1. Block localhost / loopback names
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        // 2. Resolve DNS to IP
        $ips = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (empty($ips)) {
            // Try gethostbyname
            $ip = @gethostbyname($host);
            if ($ip && $ip !== $host) {
                $ips = [['ip' => $ip]];
            }
        }

        if (empty($ips)) {
            // If host itself is an IP
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return $this->isPublicIp($host);
            }
            return false;
        }

        foreach ($ips as $record) {
            $ip = $record['ip'] ?? ($record['ipv6'] ?? null);
            if ($ip && !$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifies that an IP address is a routable public IP and not private/reserved.
     */
    public function isPublicIp(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
        // FILTER_FLAG_NO_RES_RANGE: 0.0.0.0/8, 169.254.0.0/16, 127.0.0.0/8, etc.
        $valid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($valid === false) {
            return false;
        }

        // Extra check for AWS/GCP/Azure link-local metadata IP (169.254.169.254)
        if (str_starts_with($ip, '169.254.')) {
            return false;
        }

        return true;
    }

    /**
     * Checks if a destination URL has control characters or potential header injection.
     */
    public function hasHeaderInjection(string $url): bool
    {
        return (bool)preg_match('/[\r\n\0]/', $url);
    }
}
