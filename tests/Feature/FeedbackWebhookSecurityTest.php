<?php

use App\Http\Controllers\Api\OtherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

it('does not forward feedback to unsafe webhook urls', function () {
    Http::fake();
    config()->set('constants.webhooks.feedback_discord_webhook', 'http://127.0.0.1/internal');

    $request = Request::create('/api/feedback', 'POST', [
        'content' => 'This is a valid feedback message for testing purposes.',
    ]);

    $response = (new OtherController)->feedback($request);

    expect($response->getStatusCode())->toBe(200);
    Http::assertNothingSent();
});

it('sends feedback webhook without following redirects', function () {
    $source = file_get_contents(__DIR__.'/../../app/Http/Controllers/Api/OtherController.php');

    expect($source)
        ->toContain('new SafeWebhookUrl')
        ->toContain('withoutRedirecting()->post($webhook_url');
});
