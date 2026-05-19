<?php

use App\Livewire\Notifications\Webhook;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));
    Once::flush();

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();

    $this->owner->teams()->attach($this->team, ['role' => 'owner']);
    $this->member->teams()->attach($this->team, ['role' => 'member']);
});

it('prevents team members from updating webhook notification settings', function () {
    $this->actingAs($this->member);
    session(['currentTeam' => $this->team]);

    Livewire::test(Webhook::class)
        ->set('webhookEnabled', true)
        ->set('webhookUrl', 'https://example.com/member-webhook')
        ->call('instantSave');

    $settings = $this->team->webhookNotificationSettings->fresh();

    expect($settings->webhook_enabled)->toBeFalse()
        ->and($settings->webhook_url)->toBeNull();
});

it('allows team owners to update webhook notification settings', function () {
    $this->actingAs($this->owner);
    session(['currentTeam' => $this->team]);

    Livewire::test(Webhook::class)
        ->set('webhookEnabled', true)
        ->set('webhookUrl', 'https://example.com/owner-webhook')
        ->call('instantSave');

    $settings = $this->team->webhookNotificationSettings->fresh();

    expect($settings->webhook_enabled)->toBeTrue()
        ->and($settings->webhook_url)->toBe('https://example.com/owner-webhook');
});
