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

it('quotes docker stop container names', function () {
    expect(dockerStopContainerCommand('coolify-deployment', 30))
        ->toBe("docker stop --time=30 'coolify-deployment'");
});

it('prevents command injection in docker stop container names', function () {
    $command = dockerStopContainerCommand('coolify-deployment; id #', 30);

    expect($command)
        ->toBe("docker stop --time=30 'coolify-deployment; id #'")
        ->not->toContain('docker stop --time=30 coolify-deployment;');
});

it('quotes docker stack names', function () {
    expect(dockerStackRemoveCommand('app-pr-1'))
        ->toBe("docker stack rm 'app-pr-1'");
});

it('prevents command injection in docker stack names', function () {
    $command = dockerStackRemoveCommand('app-pr-1; id #');

    expect($command)
        ->toBe("docker stack rm 'app-pr-1; id #'")
        ->not->toContain('docker stack rm app-pr-1;');
});

it('quotes docker network disconnect arguments', function () {
    expect(dockerNetworkDisconnectCommand('app-network', 'coolify-proxy'))
        ->toBe("docker network disconnect 'app-network' 'coolify-proxy'");
});

it('prevents command injection in docker network disconnect arguments', function () {
    $command = dockerNetworkDisconnectCommand('app-network; id #', 'coolify-proxy; whoami #');

    expect($command)
        ->toBe("docker network disconnect 'app-network; id #' 'coolify-proxy; whoami #'")
        ->not->toContain('docker network disconnect app-network;');
});

it('quotes docker network remove arguments', function () {
    expect(dockerNetworkRemoveCommand('app-network', force: true))
        ->toBe("docker network rm -f 'app-network'");
});

it('prevents command injection in docker network remove arguments', function () {
    $command = dockerNetworkRemoveCommand('app-network; id #');

    expect($command)
        ->toBe("docker network rm 'app-network; id #'")
        ->not->toContain('docker network rm app-network;');
});

it('quotes Docker Compose project directories', function () {
    expect(dockerComposeDownVolumesCommand('/data/coolify/applications/test app'))
        ->toBe("cd '/data/coolify/applications/test app' && docker compose down -v");
});

it('rejects command injection in Docker Compose project directories', function () {
    expect(fn () => dockerComposeDownVolumesCommand('/data/coolify/applications/test; id #'))
        ->toThrow(Exception::class);
});
