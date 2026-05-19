<?php

use App\Livewire\Project\Database\Import;

function importRestoreComponent(string $morphClass): Import
{
    $database = Mockery::mock($morphClass);
    $database->shouldReceive('getMorphClass')->andReturn($morphClass);

    return new class($database) extends Import
    {
        public function __construct(private mixed $testResource) {}

        protected function restoreResource(): mixed
        {
            return $this->testResource;
        }
    };
}

test('buildRestoreCommand handles PostgreSQL without dumpAll', function () {
    $component = importRestoreComponent('App\Models\StandalonePostgresql');
    $component->dumpAll = false;
    $component->postgresqlRestoreCommand = 'pg_restore -U ${POSTGRES_USER} -d ${POSTGRES_DB:-${POSTGRES_USER:-postgres}} --clean --verbose';

    $result = $component->buildRestoreCommand('/tmp/test.dump');

    expect($result)->toContain('pg_restore');
    expect($result)->toContain('--clean --verbose');
    expect($result)->toContain('/tmp/test.dump');
});

test('buildRestoreCommand handles PostgreSQL with dumpAll', function () {
    $component = importRestoreComponent('App\Models\StandalonePostgresql');
    $component->dumpAll = true;
    $component->postgresqlRestoreCommand = 'pg_restore -U ${POSTGRES_USER} -d ${POSTGRES_DB:-${POSTGRES_USER:-postgres}}; id';

    $result = $component->buildRestoreCommand('/tmp/test.dump');

    expect($result)->toContain("gunzip -cf '/tmp/test.dump'");
    expect($result)->toContain('psql -U ${POSTGRES_USER}');
    expect($result)->not->toContain('; id');
});

test('buildRestoreCommand handles MySQL without dumpAll', function () {
    $component = importRestoreComponent('App\Models\StandaloneMysql');
    $component->dumpAll = false;
    $component->mysqlRestoreCommand = 'mysql -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE; id';

    $result = $component->buildRestoreCommand('/tmp/test.dump');

    expect($result)->toContain('mysql -u $MYSQL_USER');
    expect($result)->toContain("< '/tmp/test.dump'");
    expect($result)->not->toContain('; id');
});

test('buildRestoreCommand handles MariaDB without dumpAll', function () {
    $component = importRestoreComponent('App\Models\StandaloneMariadb');
    $component->dumpAll = false;
    $component->mariadbRestoreCommand = 'mariadb -u $MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE; id';

    $result = $component->buildRestoreCommand('/tmp/test.dump');

    expect($result)->toContain('mariadb -u $MARIADB_USER');
    expect($result)->toContain("< '/tmp/test.dump'");
    expect($result)->not->toContain('; id');
});

test('buildRestoreCommand handles MongoDB', function () {
    $component = importRestoreComponent('App\Models\StandaloneMongodb');
    $component->dumpAll = false;
    $component->mongodbRestoreCommand = 'mongorestore --archive=; id';

    $result = $component->buildRestoreCommand('/tmp/test.dump');

    expect($result)->toContain('mongorestore');
    expect($result)->toContain('/tmp/test.dump');
    expect($result)->not->toContain('; id');
});

test('buildRestoreCommand rejects unsafe PostgreSQL custom command', function () {
    $component = importRestoreComponent('App\Models\StandalonePostgresql');
    $component->dumpAll = false;
    $component->postgresqlRestoreCommand = 'pg_restore -U ${POSTGRES_USER} -d ${POSTGRES_DB:-${POSTGRES_USER:-postgres}}; id';

    expect(fn () => $component->buildRestoreCommand('/tmp/test.dump'))
        ->toThrow(Exception::class, 'Invalid PostgreSQL restore command');
});

test('buildRestoreCommand rejects unsafe PostgreSQL restore flag', function () {
    $component = importRestoreComponent('App\Models\StandalonePostgresql');
    $component->dumpAll = false;
    $component->postgresqlRestoreCommand = 'pg_restore -U ${POSTGRES_USER} -d ${POSTGRES_DB:-${POSTGRES_USER:-postgres}} --file=/tmp/out';

    expect(fn () => $component->buildRestoreCommand('/tmp/test.dump'))
        ->toThrow(Exception::class, 'Invalid PostgreSQL restore flag');
});
