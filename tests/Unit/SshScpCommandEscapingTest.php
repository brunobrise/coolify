<?php

use App\Helpers\SshMultiplexingHelper;
use App\Models\Server;

function makeScpServer(string $ip = '192.0.2.10'): Server
{
    $server = new Server;
    $server->user = 'deployer';
    $server->ip = $ip;

    return $server;
}

it('quotes local scp paths', function () {
    expect(SshMultiplexingHelper::escapedScpPath('/tmp/source file.tar.gz'))
        ->toBe("'/tmp/source file.tar.gz'");
});

it('contains command injection payloads inside local scp path quotes', function () {
    $path = SshMultiplexingHelper::escapedScpPath('/tmp/source.tar.gz; id #');

    expect($path)
        ->toBe("'/tmp/source.tar.gz; id #'")
        ->not->toBe('/tmp/source.tar.gz; id #');
});

it('quotes remote scp targets', function () {
    $target = SshMultiplexingHelper::escapedScpRemoteTarget(makeScpServer(), '/tmp/target file.tar.gz');

    expect($target)->toBe("'deployer'@'192.0.2.10':'/tmp/target file.tar.gz'");
});

it('contains command injection payloads inside remote scp target quotes', function () {
    $target = SshMultiplexingHelper::escapedScpRemoteTarget(makeScpServer(), '/tmp/target.tar.gz; id #');

    expect($target)
        ->toBe("'deployer'@'192.0.2.10':'/tmp/target.tar.gz; id #'")
        ->not->toContain('192.0.2.10:/tmp/target.tar.gz;');
});

it('quotes ipv6 remote scp targets', function () {
    $target = SshMultiplexingHelper::escapedScpRemoteTarget(makeScpServer('2001:db8::1'), '/tmp/target.tar.gz');

    expect($target)->toBe("'deployer'@['2001:db8::1']:'/tmp/target.tar.gz'");
});
