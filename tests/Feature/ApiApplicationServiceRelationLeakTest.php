<?php

use App\Models\Application;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

function apiApplicationServiceRelationLeakToken(User $user, Team $team, array $abilities): string
{
    session(['currentTeam' => $team]);

    return $user->createToken('application-service-relation-leak-test', $abilities)->plainTextToken;
}

function apiApplicationServiceRelationLeakPrivateKey(): string
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

test('application and service details hide nested infrastructure relations', function () {
    $privateKey = PrivateKey::create([
        'team_id' => $this->team->id,
        'name' => 'app-service-api-key',
        'private_key' => apiApplicationServiceRelationLeakPrivateKey(),
    ]);
    $server = Server::factory()->create([
        'team_id' => $this->team->id,
        'private_key_id' => $privateKey->id,
    ]);
    $destination = StandaloneDocker::where('server_id', $server->id)->where('network', 'coolify')->first()
        ?? StandaloneDocker::factory()->create(['server_id' => $server->id]);
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $environment = $project->environments()->first();

    $application = Application::factory()->create([
        'name' => 'relation-app',
        'environment_id' => $environment->id,
        'destination_type' => StandaloneDocker::class,
        'destination_id' => $destination->id,
        'manual_webhook_secret_github' => 'relation-webhook-secret',
    ]);
    $service = Service::factory()->create([
        'name' => 'relation-service',
        'environment_id' => $environment->id,
        'server_id' => $server->id,
        'destination_type' => StandaloneDocker::class,
        'destination_id' => $destination->id,
        'docker_compose_raw' => 'SERVICE_TOKEN=relation-service-secret',
    ]);

    $token = apiApplicationServiceRelationLeakToken($this->owner, $this->team, ['read']);

    foreach ([
        "/api/v1/applications/{$application->uuid}" => ['relation-app', 'relation-webhook-secret'],
        "/api/v1/services/{$service->uuid}" => ['relation-service', 'relation-service-secret'],
    ] as $uri => [$name, $secret]) {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson($uri);

        $response->assertOk();
        expect($response->getContent())
            ->toContain($name)
            ->not->toContain($secret)
            ->not->toContain('"destination":')
            ->not->toContain('"server":')
            ->not->toContain('sentinel_token');
    }
});
