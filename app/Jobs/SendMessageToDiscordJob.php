<?php

namespace App\Jobs;

use App\Notifications\Dto\DiscordMessage;
use App\Rules\SafeWebhookUrl;
use App\Support\SafeUrlHost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SendMessageToDiscordJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    public $backoff = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 5;

    public function __construct(
        public DiscordMessage $message,
        public string $webhookUrl
    ) {
        $this->onQueue('high');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! $this->hasSafeWebhookUrl()) {
            return;
        }

        Http::timeout(10)->withoutRedirecting()->post($this->webhookUrl, $this->message->toPayload());
    }

    private function hasSafeWebhookUrl(): bool
    {
        $validator = Validator::make(
            ['webhook_url' => $this->webhookUrl],
            ['webhook_url' => ['required', 'url', new SafeWebhookUrl]]
        );

        if ($validator->passes()) {
            return true;
        }

        Log::warning('SendMessageToDiscordJob: blocked unsafe webhook URL', [
            'url' => SafeUrlHost::redactedUrlForLog($this->webhookUrl),
            'errors' => $validator->errors()->all(),
        ]);

        return false;
    }
}
