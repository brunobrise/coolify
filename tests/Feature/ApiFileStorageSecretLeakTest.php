<?php

use App\Models\Application;
use App\Models\InstanceSettings;
use App\Models\LocalFileVolume;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

function apiFileStorageSecretLeakToken(User $user, Team $team, array $abilities): string
{
    session(['currentTeam' => $team]);

    return $user->createToken('file-storage-secret-leak-test', $abilities)->plainTextToken;
}

function apiFileStorageSecretLeakPrivateKey(): string
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

test('file storage list hides content without sensitive read ability', function () {
    $privateKey = PrivateKey::create([
        'team_id' => $this->team->id,
        'name' => 'file-storage-api-key',
        'private_key' => apiFileStorageSecretLeakPrivateKey(),
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
        'name' => 'file-storage-app',
        'environment_id' => $environment->id,
        'destination_type' => StandaloneDocker::class,
        'destination_id' => $destination->id,
    ]);

    LocalFileVolume::withoutEvents(fn () => LocalFileVolume::forceCreate([
        'uuid' => 'file-storage-secret',
        'fs_path' => '/app/.env',
        'mount_path' => '/app/.env',
        'content' => 'FILE_STORAGE_SECRET=super-sensitive-value',
        'resource_type' => Application::class,
        'resource_id' => $application->id,
    ]));

    $token = apiFileStorageSecretLeakToken($this->owner, $this->team, ['read']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/applications/{$application->uuid}/storages");

    $response->assertOk();
    expect($response->getContent())
        ->toContain('file-storage-secret')
        ->not->toContain('FILE_STORAGE_SECRET')
        ->not->toContain('super-sensitive-value')
        ->not->toContain('"content":');
});
