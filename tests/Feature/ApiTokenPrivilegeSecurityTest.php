<?php

use App\Livewire\Security\ApiTokens;
use App\Models\InstanceSettings;
use App\Models\KubernetesCluster;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function apiPrivilegePrivateKey(): string
{
    return '-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevAAAAJi/QySHv0Mk
hwAAAAtzc2gtZWQyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevA
AAAECBQw4jg1WRT2IGHMncCiZhURCts2s24HoDS0thHnnRKVuGmoeGq/pojrsyP1pszcNV
uZx9iFkCELtxrh31QJ68AAAAEXNhaWxANzZmZjY2ZDJlMmRkAQIDBA==
-----END OPENSSH PRIVATE KEY-----';
}

function apiPrivilegeToken(User $user, Team $team, array $abilities): string
{
    session(['currentTeam' => $team]);

    return $user->createToken('security-test', $abilities)->plainTextToken;
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    config(['app.maintenance.store' => 'array']);
    $this->withoutMiddleware(PreventRequestsDuringMaintenance::class);
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0, 'is_api_enabled' => true]));
    Once::flush();

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->member->id, ['role' => 'member']);

    PrivateKey::create([
        'team_id' => $this->team->id,
        'name' => 'Team deploy key',
        'private_key' => apiPrivilegePrivateKey(),
    ]);
});

it('does not expose sensitive API fields to member read sensitive tokens', function () {
    $token = apiPrivilegeToken($this->member, $this->team, ['read', 'read:sensitive']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/security/keys');

    $response->assertOk();
    expect($response->json('0'))->not->toHaveKey('private_key');
});

it('exposes sensitive API fields to owner read sensitive tokens', function () {
    $token = apiPrivilegeToken($this->owner, $this->team, ['read', 'read:sensitive']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/security/keys');

    $response->assertOk();
    expect($response->json('0.private_key'))->toContain('BEGIN OPENSSH PRIVATE KEY');
});

it('rejects member write tokens even if they already exist', function () {
    $token = apiPrivilegeToken($this->member, $this->team, ['write']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/projects', [
        'name' => 'Blocked Project',
    ]);

    $response->assertForbidden();
});

it('rejects member root tokens even if they already exist', function () {
    $token = apiPrivilegeToken($this->member, $this->team, ['root']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/projects');

    $response->assertForbidden();
});

it('rejects privileged API tokens after the user is demoted', function () {
    $token = apiPrivilegeToken($this->owner, $this->team, ['root']);
    $this->team->members()->updateExistingPivot($this->owner->id, ['role' => 'member']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/projects');

    $response->assertForbidden();
});

it('hides sensitive API fields after the token user is demoted', function () {
    $token = apiPrivilegeToken($this->owner, $this->team, ['read', 'read:sensitive']);
    $this->team->members()->updateExistingPivot($this->owner->id, ['role' => 'member']);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/security/keys');

    $response->assertOk();
    expect($response->json('0'))->not->toHaveKey('private_key');
});

it('rejects tokens after the user is removed from the token team', function () {
    $token = apiPrivilegeToken($this->member, $this->team, ['read']);
    $this->team->members()->detach($this->member->id);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/projects');

    $response->assertForbidden()
        ->assertJsonPath('message', 'Token team access is no longer valid.');
});

it('rejects session authenticated API requests without a personal access token team id', function () {
    session(['currentTeam' => $this->team]);
    Sanctum::actingAs($this->owner->fresh(), ['read']);

    $response = $this->withSession(['currentTeam' => $this->team])->getJson('/api/v1/projects');

    $response->assertForbidden();
});

it('rejects session authenticated root ability for team members', function () {
    session(['currentTeam' => $this->team]);
    Sanctum::actingAs($this->member->fresh(), ['root']);

    $response = $this->withSession(['currentTeam' => $this->team])->getJson('/api/v1/projects');

    $response->assertForbidden();
});

it('does not treat invalid team ids as the root team', function () {
    $rootTeam = new Team([
        'name' => 'Root Team',
        'personal_team' => true,
    ]);
    $rootTeam->id = 0;
    $rootTeam->save();
    $this->owner->teams()->syncWithoutDetaching([$rootTeam->id => ['role' => 'owner']]);
    $owner = $this->owner->fresh();

    expect(apiUserBelongsToTeam($owner, false))->toBeFalse()
        ->and(apiUserCanUsePrivilegedAbilitiesForTeam($owner, false))->toBeFalse()
        ->and(apiNormalizeTeamId(false))->toBeNull();
});

it('prevents members from creating read sensitive tokens in the UI', function () {
    session(['currentTeam' => $this->team]);
    $this->actingAs($this->member);

    Livewire::test(ApiTokens::class)
        ->set('description', 'member-sensitive')
        ->set('permissions', ['read', 'read:sensitive'])
        ->call('addNewToken');

    expect($this->member->tokens()->where('name', 'member-sensitive')->exists())->toBeFalse();
});

it('prevents users from managing another users api tokens', function () {
    session(['currentTeam' => $this->team]);
    $token = $this->owner->createToken('owner-token', ['read']);

    expect($this->member->can('view', $token->accessToken))->toBeFalse()
        ->and($this->member->can('update', $token->accessToken))->toBeFalse()
        ->and($this->member->can('delete', $token->accessToken))->toBeFalse()
        ->and($this->owner->can('delete', $token->accessToken))->toBeTrue();
});

it('limits kubernetes destination mutation to team admins and owners', function () {
    session(['currentTeam' => $this->team]);
    $server = Server::factory()->create(['team_id' => $this->team->id]);
    $cluster = KubernetesCluster::factory()->create(['server_id' => $server->id]);

    expect($this->member->can('create', KubernetesCluster::class))->toBeFalse()
        ->and($this->owner->can('create', KubernetesCluster::class))->toBeTrue()
        ->and($this->member->can('update', $cluster))->toBeFalse()
        ->and($this->owner->can('update', $cluster))->toBeTrue()
        ->and($this->member->can('delete', $cluster))->toBeFalse()
        ->and($this->owner->can('delete', $cluster))->toBeTrue();
});
