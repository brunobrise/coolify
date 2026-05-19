<?php

use App\Livewire\Project\DeleteEnvironment;
use App\Livewire\Project\DeleteProject;
use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('escapes project names in deletion error toasts', function () {
    $project = Project::factory()->create([
        'team_id' => $this->team->id,
    ]);
    DB::table('projects')
        ->where('id', $project->id)
        ->update(['name' => 'Project <img src=x onerror=alert(1)>']);
    $environment = Environment::factory()->create(['project_id' => $project->id]);
    Application::factory()->create(['environment_id' => $environment->id]);

    Livewire::test(DeleteProject::class, ['project_id' => $project->id])
        ->call('delete')
        ->assertDispatched('error', function ($event, array $params) {
            $message = (string) collect($params)->flatten()->first();

            return str_contains($message, '&lt;img src=x onerror=alert(1)&gt;')
                && ! str_contains($message, '<img src=x onerror=alert(1)>');
        });
});

it('escapes environment names in deletion error toasts', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $environment = Environment::factory()->create([
        'project_id' => $project->id,
    ]);
    DB::table('environments')
        ->where('id', $environment->id)
        ->update(['name' => 'Production <script>alert(1)</script>']);
    Application::factory()->create(['environment_id' => $environment->id]);

    Livewire::test(DeleteEnvironment::class, ['environment_id' => $environment->id])
        ->call('delete')
        ->assertDispatched('error', function ($event, array $params) {
            $message = (string) collect($params)->flatten()->first();

            return str_contains($message, '&lt;script&gt;alert(1)&lt;/script&gt;')
                && ! str_contains($message, '<script>alert(1)</script>');
        });
});

it('does not mount delete environment for another team environment', function () {
    $otherTeam = Team::factory()->create();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);

    Livewire::test(DeleteEnvironment::class, ['environment_id' => $otherEnvironment->id])
        ->assertStatus(404);
});
