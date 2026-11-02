<?php

namespace App\Mcp\Tools;

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
 * Connectivity check: proves the endpoint is reachable, authenticated and executing tools.
 */
#[Name('ping')]
#[Title('Ping')]
#[Description('Checks that the MCP server is reachable and executing tools. Returns "pong".')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class PingTool extends Tool
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
        return Response::structured(['success' => true, 'message' => 'pong']);
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
            'success' => $schema->boolean()->required(),
            'message' => $schema->string()->enum(['pong'])->required(),
        ];
    }

}
