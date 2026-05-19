<?php

namespace App\Rules;

use App\Support\SafeUrlHost;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

class SafeWebhookUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Validates that a webhook URL is safe for server-side requests.
     * Blocks loopback addresses, cloud metadata endpoints (link-local),
     * and dangerous hostnames while allowing private network IPs
     * for self-hosted deployments.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $scheme = strtolower(parse_url($value, PHP_URL_SCHEME) ?? '');
        if (! in_array($scheme, ['https', 'http'])) {
            $fail('The :attribute must use the http or https scheme.');

            return;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (! $host) {
            $fail('The :attribute must contain a valid host.');

            return;
        }

        $host = SafeUrlHost::normalize($host);

        // Block well-known dangerous hostnames
        $blockedHosts = ['localhost', '0.0.0.0', '::', '::1'];
        if (in_array($host, $blockedHosts, true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) {
            Log::warning('Webhook URL points to blocked host', [
                'attribute' => $attribute,
                'host' => $host,
                'ip' => request()->ip(),
                'user_id' => auth()->id(),
            ]);
            $fail('The :attribute must not point to localhost or internal hosts.');

            return;
        }

        foreach (SafeUrlHost::resolvedIps($host) as $resolvedIp) {
            if (! SafeUrlHost::isLoopbackOrLinkLocal($resolvedIp)) {
                continue;
            }

            Log::warning('Webhook URL points to blocked IP range', [
                'attribute' => $attribute,
                'host' => $host,
                'resolved_ip' => $resolvedIp,
                'ip' => request()?->ip(),
                'user_id' => auth()->id(),
            ]);
            $fail('The :attribute must not point to loopback or link-local addresses.');

            return;
        }
    }
}
