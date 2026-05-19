<?php

namespace App\Services\Kubernetes;

use Symfony\Component\Yaml\Yaml;

class KubernetesManifestData
{
    public function stringList(?string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    public function keyValueMap(?string $value): array
    {
        return collect(preg_split('/[\r\n]+/', (string) $value))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->mapWithKeys(function (string $line) {
                $separator = str_contains($line, '=') ? '=' : ':';
                [$key, $value] = array_pad(explode($separator, $line, 2), 2, '');

                $key = trim($key);
                $value = trim($value);

                return $key === '' ? [] : [$key => $value];
            })
            ->toArray();
    }

    public function yamlList(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $parsed = Yaml::parse($value);

        if (! is_array($parsed)) {
            return [];
        }

        if (array_is_list($parsed)) {
            return collect($parsed)->filter(fn ($item) => is_array($item))->values()->toArray();
        }

        return [$parsed];
    }

    public function intOrPercent(null|int|string $value, int $default): int|string
    {
        if (is_string($value) && preg_match('/^\d+%$/', $value) === 1) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public function containerResources(array $limits, array $requests, bool $autoscaling = false): array
    {
        $limits = $this->resourceMap($limits);
        $requests = $this->resourceMap($requests);

        if ($autoscaling && ! isset($requests['cpu']) && isset($limits['cpu'])) {
            $requests['cpu'] = $limits['cpu'];
        }

        return array_filter([
            'limits' => $limits,
            'requests' => $requests,
        ]);
    }

    public function composeResources(array $service, bool $autoscaling = false): array
    {
        return $this->containerResources([
            'cpu' => $this->cpuQuantity(data_get($service, 'deploy.resources.limits.cpus')),
            'memory' => $this->resourceQuantity(data_get($service, 'deploy.resources.limits.memory')),
        ], [
            'cpu' => $this->cpuQuantity(data_get($service, 'deploy.resources.reservations.cpus')),
            'memory' => $this->resourceQuantity(data_get($service, 'deploy.resources.reservations.memory')),
        ], $autoscaling);
    }

    public function cpuQuantity(null|int|float|string $value): ?string
    {
        $value = $this->resourceQuantity($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d+\.\d+$/', $value) === 1) {
            $millicpu = (int) round(((float) $value) * 1000);

            return $millicpu <= 0 ? null : "{$millicpu}m";
        }

        return $value;
    }

    public function resourceQuantity(null|int|float|string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || (is_numeric($value) && (float) $value == 0.0) ? null : $value;
    }

    public function dnsLabel(string $value, string $fallback = 'resource', int $maxLength = 63): string
    {
        $label = str($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9-]+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();

        if ($label === '') {
            $label = $fallback;
        }

        return trim(substr($label, 0, $maxLength), '-') ?: $fallback;
    }

    private function resourceMap(array $resources): array
    {
        return collect($resources)
            ->filter(fn ($value) => $this->resourceQuantity($value) !== null)
            ->toArray();
    }
}
