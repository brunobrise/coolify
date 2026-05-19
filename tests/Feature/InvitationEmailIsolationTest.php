<?php

use App\Http\Middleware\CheckForcePasswordReset;
use App\Http\Middleware\DecideWhatToDoWithUser;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([DecideWhatToDoWithUser::class, CheckForcePasswordReset::class, PreventRequestsDuringMaintenance::class]);
    config(['cache.default' => 'array']);
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));
});

it('does not auto-accept an arbitrary invitation when login email has multiple pending invitations', function () {
    $password = 'password';
    $user = User::factory()->create(['email' => 'ambiguous@example.com']);
    $firstTeam = Team::factory()->create();
    $secondTeam = Team::factory()->create();

    TeamInvitation::create([
        'team_id' => $firstTeam->id,
        'uuid' => 'first-login-invite',
        'email' => $user->email,
        'role' => 'admin',
        'link' => 'https://example.com/invite/first-login-invite',
        'via' => 'link',
    ]);
    TeamInvitation::create([
        'team_id' => $secondTeam->id,
        'uuid' => 'second-login-invite',
        'email' => $user->email,
        'role' => 'member',
        'link' => 'https://example.com/invite/second-login-invite',
        'via' => 'link',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    expect(auth()->id())->toBe($user->id)
        ->and($user->fresh()->teams()->wherePivotIn('team_id', [$firstTeam->id, $secondTeam->id])->exists())->toBeFalse()
        ->and(TeamInvitation::whereEmail($user->email)->count())->toBe(2);
});

it('deleting a user only removes invitations for teams deleted with that user', function () {
    $user = User::factory()->create(['email' => 'cleanup@example.com']);
    $personalTeam = $user->teams()->wherePivot('role', 'owner')->firstOrFail();
    $otherTeam = Team::factory()->create();

    TeamInvitation::create([
        'team_id' => $personalTeam->id,
        'uuid' => 'personal-team-invite',
        'email' => $user->email,
        'role' => 'member',
        'link' => 'https://example.com/invite/personal-team-invite',
        'via' => 'link',
    ]);
    TeamInvitation::create([
        'team_id' => $otherTeam->id,
        'uuid' => 'other-team-invite',
        'email' => $user->email,
        'role' => 'member',
        'link' => 'https://example.com/invite/other-team-invite',
        'via' => 'link',
    ]);

    $user->delete();

    expect(TeamInvitation::whereUuid('personal-team-invite')->exists())->toBeFalse()
        ->and(TeamInvitation::whereUuid('other-team-invite')->exists())->toBeTrue();
});
