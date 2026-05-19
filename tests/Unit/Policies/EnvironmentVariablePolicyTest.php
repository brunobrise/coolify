<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Policies\EnvironmentVariablePolicy;
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

    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $environment = Environment::factory()->create(['project_id' => $project->id]);
    $application = Application::factory()->create(['environment_id' => $environment->id]);

    $this->environmentVariable = EnvironmentVariable::create([
        'key' => 'APP_SECRET',
        'value' => 'secret',
        'resourceable_type' => $application->getMorphClass(),
        'resourceable_id' => $application->id,
    ]);

    $this->policy = new EnvironmentVariablePolicy;
});

it('allows authenticated users to list environment variable summaries', function () {
    expect($this->policy->viewAny($this->member))->toBeTrue();
});

it('allows team members to view environment variables through the parent resource', function () {
    expect($this->policy->view($this->member, $this->environmentVariable))->toBeTrue();
});

it('denies outsiders from viewing environment variables', function () {
    expect($this->policy->view($this->outsider, $this->environmentVariable))->toBeFalse();
});

it('allows only current team admins or owners to create environment variables', function () {
    session(['currentTeam' => $this->team]);

    expect($this->policy->create($this->owner))->toBeTrue()
        ->and($this->policy->create($this->member))->toBeFalse();
});

it('allows team admins or owners to mutate environment variables through the parent resource', function () {
    expect($this->policy->update($this->owner, $this->environmentVariable))->toBeTrue()
        ->and($this->policy->delete($this->owner, $this->environmentVariable))->toBeTrue()
        ->and($this->policy->manageEnvironment($this->owner, $this->environmentVariable))->toBeTrue();
});

it('denies team members from mutating environment variables', function () {
    expect($this->policy->update($this->member, $this->environmentVariable))->toBeFalse()
        ->and($this->policy->delete($this->member, $this->environmentVariable))->toBeFalse()
        ->and($this->policy->manageEnvironment($this->member, $this->environmentVariable))->toBeFalse();
});

it('denies access when the parent resource is missing', function () {
    $orphan = EnvironmentVariable::make([
        'key' => 'ORPHAN_SECRET',
        'value' => 'secret',
    ]);

    expect($this->policy->view($this->owner, $orphan))->toBeFalse()
        ->and($this->policy->update($this->owner, $orphan))->toBeFalse();
});
