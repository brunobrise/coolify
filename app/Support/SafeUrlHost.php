<?php

namespace App\Support;

class SafeUrlHost
{
    public static function normalize(string $host): string
    {
        $host = strtolower(trim($host));

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return rtrim($host, '.');
    }

    /**
     * @return array<int, string>
     */
    public static function resolvedIps(string $host): array
    {
        $host = self::normalize($host);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];

        return collect($records)
            ->map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter(fn (?string $ip) => filled($ip) && filter_var($ip, FILTER_VALIDATE_IP))
            ->unique()
            ->values()
            ->all();
    }

    public static function isLoopbackOrLinkLocal(string $ip): bool
    {
        $ip = self::withoutIpv6Zone($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);

            if ($long === false) {
                return true;
            }

            return $ip === '0.0.0.0'
                || str_starts_with($ip, '127.')
                || self::ipv4InRange($long, '169.254.0.0', 16);
        }

        $binary = @inet_pton($ip);
        if ($binary === false || strlen($binary) !== 16) {
            return true;
        }

        $mappedIpv4 = self::ipv4FromMappedIpv6($binary);
        if ($mappedIpv4 !== null) {
            return self::isLoopbackOrLinkLocal($mappedIpv4);
        }

        $bytes = array_values(unpack('C*', $binary));

        return $binary === inet_pton('::')
            || $binary === inet_pton('::1')
            || ($bytes[0] === 0xFE && (($bytes[1] & 0xC0) === 0x80));
    }

    public static function isPublicIp(string $ip): bool
    {
        $ip = self::withoutIpv6Zone($ip);
        $binary = @inet_pton($ip);

        if ($binary !== false && strlen($binary) === 16) {
            $mappedIpv4 = self::ipv4FromMappedIpv6($binary);
            if ($mappedIpv4 !== null) {
                return self::isPublicIp($mappedIpv4);
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    public static function redactedUrlForLog(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return '[invalid-url]';
        }

        $scheme = strtolower($parsed['scheme'] ?? 'url');
        $host = $parsed['host'] ?? '[missing-host]';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = '['.$host.']';
        }

        $hasSensitiveSuffix = filled($parsed['path'] ?? null)
            || filled($parsed['query'] ?? null)
            || filled($parsed['fragment'] ?? null)
            || filled($parsed['user'] ?? null)
            || filled($parsed['pass'] ?? null);

        return "{$scheme}://{$host}{$port}".($hasSensitiveSuffix ? '/[redacted]' : '');
    }

    private static function withoutIpv6Zone(string $ip): string
    {
        return str($ip)->before('%')->value();
    }

    private static function ipv4InRange(int $ip, string $network, int $cidr): bool
    {
        $networkLong = ip2long($network);
        if ($networkLong === false) {
            return false;
        }

        $mask = -1 << (32 - $cidr);

        return ($ip & $mask) === ($networkLong & $mask);
    }

    private static function ipv4FromMappedIpv6(string $binary): ?string
    {
        if (substr($binary, 0, 12) !== str_repeat("\0", 10)."\xff\xff") {
            return null;
        }

        return inet_ntop(substr($binary, 12, 4)) ?: null;
    }
}
