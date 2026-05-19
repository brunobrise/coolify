<?php

use App\Livewire\Server\DockerCleanupExecutions;
use App\Models\DockerCleanupExecution;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
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

it('selects cleanup execution logs for the mounted server', function () {
    $server = Server::factory()->create(['team_id' => $this->team->id]);
    $execution = DockerCleanupExecution::create([
        'server_id' => $server->id,
        'status' => 'success',
        'message' => "line 1\nline 2",
    ]);

    $component = Livewire::test(DockerCleanupExecutions::class, ['server' => $server])
        ->call('selectExecution', $execution->id);

    expect($component->get('selectedExecution')->id)->toBe($execution->id)
        ->and($component->get('selectedKey'))->toBe($execution->id);
});

it('cannot select cleanup execution logs from another server', function () {
    $server = Server::factory()->create(['team_id' => $this->team->id]);
    $otherTeam = Team::factory()->create();
    $otherServer = Server::factory()->create(['team_id' => $otherTeam->id]);
    $otherExecution = DockerCleanupExecution::create([
        'server_id' => $otherServer->id,
        'status' => 'success',
        'message' => 'secret cleanup output',
    ]);

    $component = Livewire::test(DockerCleanupExecutions::class, ['server' => $server])
        ->call('selectExecution', $otherExecution->id);

    expect($component->get('selectedExecution'))->toBeNull()
        ->and($component->get('selectedKey'))->toBeNull()
        ->and($component->instance()->logLines)->toBeEmpty();
});
