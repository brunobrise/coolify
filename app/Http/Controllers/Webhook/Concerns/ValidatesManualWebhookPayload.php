<?php

namespace App\Http\Controllers\Webhook\Concerns;

use App\Models\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait ValidatesManualWebhookPayload
{
    private function manualWebhookApplications(?string $repository, ?string $branch): Collection
    {
        $repository = $this->normalizeWebhookRepository($repository);
        if (! $repository || blank($branch)) {
            return collect();
        }

        return Application::where('git_branch', $branch)
            ->get()
            ->filter(fn (Application $application) => $this->applicationMatchesRepository($application, $repository))
            ->values();
    }

    private function applicationMatchesRepository(Application $application, string $repository): bool
    {
        return $this->normalizeWebhookRepository($application->git_repository) === $repository;
    }

    private function normalizeWebhookRepository(?string $repository): ?string
    {
        if (! is_string($repository)) {
            return null;
        }

        $repository = trim($repository);
        if ($repository === '' || strlen($repository) > 255 || str_contains($repository, '\\')) {
            return null;
        }

        if (str_contains($repository, '://')) {
            $path = parse_url($repository, PHP_URL_PATH);
            if (! is_string($path)) {
                return null;
            }
            $repository = ltrim($path, '/');
        } elseif (preg_match('/^[^@\s]+@[^:\s]+:(?<path>.+)$/', $repository, $matches) === 1) {
            $repository = $matches['path'];
        }

        $repository = trim($repository, '/');
        $repository = preg_replace('/\.git$/i', '', $repository) ?? '';

        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.-]*(\/[A-Za-z0-9][A-Za-z0-9_.-]*){1,19}\z/', $repository)) {
            return null;
        }

        return Str::lower($repository);
    }
}
