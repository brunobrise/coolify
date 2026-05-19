<?php

it('quotes directory removal paths', function () {
    expect(removeDirectoryCommand('/data/coolify/app config'))
        ->toBe("rm -rf '/data/coolify/app config'");
});

it('rejects command injection in directory removal paths', function () {
    expect(fn () => removeDirectoryCommand('/data/coolify/app; id #'))
        ->toThrow(Exception::class);
});
