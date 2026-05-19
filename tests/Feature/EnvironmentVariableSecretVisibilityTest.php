<?php

use App\Livewire\Project\Shared\EnvironmentVariable\All as EnvironmentVariableAll;
use App\Livewire\Project\Shared\EnvironmentVariable\Show as EnvironmentVariableShow;
use App\Livewire\SharedVariables\Team\Index as TeamSharedVariables;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\SharedEnvironmentVariable;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Once;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));
    Once::flush();

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->member->id, ['role' => 'member']);

    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
    $this->application = Application::factory()->create(['environment_id' => $this->environment->id]);
    $this->environmentVariable = EnvironmentVariable::create([
        'key' => 'APP_SECRET',
        'value' => 'super-secret',
        'is_multiline' => false,
        'is_literal' => false,
        'is_shown_once' => false,
        'is_runtime' => true,
        'is_buildtime' => true,
        'resourceable_type' => $this->application->getMorphClass(),
        'resourceable_id' => $this->application->id,
    ]);
    $this->sharedVariable = SharedEnvironmentVariable::create([
        'key' => 'TEAM_SECRET',
        'value' => 'team-secret',
        'is_multiline' => false,
        'is_literal' => false,
        'is_shown_once' => false,
        'type' => 'team',
        'team_id' => $this->team->id,
    ]);

    $route = Route::get('/test/{project_uuid}/{environment_uuid}/{application_uuid}', fn () => '');
    $request = Request::create("/test/{$this->project->uuid}/{$this->environment->uuid}/{$this->application->uuid}");
    $route->bind($request);
    Route::dispatch($request);
});

it('masks resource environment variable values for team members', function () {
    $this->actingAs($this->member);
    session(['currentTeam' => $this->team]);

    Livewire::test(EnvironmentVariableAll::class, ['resource' => $this->application])
        ->assertSet('variables', fn (?string $variables) => str_contains($variables, 'APP_SECRET=(Hidden Secret)')
            && ! str_contains($variables, 'super-secret'));

    Livewire::test(EnvironmentVariableShow::class, [
        'env' => $this->environmentVariable,
        'type' => $this->application->type(),
    ])
        ->assertSet('key', 'APP_SECRET')
        ->assertSet('value', null)
        ->assertSet('real_value', null);
});

it('shows resource environment variable values to team owners', function () {
    $this->actingAs($this->owner);
    session(['currentTeam' => $this->team]);

    Livewire::test(EnvironmentVariableAll::class, ['resource' => $this->application])
        ->assertSet('variables', fn (?string $variables) => str_contains($variables, 'APP_SECRET=super-secret'));

    Livewire::test(EnvironmentVariableShow::class, [
        'env' => $this->environmentVariable,
        'type' => $this->application->type(),
    ])
        ->assertSet('value', 'super-secret');
});

it('masks shared environment variable values for team members', function () {
    $this->actingAs($this->member);
    session(['currentTeam' => $this->team]);

    Livewire::test(TeamSharedVariables::class)
        ->assertSet('variables', 'TEAM_SECRET=(Hidden Secret)');
});

it('shows shared environment variable values to team owners', function () {
    $this->actingAs($this->owner);
    session(['currentTeam' => $this->team]);

    Livewire::test(TeamSharedVariables::class)
        ->assertSet('variables', 'TEAM_SECRET=team-secret');
});
