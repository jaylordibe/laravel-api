<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
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
 * Argument round-trip check: proves structured arguments arrive validated and come back unchanged.
 */
#[Name('echo')]
#[Title('Echo')]
#[Description('Returns the given message unchanged, to check that tool arguments are passed through correctly.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class EchoTool extends Tool
{

    /**
     * Longest message accepted.
     *
     * @var int
     */
    public const int MESSAGE_MAX_LENGTH = 1000;

    /**
     * Handle the tool request.
     *
     * Invalid arguments throw a ValidationException, which laravel/mcp returns as a tool result with
     * `isError: true` and the message, so the model can correct its call. Arguments other than
     * `message` are ignored, matching the advertised input schema, which does not close the object.
     *
     * @param Request $request
     *
     * @return ResponseFactory
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:' . self::MESSAGE_MAX_LENGTH, 'not_regex:/^\s*$/u'],
        ], [
            'message.required' => 'Provide a "message" string to echo, for example "Hello MCP".',
            'message.not_regex' => 'The "message" must not be empty or whitespace only.',
            'message.max' => 'The "message" must be at most ' . self::MESSAGE_MAX_LENGTH . ' characters.',
        ]);

        return Response::structured(['success' => true, 'message' => $validated['message']]);
    }

    /**
     * Get the tool's input schema.
     *
     * @param JsonSchema $schema
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()
                ->min(1)
                ->max(self::MESSAGE_MAX_LENGTH)
                ->description('The text to echo back: 1 to ' . self::MESSAGE_MAX_LENGTH . ' characters, not only whitespace.')
                ->required(),
        ];
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
            'message' => $schema->string()->required(),
        ];
    }

}
