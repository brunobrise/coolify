<?php

it('does not dump certificate models to diagnostics', function () {
    $source = file_get_contents(__DIR__.'/../../app/Models/Server.php');

    expect($source)
        ->toContain("'common_name' => \$caCertificate?->common_name")
        ->not->toContain("ray('CA certificate generated', \$caCertificate)");
});
