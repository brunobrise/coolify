<?php

use App\Enums\ApplicationDeploymentStatus;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->withoutMiddleware();

    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));
});

it('renders deployment logs for the requested application', function () {
    $context = createDeploymentLogContext($this);
    $deployment = ApplicationDeploymentQueue::create([
        'application_id' => $context['application']->id,
        'deployment_uuid' => 'own-application-deploy',
        'server_id' => $context['server']->id,
        'status' => ApplicationDeploymentStatus::FINISHED->value,
        'logs' => deploymentLogPayload('own deployment log'),
    ]);

    $response = $this->get(route('project.application.deployment.show', [
        'project_uuid' => $context['project']->uuid,
        'environment_uuid' => $context['environment']->uuid,
        'application_uuid' => $context['application']->uuid,
        'deployment_uuid' => $deployment->deployment_uuid,
    ]));

    $response->assertSuccessful();
    $response->assertSee('own deployment log');
});

it('does not render deployment logs from another application', function () {
    $context = createDeploymentLogContext($this);
    $otherContext = createDeploymentLogContext($this, authenticate: false);
    $deployment = ApplicationDeploymentQueue::create([
        'application_id' => $otherContext['application']->id,
        'deployment_uuid' => 'other-application-deploy',
        'server_id' => $otherContext['server']->id,
        'status' => ApplicationDeploymentStatus::FINISHED->value,
        'logs' => deploymentLogPayload('secret cross team log'),
    ]);

    $response = $this->get(route('project.application.deployment.show', [
        'project_uuid' => $context['project']->uuid,
        'environment_uuid' => $context['environment']->uuid,
        'application_uuid' => $context['application']->uuid,
        'deployment_uuid' => $deployment->deployment_uuid,
    ]));

    $response->assertRedirect(route('project.application.deployment.index', [
        'project_uuid' => $context['project']->uuid,
        'environment_uuid' => $context['environment']->uuid,
        'application_uuid' => $context['application']->uuid,
    ]));
    $response->assertDontSee('secret cross team log');
});

function createDeploymentLogContext($testCase, bool $authenticate = true): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);

    if ($authenticate) {
        $testCase->actingAs($user);
        session(['currentTeam' => $team]);
    }

    $server = Server::factory()->create(['team_id' => $team->id]);
    $destination = StandaloneDocker::query()->where('server_id', $server->id)->firstOrFail();
    $project = Project::factory()->create(['team_id' => $team->id]);
    $environment = Environment::factory()->create(['project_id' => $project->id]);
    $application = Application::factory()->create([
        'environment_id' => $environment->id,
        'destination_id' => $destination->id,
        'destination_type' => $destination->getMorphClass(),
        'status' => 'running',
    ]);

    return compact('application', 'environment', 'project', 'server', 'team', 'user');
}

function deploymentLogPayload(string $output): string
{
    return json_encode([
        [
            'command' => null,
            'output' => $output,
            'type' => 'stdout',
            'timestamp' => now()->toISOString(),
            'hidden' => false,
            'batch' => 1,
            'order' => 1,
        ],
    ], JSON_THROW_ON_ERROR);
}
