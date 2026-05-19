<?php

use App\Traits\HasMetrics;

it('quotes sentinel metrics curl arguments', function () {
    $metrics = new class
    {
        use HasMetrics;
    };

    $method = new ReflectionMethod($metrics, 'sentinelMetricsCurlCommand');
    $method->setAccessible(true);

    $command = $method->invoke(
        $metrics,
        "token'; id; #",
        "http://localhost:8888/api/cpu/history?from=2026-05-19T00:00:00Z&x='; whoami #"
    );

    expect($command)
        ->toStartWith("docker exec 'coolify-sentinel' bash -c 'curl -H ")
        ->not->toContain("Bearer token'; id;")
        ->not->toContain("&x='; whoami");
});
