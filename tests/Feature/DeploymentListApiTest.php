<?php

use App\Enums\ApplicationDeploymentStatus;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->withoutMiddleware(PreventRequestsDuringMaintenance::class);
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    $plainTextToken = Str::random(40);
    $token = $this->user->tokens()->create([
        'name' => 'test-token',
        'token' => hash('sha256', $plainTextToken),
        'abilities' => ['*'],
        'team_id' => $this->team->id,
    ]);
    $this->bearerToken = $token->getKey().'|'.$plainTextToken;

    $this->server = Server::factory()->create(['team_id' => $this->team->id]);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
    $this->application = Application::factory()->create(['environment_id' => $this->environment->id]);
});

it('lists only deployments whose application belongs to the token team', function () {
    $otherTeam = Team::factory()->create();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
    $otherApplication = Application::factory()->create(['environment_id' => $otherEnvironment->id]);

    ApplicationDeploymentQueue::create([
        'deployment_uuid' => 'own-list-deployment',
        'application_id' => $this->application->id,
        'server_id' => $this->server->id,
        'status' => ApplicationDeploymentStatus::QUEUED->value,
    ]);
    ApplicationDeploymentQueue::create([
        'deployment_uuid' => 'cross-list-deployment',
        'application_id' => $otherApplication->id,
        'server_id' => $this->server->id,
        'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$this->bearerToken,
    ])->getJson('/api/v1/deployments');

    $response->assertSuccessful();
    $response->assertJsonFragment(['deployment_uuid' => 'own-list-deployment']);
    $response->assertJsonMissing(['deployment_uuid' => 'cross-list-deployment']);
});
