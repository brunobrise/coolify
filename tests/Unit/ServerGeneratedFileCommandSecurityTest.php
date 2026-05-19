<?php

it('uses quoted helpers for server-generated file writes', function () {
    foreach ([
        'app/Models/Server.php',
        'app/Livewire/Server/CaCertificate/Show.php',
        'app/Actions/Server/ConfigureCloudflared.php',
        'app/Actions/Server/InstallDocker.php',
        'app/Livewire/Server/Proxy/NewDynamicConfiguration.php',
        'app/Actions/Proxy/SaveProxyConfiguration.php',
        'app/Models/Service.php',
    ] as $path) {
        $source = file_get_contents(__DIR__.'/../../'.$path);

        expect($source)
            ->not->toContain('base64 -d | tee')
            ->not->toContain('base64 -d | tee $')
            ->not->toContain('rm -rf $caCertPath')
            ->toContain('writeBase64FileCommand');
    }
});
