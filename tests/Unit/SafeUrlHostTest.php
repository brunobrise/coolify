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
