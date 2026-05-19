<?php

use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function privateKeyDisclosureValue(): string
{
    return '-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevAAAAJi/QySHv0Mk
hwAAAAtzc2gtZWQyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevA
AAAECBQw4jg1WRT2IGHMncCiZhURCts2s24HoDS0thHnnRKVuGmoeGq/pojrsyP1pszcNV
uZx9iFkCELtxrh31QJ68AAAAEXNhaWxANzZmZjY2ZDJlMmRkAQIDBA==
-----END OPENSSH PRIVATE KEY-----';
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    config(['app.maintenance.store' => 'array']);
    Cache::flush();
    $this->withoutMiddleware(PreventRequestsDuringMaintenance::class);
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();

    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->admin->id, ['role' => 'admin']);
    $this->team->members()->attach($this->member->id, ['role' => 'member']);

    $this->privateKey = PrivateKey::create([
        'team_id' => $this->team->id,
        'name' => 'Deploy key',
        'private_key' => privateKeyDisclosureValue(),
    ]);
});

it('blocks team members from opening private key detail page', function () {
    $this->actingAs($this->member);

    $response = $this->withSession(['currentTeam' => $this->team])
        ->get(route('security.private-key.show', ['private_key_uuid' => $this->privateKey->uuid]));

    $response->assertForbidden();
});

it('allows team admins to open private key detail page', function () {
    $this->actingAs($this->admin);

    $response = $this->withSession(['currentTeam' => $this->team])
        ->get(route('security.private-key.show', ['private_key_uuid' => $this->privateKey->uuid]));

    $response->assertOk();
});

it('allows team owners to open private key detail page', function () {
    $this->actingAs($this->owner);

    $response = $this->withSession(['currentTeam' => $this->team])
        ->get(route('security.private-key.show', ['private_key_uuid' => $this->privateKey->uuid]));

    $response->assertOk();
});
