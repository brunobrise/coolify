<?php

use App\Jobs\SendMessageToDiscordJob;
use App\Notifications\Dto\DiscordMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

it('sends Discord notifications to valid webhook URLs', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $job = new SendMessageToDiscordJob(
        new DiscordMessage('Deploy complete', 'Application is running.', DiscordMessage::successColor()),
        'https://discord.com/api/webhooks/123/token'
    );

    $job->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/webhooks/123/token');
});

it('blocks Discord notifications to unsafe webhook URLs', function () {
    Http::fake();
    Log::shouldReceive('warning')
        ->atLeast()
        ->once();

    $job = new SendMessageToDiscordJob(
        new DiscordMessage('Deploy complete', 'Application is running.', DiscordMessage::successColor()),
        'http://169.254.169.254/latest/meta-data/'
    );

    $job->handle();

    Http::assertNothingSent();
});
