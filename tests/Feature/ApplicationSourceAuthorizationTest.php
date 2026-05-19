<?php

use App\Livewire\Project\Application\Source;
use App\Models\Application;
use App\Models\Environment;
use App\Models\GithubApp;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('rejects changing an application to another teams github source', function () {
    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $owner->load('teams');

    $otherTeam = Team::factory()->create();
    $otherGithubApp = GithubApp::create([
        'name' => 'Other Team GitHub App',
        'api_url' => 'https://api.github.com',
        'html_url' => 'https://github.com',
        'team_id' => $otherTeam->id,
        'is_system_wide' => false,
        'is_public' => false,
    ]);

    $project = Project::factory()->create(['team_id' => $team->id]);
    $environment = Environment::factory()->create(['project_id' => $project->id]);
    $application = Application::factory()->create([
        'environment_id' => $environment->id,
        'source_id' => null,
        'source_type' => null,
    ]);

    $this->actingAs($owner);
    session(['currentTeam' => $team]);

    Livewire::test(Source::class, ['application' => $application])
        ->call('changeSource', $otherGithubApp->id, GithubApp::class)
        ->assertDispatched('error');

    $application->refresh();
    expect($application->source_id)->toBeNull()
        ->and($application->source_type)->toBeNull();
});
