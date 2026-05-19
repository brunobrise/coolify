<?php

use App\Livewire\Project\Shared\UploadConfig;
use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));

    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->user->teams()->attach($this->team, ['role' => 'owner']);

    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);
});

function applicationForTeam(Team $team): Application
{
    $project = Project::factory()->create(['team_id' => $team->id]);
    $environment = Environment::factory()->create(['project_id' => $project->id]);

    return Application::factory()->create(['environment_id' => $environment->id]);
}

function uploadConfigPayload(array $overrides = []): string
{
    return json_encode(array_merge([
        'build_pack' => 'nixpacks',
        'base_directory' => '/app',
        'publish_directory' => '/dist',
        'ports_exposes' => '8080',
        'settings' => [
            'is_static' => true,
        ],
    ], $overrides));
}

it('updates config for an application owned by the current team', function () {
    $application = applicationForTeam($this->team);

    Livewire::test(UploadConfig::class)
        ->set('applicationId', $application->id)
        ->set('config', uploadConfigPayload())
        ->call('uploadConfig')
        ->assertDispatched('success');

    expect($application->fresh())
        ->base_directory->toBe('/app')
        ->publish_directory->toBe('/dist')
        ->ports_exposes->toBe('8080')
        ->and($application->settings()->first()->is_static)->toBeTrue();
});

it('cannot update config for an application from another team', function () {
    $otherTeam = Team::factory()->create();
    $otherApplication = applicationForTeam($otherTeam);

    Livewire::test(UploadConfig::class)
        ->set('applicationId', $otherApplication->id)
        ->set('config', uploadConfigPayload(['ports_exposes' => '9000']))
        ->call('uploadConfig')
        ->assertStatus(404);

    expect($otherApplication->fresh()->ports_exposes)->toBe('3000');
});
