<?php

it('quotes directory removal paths', function () {
    expect(removeDirectoryCommand('/data/coolify/app config'))
        ->toBe("rm -rf '/data/coolify/app config'");
});

it('rejects command injection in directory removal paths', function () {
    expect(fn () => removeDirectoryCommand('/data/coolify/app; id #'))
        ->toThrow(Exception::class);
});

it('builds process kill commands for numeric process ids', function () {
    expect(killProcessCommand('12345'))->toBe('kill -9 12345');
});

it('rejects command injection in process ids', function () {
    expect(fn () => killProcessCommand('12345; id #'))
        ->toThrow(InvalidArgumentException::class);
});
