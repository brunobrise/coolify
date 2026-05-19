<?php

use App\Jobs\SendMessageToSlackJob;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

it('sends Slack notifications to valid webhook URLs', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $job = new SendMessageToSlackJob(
        new SlackMessage('Deploy complete', 'Application is running.'),
        'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXX'
    );

    $job->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXX');
});

it('blocks Slack notifications to unsafe webhook URLs', function () {
    Http::fake();
    Log::shouldReceive('warning')
        ->atLeast()
        ->once();

    $job = new SendMessageToSlackJob(
        new SlackMessage('Deploy complete', 'Application is running.'),
        'http://169.254.169.254/latest/meta-data/'
    );

    $job->handle();

    Http::assertNothingSent();
});
