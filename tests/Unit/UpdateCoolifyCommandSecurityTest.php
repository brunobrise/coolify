<?php

it('quotes update script command arguments', function () {
    $source = file_get_contents(__DIR__.'/../../app/Actions/Server/UpdateCoolify.php');

    expect($source)
        ->toContain('escapeshellarg($upgradeScriptUrl)')
        ->toContain('escapeshellarg($this->latestVersion)')
        ->toContain('escapeshellarg($latestHelperImageVersion)')
        ->not->toContain('curl -fsSL {$upgradeScriptUrl}')
        ->not->toContain('upgrade.sh $this->latestVersion $latestHelperImageVersion');
});
