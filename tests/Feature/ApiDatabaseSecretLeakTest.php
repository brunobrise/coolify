<?php

use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMysql;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

function apiDatabaseSecretLeakToken(User $user, Team $team, array $abilities): string
{
    session(['currentTeam' => $team]);

    return $user->createToken('database-secret-leak-test', $abilities)->plainTextToken;
}

function apiDatabaseSecretLeakPrivateKey(): string
{
    return <<<'KEY'
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevAAAAJi/QySHv0Mk
hwAAAAtzc2gtZWQyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevA
AAAECBQw4jg1WRT2IGHMncCiZhURCts2s24HoDS0thHnnRKVuGmoeGq/pojrsyP1pszcNV
uZx9iFkCELtxrh31QJ68AAAAEXNhaWxANzZmZjY2ZDJlMmRkAQIDBA==
-----END OPENSSH PRIVATE KEY-----
KEY;
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    config(['app.maintenance.store' => 'array']);
    $this->withoutMiddleware(PreventRequestsDuringMaintenance::class);
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0, 'is_api_enabled' => true]));
    Once::flush();

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
});

test('database list hides mysql and mariadb secrets without sensitive read ability', function () {
    $privateKey = PrivateKey::create([
        'team_id' => $this->team->id,
        'name' => 'database-api-key',
        'private_key' => apiDatabaseSecretLeakPrivateKey(),
    ]);
    $server = Server::factory()->create([
        'team_id' => $this->team->id,
        'private_key_id' => $privateKey->id,
    ]);
    $destination = StandaloneDocker::where('server_id', $server->id)->where('network', 'coolify')->first()
        ?? StandaloneDocker::factory()->create(['server_id' => $server->id]);
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $environment = $project->environments()->first();

    StandaloneMysql::create([
        'uuid' => 'mysql-api-secret',
        'name' => 'mysql-api',
        'mysql_root_password' => 'mysql-root-secret',
        'mysql_user' => 'coolify',
        'mysql_password' => 'mysql-user-secret',
        'mysql_database' => 'app',
        'environment_id' => $environment->id,
        'destination_type' => StandaloneDocker::class,
        'destination_id' => $destination->id,
        'image' => 'mysql:8',
    ]);
    StandaloneMariadb::create([
        'uuid' => 'mariadb-api-secret',
        'name' => 'mariadb-api',
        'mariadb_root_password' => 'mariadb-root-secret',
        'mariadb_user' => 'coolify',
        'mariadb_password' => 'mariadb-user-secret',
        'mariadb_database' => 'app',
        'environment_id' => $environment->id,
        'destination_type' => StandaloneDocker::class,
        'destination_id' => $destination->id,
        'image' => 'mariadb:11',
    ]);

    $token = apiDatabaseSecretLeakToken($this->owner, $this->team, ['read']);

    foreach ([
        'mysql-api-secret' => ['mysql-api', 'mysql-root-secret', 'mysql-user-secret', 'mysql_root_password', 'mysql_password'],
        'mariadb-api-secret' => ['mariadb-api', 'mariadb-root-secret', 'mariadb-user-secret', 'mariadb_root_password', 'mariadb_password'],
    ] as $uuid => [$name, $rootPassword, $userPassword, $rootField, $userField]) {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/databases/{$uuid}");

        $response->assertOk();
        expect($response->getContent())
            ->toContain($name)
            ->not->toContain($rootPassword)
            ->not->toContain($userPassword)
            ->not->toContain($rootField)
            ->not->toContain($userField)
            ->not->toContain('"destination":')
            ->not->toContain('sentinel_token');
    }
});
