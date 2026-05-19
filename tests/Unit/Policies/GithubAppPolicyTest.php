<?php

use App\Models\GithubApp;
use App\Models\Team;
use App\Models\User;
use App\Policies\GithubAppPolicy;
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

    $this->githubApp = GithubApp::create([
        'name' => 'Team GitHub App',
        'api_url' => 'https://api.github.com',
        'html_url' => 'https://github.com',
        'team_id' => $this->team->id,
        'is_system_wide' => false,
        'is_public' => false,
    ]);

    $this->systemGithubApp = GithubApp::create([
        'name' => 'System GitHub App',
        'api_url' => 'https://api.github.com',
        'html_url' => 'https://github.com',
        'team_id' => $this->team->id,
        'is_system_wide' => true,
        'is_public' => false,
    ]);

    $this->policy = new GithubAppPolicy;
});

it('allows authenticated users to list github app summaries', function () {
    expect($this->policy->viewAny($this->member))->toBeTrue();
});

it('allows team members to view their team github app summary', function () {
    expect($this->policy->view($this->member, $this->githubApp))->toBeTrue();
});

it('denies outsiders from viewing private team github apps', function () {
    expect($this->policy->view($this->outsider, $this->githubApp))->toBeFalse();
});

it('allows system-wide github apps to appear in source lists', function () {
    expect($this->policy->view($this->outsider, $this->systemGithubApp))->toBeTrue();
});

it('allows only current team admins or owners to create github apps', function () {
    session(['currentTeam' => $this->team]);

    expect($this->policy->create($this->owner))->toBeTrue()
        ->and($this->policy->create($this->member))->toBeFalse();
});

it('allows team admins or owners to update and delete team github apps', function () {
    expect($this->policy->update($this->owner, $this->githubApp))->toBeTrue()
        ->and($this->policy->delete($this->owner, $this->githubApp))->toBeTrue();
});

it('denies team members from mutating team github apps', function () {
    expect($this->policy->update($this->member, $this->githubApp))->toBeFalse()
        ->and($this->policy->delete($this->member, $this->githubApp))->toBeFalse();
});

it('allows system-wide github apps to be used by current team admins', function () {
    session(['currentTeam' => $this->team]);

    expect($this->policy->useForDeployment($this->owner, $this->systemGithubApp))->toBeTrue()
        ->and($this->policy->useForDeployment($this->member, $this->systemGithubApp))->toBeFalse();
});

it('allows only root team admins or owners to mutate system-wide github apps', function () {
    $rootTeam = new Team([
        'name' => 'Root Team',
        'personal_team' => true,
    ]);
    $rootTeam->id = 0;
    $rootTeam->save();

    $this->owner->teams()->syncWithoutDetaching([$rootTeam->id => ['role' => 'owner']]);
    $this->owner->load('teams');

    expect($this->policy->update($this->owner, $this->systemGithubApp))->toBeTrue()
        ->and($this->policy->delete($this->owner, $this->systemGithubApp))->toBeTrue()
        ->and($this->policy->update($this->member, $this->systemGithubApp))->toBeFalse()
        ->and($this->policy->delete($this->member, $this->systemGithubApp))->toBeFalse();
});

it('denies restore and force delete of github apps', function () {
    expect($this->policy->restore($this->owner, $this->githubApp))->toBeFalse()
        ->and($this->policy->forceDelete($this->owner, $this->githubApp))->toBeFalse();
});
