<?php

use App\Livewire\Team\InviteLink;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not block current team invites because another team invited the same email', function () {
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));

    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $owner->teams()->attach($team, ['role' => 'owner']);
    $otherTeam = Team::factory()->create();

    TeamInvitation::create([
        'team_id' => $otherTeam->id,
        'uuid' => 'other-team-invite',
        'email' => 'shared@example.com',
        'role' => 'member',
        'link' => 'https://example.com/invite/other-team-invite',
        'via' => 'link',
    ]);

    $this->actingAs($owner);
    session(['currentTeam' => $team]);

    Livewire::test(InviteLink::class)
        ->set('email', 'shared@example.com')
        ->set('role', 'member')
        ->call('viaLink')
        ->assertDispatched('success');

    $this->assertDatabaseHas('team_invitations', [
        'team_id' => $team->id,
        'email' => 'shared@example.com',
    ]);
});
