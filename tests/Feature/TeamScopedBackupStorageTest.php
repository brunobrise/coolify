<?php

use App\Livewire\Storage\Form as StorageForm;
use App\Livewire\Storage\Resources as StorageResources;
use App\Models\InstanceSettings;
use App\Models\S3Storage;
use App\Models\ScheduledDatabaseBackup;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));

    $this->userA = User::factory()->create();
    $this->teamA = Team::factory()->create();
    $this->userA->teams()->attach($this->teamA, ['role' => 'owner']);

    $this->userB = User::factory()->create();
    $this->teamB = Team::factory()->create();
    $this->userB->teams()->attach($this->teamB, ['role' => 'owner']);

    $this->storageA = S3Storage::unguarded(fn () => S3Storage::create([
        'uuid' => fake()->uuid(),
        'name' => 'storage-a-'.fake()->unique()->word(),
        'region' => 'us-east-1',
        'key' => 'key-a',
        'secret' => 'secret-a',
        'bucket' => 'bucket-a',
        'endpoint' => 'https://s3.example.com',
        'team_id' => $this->teamA->id,
    ]));

    $this->storageB = S3Storage::unguarded(fn () => S3Storage::create([
        'uuid' => fake()->uuid(),
        'name' => 'storage-b-'.fake()->unique()->word(),
        'region' => 'us-east-1',
        'key' => 'key-b',
        'secret' => 'secret-b',
        'bucket' => 'bucket-b',
        'endpoint' => 'https://s3.example.com',
        'team_id' => $this->teamB->id,
    ]));

    $this->backupA = ScheduledDatabaseBackup::create([
        'uuid' => fake()->uuid(),
        'team_id' => $this->teamA->id,
        'enabled' => true,
        'save_s3' => true,
        'frequency' => '0 0 * * *',
        'database_type' => 'App\\Models\\StandalonePostgresql',
        'database_id' => 1,
        's3_storage_id' => $this->storageA->id,
    ]);

    $this->backupB = ScheduledDatabaseBackup::create([
        'uuid' => fake()->uuid(),
        'team_id' => $this->teamB->id,
        'enabled' => true,
        'save_s3' => true,
        'frequency' => '0 0 * * *',
        'database_type' => 'App\\Models\\StandalonePostgresql',
        'database_id' => 2,
        's3_storage_id' => $this->storageB->id,
    ]);

    $this->actingAs($this->userA);
    session(['currentTeam' => $this->teamA]);
});

describe('Storage/Resources team-scoped backup access', function () {
    test('disableS3 on other team backup throws and leaves row unchanged', function () {
        expect(fn () => Livewire::test(StorageResources::class, ['storage' => $this->storageA])
            ->call('disableS3', $this->backupB->id))
            ->toThrow(ModelNotFoundException::class);

        $this->backupB->refresh();
        expect((bool) $this->backupB->save_s3)->toBeTrue();
        expect($this->backupB->s3_storage_id)->toBe($this->storageB->id);
    });

    test('moveBackup on other team backup throws and leaves row unchanged', function () {
        expect(fn () => Livewire::test(StorageResources::class, ['storage' => $this->storageA])
            ->set('selectedStorages', [$this->backupB->id => $this->storageA->id])
            ->call('moveBackup', $this->backupB->id))
            ->toThrow(ModelNotFoundException::class);

        $this->backupB->refresh();
        expect($this->backupB->s3_storage_id)->toBe($this->storageB->id);
    });

    test('disableS3 on own backup succeeds', function () {
        Livewire::test(StorageResources::class, ['storage' => $this->storageA])
            ->call('disableS3', $this->backupA->id);

        $this->backupA->refresh();
        expect((bool) $this->backupA->save_s3)->toBeFalse();
        expect($this->backupA->s3_storage_id)->toBeNull();
    });
});

describe('Storage/Form credential visibility', function () {
    test('team owner can hydrate S3 credentials', function () {
        Livewire::test(StorageForm::class, ['storage' => $this->storageA])
            ->assertSet('key', 'key-a')
            ->assertSet('secret', 'secret-a');
    });

    test('team member can view storage metadata without S3 credentials', function () {
        $member = User::factory()->create();
        $member->teams()->attach($this->teamA, ['role' => 'member']);

        $this->actingAs($member);
        session(['currentTeam' => $this->teamA]);

        Livewire::test(StorageForm::class, ['storage' => $this->storageA])
            ->assertSet('name', $this->storageA->name)
            ->assertSet('endpoint', 'https://s3.example.com')
            ->assertSet('key', '')
            ->assertSet('secret', '');
    });

    test('user from another team cannot mount S3 storage form', function () {
        $this->actingAs($this->userB);
        session(['currentTeam' => $this->teamB]);

        Livewire::test(StorageForm::class, ['storage' => $this->storageA])
            ->assertForbidden();
    });
});
