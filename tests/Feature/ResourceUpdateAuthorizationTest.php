<?php

use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestsDuringMaintenance::class);
    config(['cache.default' => 'array']);

    if (! InstanceSettings::find(0)) {
        $settings = new InstanceSettings;
        $settings->id = 0;
        $settings->saveQuietly();
    }

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();

    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->member->id, ['role' => 'member']);

    $this->server = Server::factory()->create(['team_id' => $this->team->id]);
    $this->destination = StandaloneDocker::factory()->create([
        'server_id' => $this->server->id,
        'network' => 'resource-auth-test',
    ]);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
    $this->database = StandalonePostgresql::create([
        'name' => 'postgres-auth-test',
        'postgres_password' => 'secret',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);
});

test('team member cannot open database import route guarded by update middleware', function () {
    $this->actingAs($this->member);
    session(['currentTeam' => $this->team]);

    $this->get(route('project.database.import-backup', [
        'project_uuid' => $this->project->uuid,
        'environment_uuid' => $this->environment->uuid,
        'database_uuid' => $this->database->uuid,
    ]))->assertForbidden();
});

test('team member cannot upload database restore file', function () {
    $this->actingAs($this->member);
    session(['currentTeam' => $this->team]);

    $this->post(route('upload.backup', ['databaseUuid' => $this->database->uuid]), [
        'file' => UploadedFile::fake()->create('backup.sql', 1, 'application/sql'),
        'dzTotalFilesize' => 1024,
    ])->assertForbidden();
});

test('owner cannot update another teams resource through guarded route', function () {
    $otherTeam = Team::factory()->create();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherEnvironment = Environment::factory()->create(['project_id' => $otherProject->id]);
    $otherServer = Server::factory()->create(['team_id' => $otherTeam->id]);
    $otherDestination = StandaloneDocker::factory()->create([
        'server_id' => $otherServer->id,
        'network' => 'resource-auth-other-test',
    ]);
    $otherDatabase = StandalonePostgresql::create([
        'name' => 'postgres-other-test',
        'postgres_password' => 'secret',
        'environment_id' => $otherEnvironment->id,
        'destination_id' => $otherDestination->id,
        'destination_type' => StandaloneDocker::class,
    ]);

    $this->actingAs($this->owner);
    session(['currentTeam' => $this->team]);

    $this->get(route('project.database.import-backup', [
        'project_uuid' => $otherProject->uuid,
        'environment_uuid' => $otherEnvironment->uuid,
        'database_uuid' => $otherDatabase->uuid,
    ]))->assertNotFound();
});
