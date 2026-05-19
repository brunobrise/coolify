<?php

use App\Jobs\ScheduledTaskJob;

it('quotes scheduled task container names and strips newlines', function () {
    $job = (new ReflectionClass(ScheduledTaskJob::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ScheduledTaskJob::class, 'scheduledTaskExecCommand');
    $method->setAccessible(true);

    $command = $method->invoke($job, "app'; id; #", "php artisan migrate\ncurl http://evil.test");

    expect($command)
        ->toStartWith("docker exec 'app'\\''; id; #' sh -c ")
        ->toContain('php artisan migrate curl http://evil.test')
        ->not->toContain("\n")
        ->not->toContain("docker exec app'; id;");
});
