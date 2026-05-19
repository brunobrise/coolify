<?php

use App\Livewire\Project\Shared\ScheduledTask\Add as AddScheduledTask;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Project;
use App\Models\ScheduledTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->user->teams()->attach($this->team, ['role' => 'owner']);

    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);
});

function scheduledTaskApplicationForTeam(Team $team): Application
{
    $project = Project::factory()->create(['team_id' => $team->id]);
    $environment = Environment::factory()->create(['project_id' => $project->id]);

    return Application::factory()->create(['environment_id' => $environment->id]);
}

it('adds a scheduled task for an application owned by the current team', function () {
    $application = scheduledTaskApplicationForTeam($this->team);

    Livewire::test(AddScheduledTask::class, [
        'id' => (string) $application->id,
        'type' => 'application',
        'containerNames' => collect(['app']),
    ])
        ->set('name', 'Nightly task')
        ->set('command', 'php artisan schedule:run')
        ->set('frequency', '0 2 * * *')
        ->set('timeout', 300)
        ->call('submit')
        ->assertDispatched('success');

    $task = ScheduledTask::where('application_id', $application->id)->first();

    expect($task)
        ->not->toBeNull()
        ->team_id->toBe($this->team->id);
});

it('cannot mount scheduled task add for an application from another team', function () {
    $otherTeam = Team::factory()->create();
    $otherApplication = scheduledTaskApplicationForTeam($otherTeam);

    Livewire::test(AddScheduledTask::class, [
        'id' => (string) $otherApplication->id,
        'type' => 'application',
        'containerNames' => collect(['app']),
    ]);
})->throws(ModelNotFoundException::class);

it('keeps scheduled task persistence internal to the submit flow', function () {
    $method = new ReflectionMethod(AddScheduledTask::class, 'saveScheduledTask');

    expect($method->isPrivate())->toBeTrue();
});
