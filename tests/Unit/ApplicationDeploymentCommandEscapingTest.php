<?php

it('does not use raw base64 tee file writes in deployment jobs', function () {
    $source = file_get_contents(__DIR__.'/../../app/Jobs/ApplicationDeploymentJob.php');

    expect($source)
        ->not->toContain('base64 -d | tee')
        ->not->toContain('docker logs -n 100 {$this->container_name}')
        ->not->toContain('cat {$this->workdir}')
        ->not->toContain('mkdir -p {$this->workdir}')
        ->not->toContain('mkdir -p {$this->configuration_dir}')
        ->not->toContain('touch {$this->configuration_dir}')
        ->not->toContain('rm -f $this->configuration_dir');
});
