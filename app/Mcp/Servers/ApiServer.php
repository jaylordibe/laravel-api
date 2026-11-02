<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\EchoTool;
use App\Mcp\Tools\GetCurrentUserTool;
use App\Mcp\Tools\GetServerInfoTool;
use App\Mcp\Tools\PingTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;

/**
 * The application's MCP server, served at /api/mcp (routes/ai.php). See docs/mcp.md.
 *
 * Stateless: laravel/mcp builds the server per HTTP request and keeps no session, so any replica
 * can answer any request. It speaks the 2026-07-28 protocol (`server/discover`) and answers
 * 2025-era clients (`initialize`) from the same endpoint.
 */
#[Instructions('Connectivity and identity tools for this API. All tools are read-only and safe to call repeatedly.')]
class ApiServer extends Server
{

    /**
     * The MCP server's release version, reported in the handshake and by `get_server_info`.
     *
     * The application declares no version of its own (composer.json has none), so this constant is
     * the one place it is stated. Bump it when the tool surface changes.
     *
     * @var string
     */
    public const string VERSION = '1.1.0';

    /**
     * The tools, in `tools/list` order (the spec asks that order to be deterministic).
     *
     * @var array<int, class-string<Server\Tool>>
     */
    protected array $tools = [
        PingTool::class,
        EchoTool::class,
        GetServerInfoTool::class,
        GetCurrentUserTool::class,
    ];

    /**
     * Only tools are offered.
     *
     * @var array<string, array<string, bool>>
     */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
    ];

    /**
     * Identify the server as this application.
     *
     * @return void
     */
    protected function boot(): void
    {
        $this->name = (string) config('app.name');
        $this->version = self::VERSION;
    }

}
