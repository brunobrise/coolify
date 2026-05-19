<?php

namespace App\Livewire\Server\Proxy;

use App\Models\Server;
use Illuminate\Support\Collection;
use Livewire\Component;

class DynamicConfigurations extends Component
{
    public ?Server $server = null;

    public $parameters = [];

    public Collection $contents;

    public function getListeners()
    {
        $teamId = auth()->user()->currentTeam()->id;

        return [
            "echo-private:team.{$teamId},ProxyStatusChangedUI" => 'loadDynamicConfigurations',
            'loadDynamicConfigurations',
        ];
    }

    protected $rules = [
        'contents.*' => 'nullable|string',
    ];

    public function initLoadDynamicConfigurations()
    {
        $this->loadDynamicConfigurations();
    }

    public function loadDynamicConfigurations()
    {
        $proxy_path = $this->server->proxyPath();
        $dynamicPath = "{$proxy_path}/dynamic";
        $escapedDynamicPath = escapeshellarg($dynamicPath);
        $files = instant_remote_process(["mkdir -p {$escapedDynamicPath} && ls -1 {$escapedDynamicPath}"], $this->server);
        $files = self::safeDynamicConfigurationFiles($files);
        $contents = collect([]);
        foreach ($files as $file) {
            $without_extension = str_replace('.', '|', $file);
            $fullPath = "{$dynamicPath}/{$file}";
            $escapedPath = escapeshellarg($fullPath);
            $content = instant_remote_process(["cat {$escapedPath}"], $this->server);
            $contents[$without_extension] = $content ?? '';
        }
        $this->contents = $contents;
        $this->dispatch('$refresh');
        $this->dispatch('success', 'Dynamic configurations loaded.');
    }

    public static function safeDynamicConfigurationFiles(?string $files): Collection
    {
        return collect(explode("\n", (string) $files))
            ->map(fn ($file) => trim($file))
            ->filter()
            ->filter(fn ($file) => self::isSafeDynamicConfigurationFilename($file))
            ->sort()
            ->values();
    }

    private static function isSafeDynamicConfigurationFilename(string $file): bool
    {
        try {
            validateFilenameSafe($file, 'proxy configuration filename');
        } catch (\Throwable) {
            return false;
        }

        return preg_match('/\A[a-zA-Z0-9._-]+\z/', $file) === 1 && ! str_starts_with($file, '.');
    }

    public function mount()
    {
        $this->parameters = get_route_parameters();
        try {
            $this->server = Server::ownedByCurrentTeam()->whereUuid(request()->server_uuid)->first();
            if (is_null($this->server)) {
                return redirect()->route('server.index');
            }
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function render()
    {
        return view('livewire.server.proxy.dynamic-configurations');
    }
}
