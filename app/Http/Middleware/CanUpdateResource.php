<?php

namespace App\Http\Middleware;

use App\Models\Application;
use App\Models\Environment;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanUpdateResource
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Authentication required.');
        }

        $team = $user->currentTeam();
        if (! $team) {
            abort(403, 'A current team is required.');
        }

        if (! $user->isAdminFromSession()) {
            abort(403, 'You need admin or owner permissions to update this resource.');
        }

        if ($user->isInstanceAdmin()) {
            return $next($request);
        }

        if (! $this->routeResourceBelongsToTeam($request, $team->id)) {
            abort(404, 'Resource not found.');
        }

        return $next($request);
    }

    private function routeResourceBelongsToTeam(Request $request, int $teamId): bool
    {
        if ($uuid = $this->routeString($request, 'application_uuid')) {
            return Application::whereUuid($uuid)
                ->whereRelation('environment.project', 'team_id', $teamId)
                ->exists();
        }

        if ($uuid = $this->routeString($request, 'service_uuid')) {
            return Service::whereUuid($uuid)
                ->whereRelation('environment.project', 'team_id', $teamId)
                ->exists();
        }

        if ($uuid = $this->routeString($request, 'stack_service_uuid')) {
            return ServiceApplication::whereUuid($uuid)
                ->whereRelation('service.environment.project', 'team_id', $teamId)
                ->exists()
                || ServiceDatabase::whereUuid($uuid)
                    ->whereRelation('service.environment.project', 'team_id', $teamId)
                    ->exists();
        }

        $databaseUuid = $this->routeString($request, 'database_uuid') ?? $this->routeString($request, 'databaseUuid');
        if ($databaseUuid) {
            return getResourceByUuid($databaseUuid, $teamId) !== null;
        }

        if ($uuid = $this->routeString($request, 'server_uuid')) {
            return Server::whereUuid($uuid)
                ->where('team_id', $teamId)
                ->exists();
        }

        if ($uuid = $this->routeString($request, 'environment_uuid')) {
            return Environment::whereUuid($uuid)
                ->whereRelation('project', 'team_id', $teamId)
                ->exists();
        }

        if ($uuid = $this->routeString($request, 'project_uuid')) {
            return Project::whereUuid($uuid)
                ->where('team_id', $teamId)
                ->exists();
        }

        return true;
    }

    private function routeString(Request $request, string $key): ?string
    {
        $value = $request->route($key);

        return filled($value) ? (string) $value : null;
    }
}
