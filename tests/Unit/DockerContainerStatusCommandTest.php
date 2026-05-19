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

it('quotes docker ps name filters', function () {
    expect(dockerPsNamesByNameCommand('coolify-deployment'))
        ->toBe("docker ps -a --filter 'name=coolify-deployment' --format '{{.Names}}'");
});

it('prevents command injection in docker ps name filters', function () {
    $command = dockerPsNamesByNameCommand("coolify-deployment' --format '{{.ID}}'; id #");

    expect($command)
        ->toBe("docker ps -a --filter 'name=coolify-deployment'\\'' --format '\\''{{.ID}}'\\''; id #' --format '{{.Names}}'")
        ->not->toContain("--filter 'name=coolify-deployment' --format '{{.ID}}';");
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

it('quotes docker container logs arguments', function () {
    expect(dockerContainerLogsCommand('coolify-app', 200, timestamps: true, redirectStderr: true))
        ->toBe("docker logs -n 200 -t 'coolify-app' 2>&1");
});

it('prevents command injection in docker container logs arguments', function () {
    $command = dockerContainerLogsCommand('coolify-app; id #', -5);

    expect($command)
        ->toBe("docker logs -n 1 'coolify-app; id #'")
        ->not->toContain('docker logs -n -5 coolify-app;');
});

it('quotes docker service logs arguments', function () {
    expect(dockerServiceLogsCommand('coolify_proxy', null, timestamps: true))
        ->toBe("docker service logs -t 'coolify_proxy'");
});

it('prevents command injection in docker service logs arguments', function () {
    $command = dockerServiceLogsCommand('coolify_proxy; whoami #', 50);

    expect($command)
        ->toBe("docker service logs -n 50 'coolify_proxy; whoami #'")
        ->not->toContain('docker service logs -n 50 coolify_proxy;');
});

it('quotes docker stop container lists', function () {
    expect(dockerStopContainersCommand(['web-1', 'db-1'], 30))
        ->toBe("docker stop -t 30 'web-1' 'db-1'");
});

it('prevents command injection in docker stop container lists', function () {
    $command = dockerStopContainersCommand(['web-1; id #', 'db-1'], 30);

    expect($command)
        ->toBe("docker stop -t 30 'web-1; id #' 'db-1'")
        ->not->toContain('docker stop -t 30 web-1;');
});

it('quotes docker remove container lists', function () {
    expect(dockerRemoveContainersCommand(['web-1', 'db-1']))
        ->toBe("docker rm -f 'web-1' 'db-1'");
});

it('prevents command injection in docker remove container lists', function () {
    $command = dockerRemoveContainersCommand(['web-1; id #', 'db-1']);

    expect($command)
        ->toBe("docker rm -f 'web-1; id #' 'db-1'")
        ->not->toContain('docker rm -f web-1;');
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

it('quotes docker network connect arguments', function () {
    expect(dockerNetworkConnectCommand('app-network', 'coolify-proxy', 'web-app'))
        ->toBe("docker network connect --alias 'web-app' 'app-network' 'coolify-proxy'");
});

it('prevents command injection in docker network connect arguments', function () {
    $command = dockerNetworkConnectCommand('app-network; id #', 'web; whoami #', 'alias; uname #');

    expect($command)
        ->toBe("docker network connect --alias 'alias; uname #' 'app-network; id #' 'web; whoami #'")
        ->not->toContain('docker network connect --alias alias;');
});

it('quotes docker network create arguments', function () {
    expect(dockerNetworkCreateCommand('app-network', attachable: true, driver: 'overlay'))
        ->toBe("docker network create --driver 'overlay' --attachable 'app-network'");
});

it('prevents command injection in docker network create arguments', function () {
    $command = dockerNetworkCreateCommand('app-network; id #', attachable: true, driver: 'overlay; whoami #');

    expect($command)
        ->toBe("docker network create --driver 'overlay; whoami #' --attachable 'app-network; id #'")
        ->not->toContain('docker network create --driver overlay;');
});

it('quotes docker network inspect arguments', function () {
    expect(dockerNetworkInspectCommand('app-network'))
        ->toBe("docker network inspect 'app-network'");
});

it('prevents command injection in docker network inspect arguments', function () {
    $command = dockerNetworkInspectCommand('app-network; id #');

    expect($command)
        ->toBe("docker network inspect 'app-network; id #'")
        ->not->toContain('docker network inspect app-network;');
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
