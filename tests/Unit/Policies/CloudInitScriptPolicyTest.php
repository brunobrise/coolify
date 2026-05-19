<?php

use App\Models\CloudInitScript;
use App\Models\Team;
use App\Models\User;
use App\Policies\CloudInitScriptPolicy;

function cloudInitPolicyUser(array $rolesByTeam, int $currentTeamId): User
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

function cloudInitScriptForTeam(int $teamId): CloudInitScript
{
    $script = new CloudInitScript;
    $script->team_id = $teamId;

    return $script;
}

it('allows team admins to manage cloud init scripts for their team', function () {
    $policy = new CloudInitScriptPolicy;
    $user = cloudInitPolicyUser([1 => 'admin'], 1);
    $script = cloudInitScriptForTeam(1);

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->view($user, $script))->toBeTrue()
        ->and($policy->update($user, $script))->toBeTrue()
        ->and($policy->delete($user, $script))->toBeTrue();
});

it('denies team members from managing cloud init scripts', function () {
    $policy = new CloudInitScriptPolicy;
    $user = cloudInitPolicyUser([1 => 'member'], 1);
    $script = cloudInitScriptForTeam(1);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->view($user, $script))->toBeFalse()
        ->and($policy->update($user, $script))->toBeFalse()
        ->and($policy->delete($user, $script))->toBeFalse();
});

it('denies admins from managing another teams cloud init scripts', function () {
    $policy = new CloudInitScriptPolicy;
    $user = cloudInitPolicyUser([1 => 'admin', 2 => 'member'], 1);
    $script = cloudInitScriptForTeam(2);

    expect($policy->view($user, $script))->toBeFalse()
        ->and($policy->update($user, $script))->toBeFalse()
        ->and($policy->delete($user, $script))->toBeFalse();
});

it('denies restoring cloud init scripts', function () {
    $policy = new CloudInitScriptPolicy;
    $user = cloudInitPolicyUser([1 => 'owner'], 1);
    $script = cloudInitScriptForTeam(1);

    expect($policy->restore($user, $script))->toBeFalse()
        ->and($policy->forceDelete($user, $script))->toBeFalse();
});
