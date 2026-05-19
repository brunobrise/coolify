<?php

use App\Models\DiscordNotificationSettings;
use App\Models\EmailNotificationSettings;
use App\Models\PushoverNotificationSettings;
use App\Models\SlackNotificationSettings;
use App\Models\Team;
use App\Models\TelegramNotificationSettings;
use App\Models\User;
use App\Models\WebhookNotificationSettings;
use App\Policies\NotificationPolicy;
use Illuminate\Database\Eloquent\Model;

function notificationPolicyUser(array $rolesByTeam): User
{
    $teams = collect(array_map(
        fn (int $teamId, string $role) => (object) ['id' => $teamId, 'pivot' => (object) ['role' => $role]],
        array_keys($rolesByTeam),
        $rolesByTeam,
    ));

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);
    $user->shouldReceive('isAdminOfTeam')->andReturnUsing(function (int $teamId) use ($rolesByTeam) {
        return in_array($rolesByTeam[$teamId] ?? null, ['admin', 'owner'], true);
    });
    $user->shouldReceive('canAccessSystemResources')->andReturnUsing(function () use ($rolesByTeam) {
        return in_array($rolesByTeam[0] ?? null, ['admin', 'owner'], true);
    });

    return $user;
}

function notificationPolicySettings(string $settingsClass, int $teamId = 1): Model
{
    $team = new Team;
    $team->id = $teamId;

    $settings = new $settingsClass;
    $settings->team_id = $teamId;
    $settings->setRelation('team', $team);

    return $settings;
}

dataset('notification settings', [
    DiscordNotificationSettings::class,
    EmailNotificationSettings::class,
    PushoverNotificationSettings::class,
    SlackNotificationSettings::class,
    TelegramNotificationSettings::class,
    WebhookNotificationSettings::class,
]);

it('allows team members to view notification settings', function (string $settingsClass) {
    $settings = notificationPolicySettings($settingsClass);
    $user = notificationPolicyUser([1 => 'member']);
    $policy = new NotificationPolicy;

    expect($policy->view($user, $settings))->toBeTrue();
})->with('notification settings');

it('denies team members from mutating notification settings', function (string $settingsClass) {
    $settings = notificationPolicySettings($settingsClass);
    $user = notificationPolicyUser([1 => 'member']);
    $policy = new NotificationPolicy;

    expect($policy->update($user, $settings))->toBeFalse()
        ->and($policy->manage($user, $settings))->toBeFalse()
        ->and($policy->sendTest($user, $settings))->toBeFalse();
})->with('notification settings');

it('allows team admins to mutate notification settings', function (string $settingsClass) {
    $settings = notificationPolicySettings($settingsClass);
    $user = notificationPolicyUser([1 => 'admin']);
    $policy = new NotificationPolicy;

    expect($policy->update($user, $settings))->toBeTrue()
        ->and($policy->manage($user, $settings))->toBeTrue()
        ->and($policy->sendTest($user, $settings))->toBeTrue();
})->with('notification settings');

it('denies outsiders from viewing notification settings', function (string $settingsClass) {
    $settings = notificationPolicySettings($settingsClass);
    $user = notificationPolicyUser([2 => 'owner']);
    $policy = new NotificationPolicy;

    expect($policy->view($user, $settings))->toBeFalse()
        ->and($policy->update($user, $settings))->toBeFalse();
})->with('notification settings');

it('denies notification settings without a team', function (string $settingsClass) {
    $settings = new $settingsClass;
    $user = notificationPolicyUser([1 => 'owner']);
    $policy = new NotificationPolicy;

    expect($policy->view($user, $settings))->toBeFalse()
        ->and($policy->update($user, $settings))->toBeFalse();
})->with('notification settings');
