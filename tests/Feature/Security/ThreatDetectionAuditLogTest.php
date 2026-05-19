<?php

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    config(['app.maintenance.store' => 'array']);
    $this->withoutMiddleware(PreventRequestsDuringMaintenance::class);
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0, 'is_api_enabled' => true]));
    Once::flush();
});

function makeThreatAuditTeamUser(): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);
    session(['currentTeam' => $team]);
    test()->actingAs($user);

    return [$team, $user];
}

function makeThreatAuditApiToken(User $user, Team $team, array $abilities = ['root']): string
{
    $token = $user->createToken('audit-test', $abilities);
    DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->update([
        'team_id' => $team->id,
    ]);
    auth()->guard()->logout();
    test()->flushSession();

    return $token->plainTextToken;
}

describe('threat-detection audit logging', function () {
    test('missing bearer token logs api.auth.unauthenticated', function () {
        $auditChannel = Mockery::mock();
        $auditChannel->shouldReceive('warning')
            ->atLeast()
            ->once()
            ->with('api.auth.unauthenticated', Mockery::any());

        Log::shouldReceive('channel')->with('audit')->andReturn($auditChannel);
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(401);
    });

    test('expired bearer token logs api.auth.unauthenticated', function () {
        [$team, $user] = makeThreatAuditTeamUser();
        $token = $user->createToken('expired-audit', ['read'], now()->subDay());
        DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->update([
            'team_id' => $team->id,
        ]);
        auth()->guard()->logout();
        $this->flushSession();

        $auditChannel = Mockery::mock();
        $auditChannel->shouldReceive('warning')
            ->atLeast()
            ->once()
            ->with('api.auth.unauthenticated', Mockery::any());

        Log::shouldReceive('channel')->with('audit')->andReturn($auditChannel);
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->getJson('/api/v1/projects');

        $response->assertStatus(401);
    });

    test('read-only token hitting write endpoint logs api.auth.ability_denied', function () {
        [$team, $user] = makeThreatAuditTeamUser();
        $readToken = makeThreatAuditApiToken($user, $team, ['read']);

        $auditChannel = Mockery::mock();
        $auditChannel->shouldReceive('warning')
            ->atLeast()
            ->once()
            ->with('api.auth.ability_denied', Mockery::on(function ($ctx) {
                return in_array('write', $ctx['required_abilities'] ?? [], true);
            }));

        Log::shouldReceive('channel')->with('audit')->andReturn($auditChannel);
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$readToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/projects', [
            'name' => 'should-fail',
        ]);

        $response->assertStatus(403);
    });

    test('sentinel push without Authorization logs token_missing', function () {
        $auditChannel = Mockery::mock();
        $auditChannel->shouldReceive('warning')
            ->atLeast()
            ->once()
            ->with('webhook.sentinel.signature_failed', Mockery::on(function ($ctx) {
                return $ctx['reason'] === 'token_missing';
            }));

        Log::shouldReceive('channel')->with('audit')->andReturn($auditChannel);
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $response = $this->postJson('/api/v1/sentinel/push', []);

        $response->assertStatus(401);
    });

    test('sentinel push with un-decryptable bearer logs decrypt_failed', function () {
        $auditChannel = Mockery::mock();
        $auditChannel->shouldReceive('warning')
            ->atLeast()
            ->once()
            ->with('webhook.sentinel.signature_failed', Mockery::on(function ($ctx) {
                return $ctx['reason'] === 'decrypt_failed';
            }));

        Log::shouldReceive('channel')->with('audit')->andReturn($auditChannel);
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer not-a-valid-encrypted-payload',
        ])->postJson('/api/v1/sentinel/push', []);

        $response->assertStatus(401);
    });
});
