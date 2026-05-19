<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ResourcesController extends Controller
{
    private const ALWAYS_HIDDEN_FIELDS = [
        'id',
        'laravel_through_key',
        'destination',
        'environment',
        'fileStorages',
        'persistentStorages',
        'resourceable',
        'resourceable_id',
        'resourceable_type',
        'scheduledBackups',
        'server',
        'settings',
        'source',
        'tags',
        'additional_servers',
    ];

    private const SENSITIVE_FIELDS = [
        'clickhouse_admin_password',
        'custom_docker_run_options',
        'custom_labels',
        'custom_nginx_configuration',
        'docker_compose',
        'docker_compose_custom_build_command',
        'docker_compose_custom_start_command',
        'docker_compose_raw',
        'dockerfile',
        'dragonfly_password',
        'external_db_url',
        'git_full_url',
        'http_basic_auth_password',
        'internal_db_url',
        'keydb_password',
        'manual_webhook_secret_bitbucket',
        'manual_webhook_secret_gitea',
        'manual_webhook_secret_github',
        'manual_webhook_secret_gitlab',
        'mariadb_password',
        'mariadb_root_password',
        'mongo_initdb_root_password',
        'mysql_password',
        'mysql_root_password',
        'post_deployment_command',
        'postgres_password',
        'pre_deployment_command',
        'private_key_id',
        'redis_password',
        'real_value',
        'value',
    ];

    #[OA\Get(
        summary: 'List',
        description: 'Get all resources.',
        path: '/resources',
        operationId: 'list-resources',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Resources'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Get all resources',
                content: new OA\JsonContent(
                    type: 'string',
                    example: 'Content is very complex. Will be implemented later.',
                ),
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
        ]
    )]
    public function resources(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // General authorization check for viewing resources - using Project as base resource type
        $this->authorize('viewAny', Project::class);

        $projects = Project::where('team_id', $teamId)->get();
        $resources = collect();
        $resources->push($projects->pluck('applications')->flatten());
        $resources->push($projects->pluck('services')->flatten());
        foreach (collect(DATABASE_TYPES) as $db) {
            $resources->push($projects->pluck(str($db)->plural(2))->flatten());
        }
        $resources = $resources->flatten();
        $resources = $resources->map(function ($resource) {
            $payload = $this->serializeResource($resource);
            $payload['status'] = $resource->status;
            $payload['type'] = $resource->type();

            return $payload;
        });

        return response()->json(serializeApiResponse($resources));
    }

    private function serializeResource($resource): array
    {
        $resource->makeHidden(self::ALWAYS_HIDDEN_FIELDS);
        if (request()->attributes->get('can_read_sensitive', false) === false) {
            $resource->makeHidden(self::SENSITIVE_FIELDS);
        }

        return $resource->toArray();
    }
}
