<?php

use App\Models\SharedEnvironmentVariable;
use App\Models\Team;
use App\Models\User;
use App\Policies\SharedEnvironmentVariablePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->member->id, ['role' => 'member']);

    $this->owner->load('teams');
    $this->member->load('teams');
    $this->outsider->load('teams');

    $this->sharedVariable = SharedEnvironmentVariable::create([
        'key' => 'SHARED_SECRET',
        'value' => 'secret',
        'type' => 'team',
        'team_id' => $this->team->id,
    ]);

    $this->policy = new SharedEnvironmentVariablePolicy;
});

it('allows any authenticated user to list shared environment variable summaries', function () {
    expect($this->policy->viewAny($this->member))->toBeTrue();
});

it('allows team members to view their team shared environment variable', function () {
    expect($this->policy->view($this->member, $this->sharedVariable))->toBeTrue();
});

it('denies outsiders from viewing shared environment variables', function () {
    expect($this->policy->view($this->outsider, $this->sharedVariable))->toBeFalse();
});

it('allows only current team admins or owners to create shared environment variables', function () {
    session(['currentTeam' => $this->team]);

    expect($this->policy->create($this->owner))->toBeTrue()
        ->and($this->policy->create($this->member))->toBeFalse();
});

it('allows team admins or owners to update and delete shared environment variables', function () {
    expect($this->policy->update($this->owner, $this->sharedVariable))->toBeTrue()
        ->and($this->policy->delete($this->owner, $this->sharedVariable))->toBeTrue()
        ->and($this->policy->manageEnvironment($this->owner, $this->sharedVariable))->toBeTrue();
});

it('denies team members from mutating shared environment variables', function () {
    expect($this->policy->update($this->member, $this->sharedVariable))->toBeFalse()
        ->and($this->policy->delete($this->member, $this->sharedVariable))->toBeFalse()
        ->and($this->policy->manageEnvironment($this->member, $this->sharedVariable))->toBeFalse();
});

it('denies restore and force delete for shared environment variables', function () {
    expect($this->policy->restore($this->owner, $this->sharedVariable))->toBeFalse()
        ->and($this->policy->forceDelete($this->owner, $this->sharedVariable))->toBeFalse();
});
