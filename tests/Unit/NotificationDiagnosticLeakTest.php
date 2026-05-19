<?php

it('does not log pushover credentials when building payloads', function () {
    $source = file_get_contents(__DIR__.'/../../app/Notifications/Dto/PushoverMessage.php');

    expect($source)
        ->not->toContain("Log::info('Pushover message', \$payload)")
        ->not->toContain('Illuminate\Support\Facades\Log');
});

it('redacts webhook URLs and omits webhook bodies from diagnostics', function () {
    $diagnosticSources = collect([
        'app/Notifications/Channels/WebhookChannel.php',
        'app/Livewire/Notifications/Webhook.php',
        'app/Jobs/SendWebhookJob.php',
    ])->mapWithKeys(fn (string $path) => [$path => file_get_contents(__DIR__.'/../../'.$path)]);

    expect($diagnosticSources['app/Notifications/Channels/WebhookChannel.php'])
        ->toContain('SafeUrlHost::redactedUrlForLog($webhookSettings->webhook_url)')
        ->toContain("'payload_keys' => array_keys(\$payload)")
        ->not->toContain("'url' => \$webhookSettings->webhook_url")
        ->not->toContain("'payload' => \$payload");

    expect($diagnosticSources['app/Livewire/Notifications/Webhook.php'])
        ->toContain("SafeUrlHost::redactedUrlForLog(\$this->settings->webhook_url ?? '')")
        ->not->toContain("'webhook_url' => \$this->settings->webhook_url");

    expect($diagnosticSources['app/Jobs/SendWebhookJob.php'])
        ->toContain('SafeUrlHost::redactedUrlForLog($this->webhookUrl)')
        ->toContain("'payload_keys' => array_keys(\$this->payload)")
        ->toContain("'body_bytes' => strlen(\$response->body())")
        ->not->toContain("'payload' => \$this->payload")
        ->not->toContain("'body' => \$response->body()");
});
