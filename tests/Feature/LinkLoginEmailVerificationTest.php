<?php

use App\Http\Middleware\CheckForcePasswordReset;
use App\Http\Middleware\DecideWhatToDoWithUser;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([DecideWhatToDoWithUser::class, CheckForcePasswordReset::class, PreventRequestsDuringMaintenance::class]);
    config(['cache.default' => 'array']);
    Once::flush();
    if (! InstanceSettings::find(0)) {
        $settings = new InstanceSettings;
        $settings->id = 0;
        $settings->saveQuietly();
    }
});

describe('invitation link login', function () {
    test('does not auto-verify the email address', function () {
        $team = Team::factory()->create();
        $password = 'test-password-123';
        $user = User::factory()->create([
            'email' => 'invitee@example.com',
            'password' => Hash::make($password),
            'email_verified_at' => null,
        ]);
        TeamInvitation::create([
            'team_id' => $team->id,
            'uuid' => 'invite-email-verification',
            'email' => $user->email,
            'role' => 'member',
            'link' => 'https://example.com/invite',
            'via' => 'link',
        ]);

        $token = inviteLoginToken($user->email, $password);

        $this->get(route('auth.link', ['token' => $token]));

        $user->refresh();
        expect($user->email_verified_at)->toBeNull();
    });

    test('still logs the user in', function () {
        $team = Team::factory()->create();
        $password = 'test-password-123';
        $user = User::factory()->create([
            'email' => 'invitee2@example.com',
            'password' => Hash::make($password),
            'email_verified_at' => null,
        ]);
        TeamInvitation::create([
            'team_id' => $team->id,
            'uuid' => 'invite-login',
            'email' => $user->email,
            'role' => 'member',
            'link' => 'https://example.com/invite',
            'via' => 'link',
        ]);

        $token = inviteLoginToken($user->email, $password);

        $this->get(route('auth.link', ['token' => $token]));

        expect(auth()->id())->toBe($user->id);
    });

    test('rejects legacy password tokens', function () {
        $password = 'test-password-123';
        $user = User::factory()->create([
            'email' => 'legacy-invitee@example.com',
            'password' => Hash::make($password),
        ]);

        $token = Crypt::encryptString("{$user->email}@@@{$password}");

        $this->get(route('auth.link', ['token' => $token]));

        expect(auth()->check())->toBeFalse();
    });

    test('rejects expired invite login tokens', function () {
        $team = Team::factory()->create();
        $password = 'test-password-123';
        $user = User::factory()->create([
            'email' => 'expired-invitee@example.com',
            'password' => Hash::make($password),
        ]);
        TeamInvitation::create([
            'team_id' => $team->id,
            'uuid' => 'invite-expired',
            'email' => $user->email,
            'role' => 'member',
            'link' => 'https://example.com/invite',
            'via' => 'link',
        ]);

        $token = inviteLoginToken($user->email, $password, now()->subMinute()->timestamp);

        $this->get(route('auth.link', ['token' => $token]));

        expect(auth()->check())->toBeFalse();
    });

    test('rejects invite login token replay after invitation is consumed', function () {
        $team = Team::factory()->create();
        $password = 'test-password-123';
        $user = User::factory()->create([
            'email' => 'replay-invitee@example.com',
            'password' => Hash::make($password),
        ]);
        TeamInvitation::create([
            'team_id' => $team->id,
            'uuid' => 'invite-replay',
            'email' => $user->email,
            'role' => 'member',
            'link' => 'https://example.com/invite',
            'via' => 'link',
        ]);

        $token = inviteLoginToken($user->email, $password);

        $this->get(route('auth.link', ['token' => $token]));
        auth()->logout();

        $this->get(route('auth.link', ['token' => $token]));

        expect(auth()->check())->toBeFalse();
    });

    test('uses invitation uuid from invite login tokens when email has multiple invitations', function () {
        $firstTeam = Team::factory()->create();
        $secondTeam = Team::factory()->create();
        $password = 'test-password-123';
        $user = User::factory()->create([
            'email' => 'multi-invitee@example.com',
            'password' => Hash::make($password),
        ]);
        $firstInvitation = TeamInvitation::create([
            'team_id' => $firstTeam->id,
            'uuid' => 'first-invite',
            'email' => $user->email,
            'role' => 'admin',
            'link' => 'https://example.com/invite/first-invite',
            'via' => 'link',
        ]);
        $secondInvitation = TeamInvitation::create([
            'team_id' => $secondTeam->id,
            'uuid' => 'second-invite',
            'email' => $user->email,
            'role' => 'member',
            'link' => 'https://example.com/invite/second-invite',
            'via' => 'link',
        ]);

        $token = inviteLoginToken($user->email, $password, invitationUuid: $secondInvitation->uuid);

        $this->get(route('auth.link', ['token' => $token]));

        expect(auth()->id())->toBe($user->id)
            ->and($user->fresh()->teams()->where('team_id', $secondTeam->id)->exists())->toBeTrue()
            ->and($user->fresh()->teams()->where('team_id', $firstTeam->id)->exists())->toBeFalse()
            ->and(TeamInvitation::whereKey($secondInvitation->id)->exists())->toBeFalse()
            ->and(TeamInvitation::whereKey($firstInvitation->id)->exists())->toBeTrue();
    });
});

function inviteLoginToken(string $email, string $password, ?int $expiresAt = null, ?string $invitationUuid = null): string
{
    return Crypt::encryptString(json_encode([
        'email' => $email,
        'password' => $password,
        'invitation_uuid' => $invitationUuid,
        'expires_at' => $expiresAt ?? now()->addDay()->timestamp,
    ], JSON_THROW_ON_ERROR));
}
