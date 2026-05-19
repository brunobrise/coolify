<?php

use App\Livewire\Project\Shared\ResourceOperations;
use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\SwarmDocker;
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

    // Team A (attacker's team)
    $this->userA = User::factory()->create();
    $this->teamA = Team::factory()->create();
    $this->userA->teams()->attach($this->teamA, ['role' => 'owner']);
    $this->memberA = User::factory()->create();
    $this->memberA->teams()->attach($this->teamA, ['role' => 'member']);

    $this->serverA = Server::factory()->create(['team_id' => $this->teamA->id]);
    $this->destinationA = StandaloneDocker::factory()->create([
        'server_id' => $this->serverA->id,
        'network' => 'team-a-'.fake()->unique()->word(),
    ]);
    $this->swarmDestinationA = SwarmDocker::create([
        'uuid' => fake()->uuid(),
        'name' => 'swarm-a-'.fake()->unique()->word(),
        'network' => 'swarm-a-'.fake()->unique()->word(),
        'server_id' => $this->serverA->id,
    ]);
    $this->projectA = Project::factory()->create(['team_id' => $this->teamA->id]);
    $this->environmentA = Environment::factory()->create(['project_id' => $this->projectA->id]);

    $this->applicationA = Application::factory()->create([
        'environment_id' => $this->environmentA->id,
        'destination_id' => $this->destinationA->id,
        'destination_type' => $this->destinationA->getMorphClass(),
    ]);

    // Team B (victim's team)
    $this->teamB = Team::factory()->create();
    $this->serverB = Server::factory()->create(['team_id' => $this->teamB->id]);
    $this->destinationB = StandaloneDocker::factory()->create([
        'server_id' => $this->serverB->id,
        'network' => 'team-b-'.fake()->unique()->word(),
    ]);
    $this->projectB = Project::factory()->create(['team_id' => $this->teamB->id]);
    $this->environmentB = Environment::factory()->create(['project_id' => $this->projectB->id]);

    $this->actingAs($this->userA);
    session(['currentTeam' => $this->teamA]);

    $route = Route::get('/test/{project_uuid}/{environment_uuid}', fn () => '');
    $request = Request::create("/test/{$this->projectA->uuid}/{$this->environmentA->uuid}");
    $route->bind($request);
    Route::dispatch($request);
});

test('cloneTo rejects destination belonging to another team', function () {
    Livewire::test(ResourceOperations::class, ['resource' => $this->applicationA])
        ->call('cloneTo', $this->destinationB->id)
        ->assertHasErrors('destination_id');

    // Ensure no cross-tenant application was created
    expect(Application::where('destination_id', $this->destinationB->id)->exists())->toBeFalse();
});

test('cloneTo allows destination belonging to own team', function () {
    $secondDestination = StandaloneDocker::factory()->create([
        'server_id' => $this->serverA->id,
        'network' => 'team-a-clone-'.fake()->unique()->word(),
    ]);

    Livewire::test(ResourceOperations::class, ['resource' => $this->applicationA])
        ->set('projectUuid', $this->projectA->uuid)
        ->set('environmentUuid', $this->environmentA->uuid)
        ->call('cloneTo', $secondDestination->id)
        ->assertHasNoErrors('destination_id')
        ->assertRedirect();
});

test('moveTo rejects environment belonging to another team', function () {
    Livewire::test(ResourceOperations::class, ['resource' => $this->applicationA])
        ->call('moveTo', $this->environmentB->id);

    // Resource should still be in original environment
    $this->applicationA->refresh();
    expect($this->applicationA->environment_id)->toBe($this->environmentA->id);
});

test('moveTo allows environment belonging to own team', function () {
    $secondEnvironment = Environment::factory()->create(['project_id' => $this->projectA->id]);

    Livewire::test(ResourceOperations::class, ['resource' => $this->applicationA])
        ->call('moveTo', $secondEnvironment->id)
        ->assertRedirect();

    $this->applicationA->refresh();
    expect($this->applicationA->environment_id)->toBe($secondEnvironment->id);
});

test('StandaloneDockerPolicy denies update for cross-team user', function () {
    expect($this->userA->can('update', $this->destinationB))->toBeFalse();
});

test('StandaloneDockerPolicy allows update for same-team user', function () {
    expect($this->userA->can('update', $this->destinationA))->toBeTrue();
});

test('Docker destination policies deny same-team member writes', function () {
    expect($this->memberA->can('view', $this->destinationA))->toBeTrue()
        ->and($this->memberA->can('create', StandaloneDocker::class))->toBeFalse()
        ->and($this->memberA->can('update', $this->destinationA))->toBeFalse()
        ->and($this->memberA->can('delete', $this->destinationA))->toBeFalse()
        ->and($this->memberA->can('view', $this->swarmDestinationA))->toBeTrue()
        ->and($this->memberA->can('create', SwarmDocker::class))->toBeFalse()
        ->and($this->memberA->can('update', $this->swarmDestinationA))->toBeFalse()
        ->and($this->memberA->can('delete', $this->swarmDestinationA))->toBeFalse();
});

test('Docker destination policies allow same-team owner writes', function () {
    expect($this->userA->can('create', StandaloneDocker::class))->toBeTrue()
        ->and($this->userA->can('update', $this->destinationA))->toBeTrue()
        ->and($this->userA->can('delete', $this->destinationA))->toBeTrue()
        ->and($this->userA->can('create', SwarmDocker::class))->toBeTrue()
        ->and($this->userA->can('update', $this->swarmDestinationA))->toBeTrue()
        ->and($this->userA->can('delete', $this->swarmDestinationA))->toBeTrue();
});
