<?php

use App\Models\CloudProviderToken;
use App\Models\Team;
use App\Models\User;
use App\Policies\CloudProviderTokenPolicy;

function cloudTokenPolicyUser(array $rolesByTeam, int $currentTeamId): User
{
    $currentTeam = new Team;
    $currentTeam->id = $currentTeamId;

    $teams = collect(array_map(
        fn (int $teamId, string $role) => (object) ['id' => $teamId, 'pivot' => (object) ['role' => $role]],
        array_keys($rolesByTeam),
        $rolesByTeam,
    ));

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);
    $user->shouldReceive('currentTeam')->andReturn($currentTeam);
    $user->shouldReceive('isAdminOfTeam')->andReturnUsing(function (int $teamId) use ($rolesByTeam) {
        return in_array($rolesByTeam[$teamId] ?? null, ['admin', 'owner'], true);
    });
    $user->shouldReceive('canAccessSystemResources')->andReturnUsing(function () use ($rolesByTeam) {
        return in_array($rolesByTeam[0] ?? null, ['admin', 'owner'], true);
    });

    return $user;
}

function cloudProviderTokenForTeam(int $teamId): CloudProviderToken
{
    $token = new CloudProviderToken;
    $token->team_id = $teamId;

    return $token;
}

it('allows team admins to manage cloud provider tokens for their team', function () {
    $policy = new CloudProviderTokenPolicy;
    $user = cloudTokenPolicyUser([1 => 'admin'], 1);
    $token = cloudProviderTokenForTeam(1);

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->view($user, $token))->toBeTrue()
        ->and($policy->update($user, $token))->toBeTrue()
        ->and($policy->delete($user, $token))->toBeTrue();
});

it('denies team members from managing cloud provider tokens', function () {
    $policy = new CloudProviderTokenPolicy;
    $user = cloudTokenPolicyUser([1 => 'member'], 1);
    $token = cloudProviderTokenForTeam(1);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->view($user, $token))->toBeFalse()
        ->and($policy->update($user, $token))->toBeFalse()
        ->and($policy->delete($user, $token))->toBeFalse();
});

it('denies admins from managing another teams cloud provider tokens', function () {
    $policy = new CloudProviderTokenPolicy;
    $user = cloudTokenPolicyUser([1 => 'admin', 2 => 'member'], 1);
    $token = cloudProviderTokenForTeam(2);

    expect($policy->view($user, $token))->toBeFalse()
        ->and($policy->update($user, $token))->toBeFalse()
        ->and($policy->delete($user, $token))->toBeFalse();
});

it('denies restoring cloud provider tokens', function () {
    $policy = new CloudProviderTokenPolicy;
    $user = cloudTokenPolicyUser([1 => 'owner'], 1);
    $token = cloudProviderTokenForTeam(1);

    expect($policy->restore($user, $token))->toBeFalse()
        ->and($policy->forceDelete($user, $token))->toBeFalse();
});
