<?php

use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

function apiServerSecretLeakToken(User $user, Team $team, array $abilities): string
{
    session(['currentTeam' => $team]);

    return $user->createToken('server-secret-leak-test', $abilities)->plainTextToken;
}

function apiServerSecretLeakPrivateKey(): string
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

test('server detail hides infrastructure secrets without sensitive read ability', function () {
    $privateKey = PrivateKey::create([
        'team_id' => $this->team->id,
        'name' => 'server-api-key',
        'private_key' => apiServerSecretLeakPrivateKey(),
    ]);
    $server = Server::factory()->create([
        'team_id' => $this->team->id,
        'private_key_id' => $privateKey->id,
    ]);
    $server->settings->forceFill([
        'logdrain_newrelic_license_key' => 'newrelic-license-secret',
        'logdrain_axiom_api_key' => 'axiom-api-secret',
        'logdrain_custom_config' => 'Authorization bearer custom-log-secret',
    ])->save();

    $token = apiServerSecretLeakToken($this->owner, $this->team, ['read']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson("/api/v1/servers/{$server->uuid}");

    $response->assertOk();
    expect($response->getContent())
        ->toContain($server->uuid)
        ->not->toContain('private_key_id')
        ->not->toContain('cloud_provider_token_id')
        ->not->toContain('sentinel_token')
        ->not->toContain('logdrain_newrelic_license_key')
        ->not->toContain('newrelic-license-secret')
        ->not->toContain('logdrain_axiom_api_key')
        ->not->toContain('axiom-api-secret')
        ->not->toContain('"logdrain_custom_config":')
        ->not->toContain('custom-log-secret');
});
