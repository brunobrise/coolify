<?php

it('uses bounded non-redirecting http clients for fixed notification endpoints', function () {
    foreach ([
        'app/Jobs/SendMessageToTelegramJob.php',
        'app/Jobs/SendMessageToPushoverJob.php',
        'app/Livewire/Help.php',
    ] as $path) {
        $source = file_get_contents(__DIR__.'/../../'.$path);

        expect($source)
            ->toContain('Http::timeout(10)->withoutRedirecting()->post')
            ->not->toContain('Http::post(');
    }
});
