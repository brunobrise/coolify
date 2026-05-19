<?php

use App\Support\SafeUrlHost;

it('redacts sensitive URL parts before logging', function () {
    $redacted = SafeUrlHost::redactedUrlForLog('https://user:secret@example.com/hooks/token/path?key=value#fragment');

    expect($redacted)->toBe('https://example.com/[redacted]')
        ->not->toContain('user')
        ->not->toContain('secret')
        ->not->toContain('token')
        ->not->toContain('key=value');
});

it('keeps only origin data for log diagnostics', function () {
    expect(SafeUrlHost::redactedUrlForLog('https://example.com'))->toBe('https://example.com')
        ->and(SafeUrlHost::redactedUrlForLog('http://example.com:8080/path'))->toBe('http://example.com:8080/[redacted]')
        ->and(SafeUrlHost::redactedUrlForLog('http://[::1]:8080/path'))->toBe('http://[::1]:8080/[redacted]');
});

it('canonicalizes obfuscated IPv4 hosts before safety checks', function (string $host) {
    expect(SafeUrlHost::resolvedIps($host))->toBe(['127.0.0.1']);
})->with([
    'integer' => '2130706433',
    'hex integer' => '0x7f000001',
    'octal dotted' => '0177.0.0.1',
    'short dotted' => '127.1',
]);
