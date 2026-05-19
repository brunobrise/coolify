<?php

use App\Models\S3Storage;
use App\Models\Team;
use App\Models\User;
use App\Policies\S3StoragePolicy;

function s3PolicyUser(array $rolesByTeam): User
{
    $currentTeam = new Team;
    $currentTeam->id = array_key_first($rolesByTeam);

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

function s3PolicyStorage(int $teamId): S3Storage
{
    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn($teamId);
    $storage->team_id = $teamId;

    return $storage;
}

it('allows team member to view S3 storage from their team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->view(s3PolicyUser([1 => 'member']), s3PolicyStorage(1)))->toBeTrue();
});

it('denies team member to view S3 storage from another team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->view(s3PolicyUser([1 => 'owner']), s3PolicyStorage(2)))->toBeFalse();
});

it('allows team admin to update S3 storage from their team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->update(s3PolicyUser([1 => 'admin']), s3PolicyStorage(1)))->toBeTrue();
});

it('denies team member to update S3 storage from their team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->update(s3PolicyUser([1 => 'member']), s3PolicyStorage(1)))->toBeFalse();
});

it('denies team member to update S3 storage from another team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->update(s3PolicyUser([1 => 'admin']), s3PolicyStorage(2)))->toBeFalse();
});

it('allows team owner to delete S3 storage from their team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->delete(s3PolicyUser([1 => 'owner']), s3PolicyStorage(1)))->toBeTrue();
});

it('denies team member to delete S3 storage from their team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->delete(s3PolicyUser([1 => 'member']), s3PolicyStorage(1)))->toBeFalse();
});

it('denies team member to delete S3 storage from another team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->delete(s3PolicyUser([1 => 'owner']), s3PolicyStorage(2)))->toBeFalse();
});

it('allows team admin to create S3 storage', function () {
    $policy = new S3StoragePolicy;

    expect($policy->create(s3PolicyUser([1 => 'admin'])))->toBeTrue();
});

it('denies team member to create S3 storage', function () {
    $policy = new S3StoragePolicy;

    expect($policy->create(s3PolicyUser([1 => 'member'])))->toBeFalse();
});

it('allows team owner to validate connection of S3 storage from their team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->validateConnection(s3PolicyUser([1 => 'owner']), s3PolicyStorage(1)))->toBeTrue();
});

it('denies team member to validate connection of S3 storage from their team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->validateConnection(s3PolicyUser([1 => 'member']), s3PolicyStorage(1)))->toBeFalse();
});

it('denies team member to validate connection of S3 storage from another team', function () {
    $policy = new S3StoragePolicy;

    expect($policy->validateConnection(s3PolicyUser([1 => 'admin']), s3PolicyStorage(2)))->toBeFalse();
});
