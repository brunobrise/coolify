<?php

it('does not dump cloud provider token validation responses', function () {
    $source = file_get_contents(__DIR__.'/../../app/Livewire/Security/CloudProviderTokenForm.php');

    expect($source)
        ->toContain("'successful' => \$response->successful()")
        ->not->toContain('ray($response)');
});

it('redacts Hetzner server provisioning diagnostics', function () {
    $source = file_get_contents(__DIR__.'/../../app/Services/HetznerService.php');

    expect($source)
        ->toContain("Arr::except(\$params, ['user_data'])")
        ->toContain("'has_user_data' => filled(\$params['user_data'] ?? null)")
        ->toContain("'server_id' => data_get(\$response, 'server.id')")
        ->not->toContain("'params' => \$params")
        ->not->toContain("'response' => \$response");
});
