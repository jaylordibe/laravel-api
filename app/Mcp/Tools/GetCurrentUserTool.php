<?php

namespace App\Mcp\Tools;

use App\Models\User;
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
 * Identifies the signed-in user this connection acts for.
 *
 * Also the pattern for any user-aware tool: EnsureMcpToken has verified the MCP access token and made the
 * `api` guard the default, so `$request->user()` is the signed-in user. A tool then goes through the same
 * services and access checks REST uses (`Gate::forUser($user)`, ownership-scoped repository queries) and
 * never accepts a user id from its arguments as proof of access.
 */
#[Name('get_current_user')]
#[Title('Get current user')]
#[Description('Identifies the signed-in user this connection acts for: their id, email and roles.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class GetCurrentUserTool extends Tool
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
        /** @var User $user */
        $user = $request->user();

        return Response::structured([
            'id' => (string) $user->id,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->sort()->values()->all(),
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
            'id' => $schema->string()->required(),
            'email' => $schema->string()->required(),
            'roles' => $schema->array()->items($schema->string())->required(),
        ];
    }

}
