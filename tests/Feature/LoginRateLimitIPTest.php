<?php

use App\Models\InstanceSettings;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    config(['app.maintenance.store' => 'array']);
    Cache::forget('instance_settings_fqdn_host');
    $this->withoutMiddleware(PreventRequestsDuringMaintenance::class);
    InstanceSettings::unguarded(fn () => InstanceSettings::query()->create(['id' => 0]));
});

it('does not bypass login rate limiting when x-forwarded-for rotates', function () {
    $baseUrl = '/login';
    $email = 'rate-limit-bypass@example.com';
    $token = 'rate-limit-test-token';

    $results = [];
    for ($i = 1; $i <= 6; $i++) {
        $spoofedIp = "198.51.100.{$i}";

        $response = $this->withSession(['_token' => $token])
            ->withHeader('X-Forwarded-For', $spoofedIp)
            ->post($baseUrl, [
                '_token' => $token,
                'email' => $email,
                'password' => "WrongPass{$i}!",
            ]);

        $results[$i] = [
            'ip' => $spoofedIp,
            'status' => $response->getStatusCode(),
            'rate_limit' => $response->headers->get('X-RateLimit-Limit'),
            'rate_limit_remaining' => $response->headers->get('X-RateLimit-Remaining'),
        ];
    }

    expect($results)->toHaveCount(6);

    expect($results[1]['status'])->not->toBe(429)
        ->and($results[5]['status'])->not->toBe(429)
        ->and($results[6]['status'])->toBe(429);
});
