<?php

it('uses quoted helpers for server-generated file writes', function () {
    foreach ([
        'app/Models/Server.php',
        'app/Livewire/Server/CaCertificate/Show.php',
        'app/Actions/Server/ConfigureCloudflared.php',
    ] as $path) {
        $source = file_get_contents(__DIR__.'/../../'.$path);

        expect($source)
            ->not->toContain('base64 -d | tee')
            ->not->toContain('rm -rf $caCertPath')
            ->toContain('writeBase64FileCommand');
    }
});
