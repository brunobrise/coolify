<?php

use App\Traits\ExecuteRemoteCommand;

it('does not include hidden command text in failure messages', function () {
    $runner = new class
    {
        use ExecuteRemoteCommand;
    };
    $method = new ReflectionMethod($runner, 'commandForFailureMessage');
    $payload = base64_encode("apiVersion: v1\nkind: Config\nsecret-token\n");
    $command = "printf %s '{$payload}' | base64 -d > '/tmp/kubeconfig'";

    expect($method->invoke($runner, $command, true))
        ->toBe('[hidden command]')
        ->not->toContain($payload);
});

it('redacts base64 file write payloads from command previews', function () {
    $payload = base64_encode("apiVersion: v1\nkind: Config\nsecret-token\n");

    expect(remove_iip("printf %s '{$payload}' | base64 -d > '/tmp/kubeconfig'"))
        ->not->toContain($payload)
        ->toContain(REDACTED);

    expect(remove_iip("echo '{$payload}' | base64 -d | tee /artifacts/build-time.env > /dev/null"))
        ->not->toContain($payload)
        ->toContain(REDACTED);

    expect(remove_iip("echo \"{$payload}\" | base64 -d > /tmp/script.sh"))
        ->not->toContain($payload)
        ->toContain(REDACTED);

    expect(remove_iip("echo {$payload} | base64 -d | tee /tmp/docker-compose.yml > /dev/null"))
        ->not->toContain($payload)
        ->toContain(REDACTED);
});
