<?php

dataset('database_start_actions', [
    'clickhouse' => ['app/Actions/Database/StartClickhouse.php'],
    'dragonfly' => ['app/Actions/Database/StartDragonfly.php'],
    'keydb' => ['app/Actions/Database/StartKeydb.php'],
    'mariadb' => ['app/Actions/Database/StartMariadb.php'],
    'mongodb' => ['app/Actions/Database/StartMongodb.php'],
    'mysql' => ['app/Actions/Database/StartMysql.php'],
    'postgresql' => ['app/Actions/Database/StartPostgresql.php'],
    'redis' => ['app/Actions/Database/StartRedis.php'],
]);

it('does not interpolate database configuration directories into shell commands', function (string $path) {
    $source = file_get_contents(__DIR__.'/../../'.$path);

    expect($source)
        ->not->toContain('"mkdir -p $this->configuration_dir')
        ->not->toContain('"rm -rf $this->configuration_dir')
        ->not->toContain('docker compose -f $this->configuration_dir')
        ->not->toContain('tee $this->configuration_dir')
        ->not->toContain('> $this->configuration_dir/README.md');
})->with('database_start_actions');
