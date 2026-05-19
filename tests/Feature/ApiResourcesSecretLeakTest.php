<?php

use App\Models\Application;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneDocker;
use App\Models\StandaloneMariadb;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

function apiResourcesSecretLeakToken(User $user, Team $team, array $abilities): string
{
    session(['currentTeam' => $team]);

    return $user->createToken('resources-secret-leak-test', $abilities)->plainTextToken;
}

function apiResourcesSecretLeakPrivateKey(): string
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

test('resources list hides secrets and nested infrastructure relations without sensitive read ability', function () {
    $privateKey = PrivateKey::create([
        'team_id' => $this->team->id,
        'name' => 'resources-api-key',
        'private_key' => apiResourcesSecretLeakPrivateKey(),
    ]);
    $server = Server::factory()->create([
        'team_id' => $this->team->id,
        'private_key_id' => $privateKey->id,
    ]);
    $destination = StandaloneDocker::where('server_id', $server->id)->where('network', 'coolify')->first()
        ?? StandaloneDocker::factory()->create(['server_id' => $server->id]);
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $environment = $project->environments()->first();

    Application::factory()->create([
        'name' => 'resources-app',
        'environment_id' => $environment->id,
        'destination_type' => StandaloneDocker::class,
        'destination_id' => $destination->id,
        'git_full_url' => 'https://user:repo-token-secret@example.com/team/repo.git',
        'manual_webhook_secret_github' => 'github-webhook-secret',
        'http_basic_auth_password' => 'basic-auth-secret',
        'docker_compose_raw' => 'services: {app: {environment: [APP_SECRET=compose-secret]}}',
    ]);
    Service::factory()->create([
        'name' => 'resources-service',
        'environment_id' => $environment->id,
        'server_id' => $server->id,
        'destination_type' => StandaloneDocker::class,
        'destination_id' => $destination->id,
        'docker_compose_raw' => 'SERVICE_PASSWORD=service-compose-secret',
    ]);
    StandaloneMariadb::create([
        'uuid' => 'resources-mariadb',
        'name' => 'resources-db',
        'mariadb_root_password' => 'resources-db-root-secret',
        'mariadb_user' => 'coolify',
        'mariadb_password' => 'resources-db-user-secret',
        'mariadb_database' => 'app',
        'environment_id' => $environment->id,
        'destination_type' => StandaloneDocker::class,
        'destination_id' => $destination->id,
        'image' => 'mariadb:11',
    ]);

    $token = apiResourcesSecretLeakToken($this->owner, $this->team, ['read']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/resources');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('resources-app')
        ->toContain('resources-service')
        ->toContain('resources-db')
        ->not->toContain('repo-token-secret')
        ->not->toContain('github-webhook-secret')
        ->not->toContain('basic-auth-secret')
        ->not->toContain('compose-secret')
        ->not->toContain('service-compose-secret')
        ->not->toContain('resources-db-root-secret')
        ->not->toContain('resources-db-user-secret')
        ->not->toContain('internal_db_url')
        ->not->toContain('external_db_url')
        ->not->toContain('"destination":')
        ->not->toContain('sentinel_token');
});
