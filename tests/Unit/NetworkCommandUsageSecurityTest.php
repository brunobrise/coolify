<?php

it('uses shared docker network command builders for dynamic network names', function () {
    foreach ([
        'app/Jobs/ApplicationDeploymentJob.php',
        'app/Models/StandaloneDocker.php',
        'app/Console/Commands/Init.php',
    ] as $path) {
        $source = file_get_contents(__DIR__.'/../../'.$path);

        expect($source)
            ->not->toContain('docker network connect {$networkId}')
            ->not->toContain('docker network rm {$safe}')
            ->not->toContain('docker network inspect {$safeNetwork}')
            ->toContain('dockerNetwork');
    }
});
