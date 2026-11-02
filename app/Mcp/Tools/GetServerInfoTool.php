<?php

namespace App\Mcp\Tools;

use App\Mcp\Servers\ApiServer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Identifies the connected application.
 *
 * Deliberately only the public identity (application name and MCP server version): no environment,
 * host, commit or configuration value is ever added here.
 */
#[Name('get_server_info')]
#[Title('Get server info')]
#[Description('Identifies the connected application: its name, release version and MCP status.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class GetServerInfoTool extends Tool
{

    /**
     * Handle the tool request.
     *
     * @param Request $request
     *
     * @return ResponseFactory
     */
    public function handle(Request $request): ResponseFactory
    {
        return Response::structured([
            'name' => (string) config('app.name'),
            'mcp' => true,
            'status' => 'ready',
            'version' => ApiServer::VERSION,
        ]);
    }

    /**
     * The tool takes no input.
     *
     * @param JsonSchema $schema
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Get the tool's output schema.
     *
     * @param JsonSchema $schema
     *
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'mcp' => $schema->boolean()->required(),
            'status' => $schema->string()->enum(['ready'])->required(),
            'version' => $schema->string()->required(),
        ];
    }

}
