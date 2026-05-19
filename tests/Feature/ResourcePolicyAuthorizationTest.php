<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneDocker;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);

    if (! InstanceSettings::find(0)) {
        $settings = new InstanceSettings;
        $settings->id = 0;
        $settings->saveQuietly();
    }

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->member->id, ['role' => 'member']);

    $this->server = Server::factory()->create(['team_id' => $this->team->id]);
    $this->destination = StandaloneDocker::factory()->create([
        'server_id' => $this->server->id,
        'network' => 'resource-policy-test',
    ]);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);
    $this->service = Service::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);
    $this->database = StandalonePostgresql::create([
        'name' => 'postgres-policy-test',
        'postgres_password' => 'secret',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);
});

test('members can view team resources but cannot mutate or deploy them', function () {
    expect($this->member->can('view', $this->application))->toBeTrue()
        ->and($this->member->can('update', $this->application))->toBeFalse()
        ->and($this->member->can('deploy', $this->application))->toBeFalse()
        ->and($this->member->can('manageEnvironment', $this->application))->toBeFalse()
        ->and($this->member->can('update', $this->service))->toBeFalse()
        ->and($this->member->can('deploy', $this->service))->toBeFalse()
        ->and($this->member->can('update', $this->database))->toBeFalse()
        ->and($this->member->can('manageBackups', $this->database))->toBeFalse()
        ->and($this->member->can('update', $this->server))->toBeFalse()
        ->and($this->member->can('manageProxy', $this->server))->toBeFalse()
        ->and($this->member->can('update', $this->project))->toBeFalse()
        ->and($this->member->can('update', $this->environment))->toBeFalse();
});

test('owners can mutate and deploy their team resources', function () {
    expect($this->owner->can('update', $this->application))->toBeTrue()
        ->and($this->owner->can('deploy', $this->application))->toBeTrue()
        ->and($this->owner->can('manageEnvironment', $this->application))->toBeTrue()
        ->and($this->owner->can('update', $this->service))->toBeTrue()
        ->and($this->owner->can('deploy', $this->service))->toBeTrue()
        ->and($this->owner->can('update', $this->database))->toBeTrue()
        ->and($this->owner->can('manageBackups', $this->database))->toBeTrue()
        ->and($this->owner->can('update', $this->server))->toBeTrue()
        ->and($this->owner->can('manageProxy', $this->server))->toBeTrue()
        ->and($this->owner->can('update', $this->project))->toBeTrue()
        ->and($this->owner->can('update', $this->environment))->toBeTrue();
});

test('outsiders cannot view or mutate another teams resources', function () {
    expect($this->outsider->can('view', $this->application))->toBeFalse()
        ->and($this->outsider->can('update', $this->application))->toBeFalse()
        ->and($this->outsider->can('view', $this->database))->toBeFalse()
        ->and($this->outsider->can('manageBackups', $this->database))->toBeFalse()
        ->and($this->outsider->can('view', $this->server))->toBeFalse()
        ->and($this->outsider->can('update', $this->project))->toBeFalse();
});
