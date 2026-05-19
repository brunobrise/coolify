<?php

it('quotes base64 file write paths', function () {
    $command = writeBase64FileCommand('/data/coolify/log drains/.env', base64_encode("KEY=value\n"));

    expect($command)
        ->toBe("printf %s 'S0VZPXZhbHVlCg==' | base64 -d | tee -- '/data/coolify/log drains/.env' > /dev/null");
});

it('prevents shell injection in base64 file write paths and payloads', function () {
    $payload = base64_encode("KEY='; id; #\n");
    $command = writeBase64FileCommand('/tmp/file; id #', $payload);

    expect($command)
        ->toBe("printf %s 'S0VZPSc7IGlkOyAjCg==' | base64 -d | tee -- '/tmp/file; id #' > /dev/null")
        ->not->toContain('tee -- /tmp/file;');
});

it('quotes base64 file writes inside docker containers', function () {
    $command = writeBase64FileInDockerCommand('builder; id #', '/app/Dockerfile; whoami #', base64_encode('FROM nginx'));

    expect($command)
        ->toStartWith("docker exec 'builder; id #' bash -c ")
        ->not->toContain('docker exec builder;')
        ->not->toContain('tee -- /app/Dockerfile;');
});
