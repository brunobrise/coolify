<?php

it('uses bounded non-redirecting clients for github api urls', function () {
    foreach ([
        'app/Providers/AppServiceProvider.php',
        'bootstrap/helpers/github.php',
        'app/Http/Controllers/Webhook/Github.php',
    ] as $path) {
        $source = file_get_contents(__DIR__.'/../../'.$path);

        expect($source)
            ->toContain('timeout(10)->withoutRedirecting()')
            ->not->toContain('Http::get("{$source->api_url}/zen")')
            ->not->toContain('Http::withHeaders([')
            ->not->toContain('Http::withBody(null)->accept');
    }
});
