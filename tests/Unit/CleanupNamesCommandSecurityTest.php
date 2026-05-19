<?php

use App\Console\Commands\CleanupNames;

it('quotes database backup command arguments', function () {
    $command = new CleanupNames;
    $method = new ReflectionMethod(CleanupNames::class, 'pgDumpCommand');
    $method->setAccessible(true);

    $shell = $method->invoke($command, [
        'host' => "localhost'; id; #",
        'port' => '5432',
        'username' => "coolify'; whoami; #",
        'database' => 'coolify',
    ], "/tmp/name backups/backup'; cat /etc/passwd; #.sql");

    expect($shell)
        ->toBe("pg_dump -h 'localhost'\\''; id; #' -p '5432' -U 'coolify'\\''; whoami; #' -d 'coolify' > '/tmp/name backups/backup'\\''; cat /etc/passwd; #.sql'")
        ->not->toContain("localhost'; id;")
        ->not->toContain("backup'; cat");
});
