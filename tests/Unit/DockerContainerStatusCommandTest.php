<?php

it('quotes docker inspect container names', function () {
    expect(dockerContainerInspectCommand('coolify-proxy'))
        ->toBe("docker inspect --format '{{json .}}' 'coolify-proxy'");
});

it('prevents command injection in docker inspect container names', function () {
    $command = dockerContainerInspectCommand('coolify-proxy; id #');

    expect($command)
        ->toBe("docker inspect --format '{{json .}}' 'coolify-proxy; id #'")
        ->not->toContain("docker inspect --format '{{json .}}' coolify-proxy;");
});

it('quotes swarm service status filters', function () {
    expect(dockerServiceStatusCommand('coolify-proxy'))
        ->toBe("docker service ls --filter 'name=coolify-proxy' --format '{{json .}}'");
});

it('prevents command injection in swarm service status filters', function () {
    $command = dockerServiceStatusCommand("coolify-proxy' --format '{{.ID}}'; id #");

    expect($command)
        ->toBe("docker service ls --filter 'name=coolify-proxy'\\'' --format '\\''{{.ID}}'\\''; id #' --format '{{json .}}'")
        ->not->toContain("--filter 'name=coolify-proxy' --format '{{.ID}}';");
});

it('quotes docker remove container names', function () {
    expect(dockerRemoveContainerCommand('coolify-deployment'))
        ->toBe("docker rm -f 'coolify-deployment'");
});

it('prevents command injection in docker remove container names', function () {
    $command = dockerRemoveContainerCommand('coolify-deployment; id #');

    expect($command)
        ->toBe("docker rm -f 'coolify-deployment; id #'")
        ->not->toContain('docker rm -f coolify-deployment;');
});
