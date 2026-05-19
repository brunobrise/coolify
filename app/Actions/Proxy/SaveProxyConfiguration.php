<?php

namespace App\Actions\Proxy;

use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveProxyConfiguration
{
    use AsAction;

    private const MAX_BACKUPS = 10;

    public function handle(Server $server, string $configuration): void
    {
        $proxy_path = $server->proxyPath();
        $escaped_proxy_path = escapeshellarg($proxy_path);
        $docker_compose_yml_base64 = base64_encode($configuration);
        $new_hash = str($docker_compose_yml_base64)->pipe('md5')->value;

        // Only create a backup if the configuration actually changed
        $old_hash = $server->proxy->get('last_saved_settings');
        $config_changed = $old_hash && $old_hash !== $new_hash;

        // Update the saved settings hash and store full config as database backup
        $server->proxy->last_saved_settings = $new_hash;
        $server->proxy->last_saved_proxy_configuration = $configuration;
        $server->save();

        $backup_path = "$proxy_path/backups";
        $escaped_backup_path = escapeshellarg($backup_path);
        $compose_path = "$proxy_path/docker-compose.yml";

        // Transfer the configuration file to the server, with backup if changed
        $commands = ["mkdir -p {$escaped_proxy_path}"];

        if ($config_changed) {
            $short_hash = substr($old_hash, 0, 8);
            $timestamp = now()->format('Y-m-d_H-i-s');
            $backup_file = "docker-compose.{$timestamp}.{$short_hash}.yml";
            $backup_file_path = "{$backup_path}/{$backup_file}";
            $matching_backup_glob = "{$escaped_backup_path}/docker-compose.*.{$short_hash}.yml";
            $commands[] = "mkdir -p {$escaped_backup_path}";
            // Skip backup if a file with the same hash already exists (identical content)
            $commands[] = "ls {$matching_backup_glob} 1>/dev/null 2>&1 || cp -f ".escapeshellarg($compose_path).' '.escapeshellarg($backup_file_path).' 2>/dev/null || true';
            // Prune old backups, keep only the most recent ones
            $commands[] = 'cd '.$escaped_backup_path.' && ls -1t docker-compose.*.yml 2>/dev/null | tail -n +'.((int) self::MAX_BACKUPS + 1).' | xargs rm -f 2>/dev/null || true';
        }

        $commands[] = writeBase64FileCommand($compose_path, $docker_compose_yml_base64);

        instant_remote_process($commands, $server);
    }
}
