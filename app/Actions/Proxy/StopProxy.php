<?php

namespace App\Actions\Proxy;

use App\Events\ProxyStatusChanged;
use App\Events\ProxyStatusChangedUI;
use App\Models\Server;
use App\Services\ProxyDashboardCacheService;
use Lorisleiva\Actions\Concerns\AsAction;

class StopProxy
{
    use AsAction;

    public function handle(Server $server, bool $forceStop = true, int $timeout = 30, bool $restarting = false)
    {
        try {
            $containerName = $server->isSwarm() ? 'coolify-proxy_traefik' : 'coolify-proxy';
            $server->proxy->status = 'stopping';
            $server->save();

            if (! $restarting) {
                ProxyStatusChangedUI::dispatch($server->team_id);
            }

            instant_remote_process(command: [
                dockerStopContainerCommand($containerName, $timeout).' 2>/dev/null || true',
                dockerRemoveContainerCommand($containerName).' 2>/dev/null || true',
                '# Wait for container to be fully removed',
                'for i in {1..10}; do',
                '    if ! '.dockerPsNameExistsCommand($containerName).' >/dev/null; then',
                '        break',
                '    fi',
                '    sleep 1',
                'done',
            ], server: $server, throwError: false);

            $server->proxy->force_stop = $forceStop;
            $server->proxy->status = 'exited';
            $server->save();
        } catch (\Throwable $e) {
            return handleError($e);
        } finally {
            ProxyDashboardCacheService::clearCache($server);

            if (! $restarting) {
                ProxyStatusChanged::dispatch($server->id);
            }
        }
    }
}
